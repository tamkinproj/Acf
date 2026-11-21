<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Audit;
use App\Support\ChatCards;
use App\Support\PermissionCatalog;
use Illuminate\Http\Request;

/**
 * Office chat. Everything here needs the chat.use permission (President Admin and staff; never the system roles).
 * The General group is for everyone who has chat.use. A one-to-one chat has exactly two members.
 */
class ChatController extends Controller
{
    private function me(Request $r): User
    {
        return $r->user();
    }

    private function canChat(User $u): bool
    {
        return $u->active && PermissionCatalog::allows($u, 'chat.use');
    }

    /** May this person read and write this conversation? */
    private function mayUse(User $u, ChatConversation $c): bool
    {
        if ($c->type === 'general') {
            return true;   // chat.use is already required by the route
        }

        return ChatMember::where('conversation_id', $c->id)->where('user_id', $u->id)->exists();
    }

    private function memberRow(User $u, ChatConversation $c): ChatMember
    {
        return ChatMember::firstOrCreate(['conversation_id' => $c->id, 'user_id' => $u->id]);
    }

    /** People who can be chatted with: active accounts of the foundation that hold chat.use (the system roles never do). */
    private function chatPeople(User $me)
    {
        return User::where('foundation_id', $me->foundation_id)->where('active', true)->where('id', '!=', $me->id)->orderBy('name')->get()
            ->filter(fn (User $u) => PermissionCatalog::allows($u, 'chat.use'))->values();
    }

    private function unreadIn(ChatConversation $c, User $me): int
    {
        $last = (int) ChatMember::where('conversation_id', $c->id)->where('user_id', $me->id)->value('last_read_id');

        return ChatMessage::where('conversation_id', $c->id)->where('id', '>', $last)->where('user_id', '!=', $me->id)->count();
    }

    private function messageOut(ChatMessage $m, User $viewer): array
    {
        $out = [
            'id' => $m->id, 'user_id' => $m->user_id, 'user' => $m->user?->name, 'mine' => $m->user_id === $viewer->id,
            'av' => $m->user?->avatar_v, 'body' => $m->body, 'at' => $m->created_at?->toIso8601String(), 'card' => null,
        ];
        if ($m->card_kind) {
            $out['card'] = ChatCards::describe($viewer, $m->card_kind, $m->card_id, $m->card_extra);
        }

        return $out;
    }

    private function preview(?ChatMessage $m): ?string
    {
        if (! $m) {
            return null;
        }

        return $m->body !== null && $m->body !== '' ? mb_substr($m->body, 0, 80) : '['.$m->card_kind.']';
    }

    public function conversations(Request $request)
    {
        $me = $this->me($request);
        $general = ChatConversation::general($me->foundation_id);
        $this->memberRow($me, $general);

        $directs = ChatConversation::where('type', 'direct')
            ->whereIn('id', ChatMember::where('user_id', $me->id)->pluck('conversation_id'))->get();

        $rows = collect([$general])->merge($directs)->map(function (ChatConversation $c) use ($me) {
            $last = ChatMessage::where('conversation_id', $c->id)->orderByDesc('id')->first();
            $other = null;
            if ($c->type === 'direct') {
                $otherId = ChatMember::where('conversation_id', $c->id)->where('user_id', '!=', $me->id)->value('user_id');
                $o = $otherId ? User::find($otherId) : null;
                $other = $o ? ['id' => $o->id, 'name' => $o->name, 'username' => $o->username, 'avatar' => $o->avatar_v, 'role_label' => config("roles.roles.{$o->role}.label")] : null;
            }

            return [
                'id' => $c->id, 'type' => $c->type, 'title' => $c->type === 'general' ? 'General' : ($other['name'] ?? 'Chat'), 'other' => $other,
                'last' => $this->preview($last), 'last_by' => $last && $last->user_id !== $me->id ? $last->user?->name : null,
                'last_at' => $last?->created_at?->toIso8601String(), 'unread' => $this->unreadIn($c, $me),
            ];
        })->sortByDesc(fn ($r) => $r['type'] === 'general' ? PHP_INT_MAX : strtotime((string) $r['last_at']))->values();

        return ApiResponse::collection($rows);
    }

    public function unread(Request $request)
    {
        $me = $this->me($request);
        $ids = ChatConversation::general($me->foundation_id)->id;
        $conv = ChatConversation::where('type', 'direct')->whereIn('id', ChatMember::where('user_id', $me->id)->pluck('conversation_id'))->pluck('id')->push($ids);
        $n = 0;
        foreach ($conv as $cid) {
            $last = (int) ChatMember::where('conversation_id', $cid)->where('user_id', $me->id)->value('last_read_id');
            $n += ChatMessage::where('conversation_id', $cid)->where('id', '>', $last)->where('user_id', '!=', $me->id)->count();
        }

        return ApiResponse::ok(['unread' => $n, 'shareable' => ChatCards::shareable($me)]);
    }

    public function people(Request $request)
    {
        $me = $this->me($request);

        return ApiResponse::collection($this->chatPeople($me)->map(fn (User $u) => [
            'id' => $u->id, 'name' => $u->name, 'username' => $u->username, 'avatar' => $u->avatar_v, 'role_label' => config("roles.roles.{$u->role}.label"),
            'department' => $u->department_key ? config("roles.departments.{$u->department_key}.name_en") : null,
        ]));
    }

    public function direct(Request $request)
    {
        $me = $this->me($request);
        $data = $request->validate(['user_id' => ['required', 'integer']]);
        $other = User::where('foundation_id', $me->foundation_id)->find($data['user_id']);
        if (! $other || $other->id === $me->id || ! $this->canChat($other)) {
            return ApiResponse::error('CANNOT_CHAT', 'This person cannot be messaged here.', 422);
        }
        $key = min($me->id, $other->id).'-'.max($me->id, $other->id);
        $c = ChatConversation::firstOrCreate(
            ['foundation_id' => $me->foundation_id, 'pair_key' => $key],
            ['type' => 'direct', 'created_by' => $me->id]
        );
        $this->memberRow($me, $c);
        $this->memberRow($other, $c);

        return ApiResponse::ok(['id' => $c->id, 'title' => $other->name]);
    }

    public function messages(Request $request, ChatConversation $conversation)
    {
        $me = $this->me($request);
        if (! $this->mayUse($me, $conversation)) {
            return ApiResponse::forbidden();
        }
        $after = (int) $request->query('after_id', 0);
        $q = ChatMessage::where('conversation_id', $conversation->id)->with('user');
        $rows = $after > 0 ? $q->where('id', '>', $after)->orderBy('id')->limit(100)->get() : $q->orderByDesc('id')->limit(60)->get()->reverse()->values();

        $member = $this->memberRow($me, $conversation);
        $max = (int) ChatMessage::where('conversation_id', $conversation->id)->max('id');
        if ($rows->count() && $max > $member->last_read_id && $rows->last()->id === $max) {
            $member->update(['last_read_id' => $max]);
        }

        $other = null;
        if ($conversation->type === 'direct') {
            $oid = ChatMember::where('conversation_id', $conversation->id)->where('user_id', '!=', $me->id)->value('user_id');
            $o = $oid ? User::find($oid) : null;
            $other = $o ? ['id' => $o->id, 'name' => $o->name, 'username' => $o->username, 'avatar' => $o->avatar_v, 'role_label' => config("roles.roles.{$o->role}.label")] : null;
        }

        return ApiResponse::collection($rows->map(fn (ChatMessage $m) => $this->messageOut($m, $me)), [
            'conversation' => ['id' => $conversation->id, 'type' => $conversation->type, 'title' => $conversation->type === 'general' ? 'General' : ($other['name'] ?? 'Chat'), 'other' => $other],
            'shareable' => ChatCards::shareable($me),
        ]);
    }

    public function send(Request $request, ChatConversation $conversation)
    {
        $me = $this->me($request);
        if (! $this->mayUse($me, $conversation)) {
            return ApiResponse::forbidden();
        }
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:2000'],
            'card' => ['nullable', 'array'],
            'card.kind' => ['required_with:card', 'string'],
            'card.id' => ['nullable', 'integer'],
            'card.month' => ['nullable', 'string', 'max:7'],
        ]);
        $body = trim((string) ($data['body'] ?? ''));
        $card = $data['card'] ?? null;
        if ($body === '' && ! $card) {
            return ApiResponse::error('EMPTY_MESSAGE', 'Write a message or attach a card.', 422);
        }

        $msg = ['foundation_id' => $me->foundation_id, 'conversation_id' => $conversation->id, 'user_id' => $me->id, 'body' => $body === '' ? null : $body, 'created_at' => now()];
        if ($card) {
            $kind = (string) $card['kind'];
            $id = isset($card['id']) ? (int) $card['id'] : null;
            $extra = $card['month'] ?? null;
            if (! ChatCards::valid($kind)) {
                return ApiResponse::error('BAD_CARD', 'This cannot be shared.', 422);
            }
            if (! ChatCards::canOpen($me, $kind)) {
                return ApiResponse::forbidden('You can only share what you can open yourself.');
            }
            if (! ChatCards::exists($kind, $id, $extra)) {
                return ApiResponse::error('BAD_CARD', 'That item was not found.', 422);
            }
            $msg += ['card_kind' => $kind, 'card_id' => $id, 'card_extra' => in_array($kind, ['payment', 'report'], true) ? $extra : null];
        }

        $m = ChatMessage::create($msg);
        $conversation->forceFill(['last_message_at' => $m->created_at])->save();
        $this->memberRow($me, $conversation)->update(['last_read_id' => $m->id]);
        if ($card) {
            Audit::record('chat.share', $request, $kind, $id, ['conversation' => $conversation->id, 'type' => $conversation->type, 'month' => $extra]);
        }

        return ApiResponse::created($this->messageOut($m->load('user'), $me));
    }

    /** Before sending a card: which people in this chat cannot open it (so the sender knows). */
    public function audience(Request $request, ChatConversation $conversation)
    {
        $me = $this->me($request);
        if (! $this->mayUse($me, $conversation)) {
            return ApiResponse::forbidden();
        }
        $kind = (string) $request->query('kind');
        if (! ChatCards::valid($kind)) {
            return ApiResponse::error('BAD_CARD', 'This cannot be shared.', 422);
        }
        $members = $conversation->type === 'general'
            ? $this->chatPeople($me)
            : User::whereIn('id', ChatMember::where('conversation_id', $conversation->id)->where('user_id', '!=', $me->id)->pluck('user_id'))->get();
        $cannot = $members->filter(fn (User $u) => ! ChatCards::canOpen($u, $kind));

        return ApiResponse::ok(['total' => $members->count(), 'cannot_open' => $cannot->count(), 'names' => $conversation->type === 'general' ? [] : $cannot->pluck('name')->values()]);
    }
}
