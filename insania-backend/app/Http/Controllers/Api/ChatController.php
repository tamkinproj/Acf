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
use Illuminate\Support\Facades\Storage;

/**
 * Office chat. Everything here needs the chat.use permission (President Admin and staff; never the system roles).
 * The General group is for everyone who has chat.use. A one-to-one chat has exactly two members.
 */
class ChatController extends Controller
{
    /** Colour themes a conversation can use (the app draws each one). */
    public const THEMES = ['lime', 'ocean', 'sunset', 'rose', 'violet', 'graphite'];

    private function me(Request $r): User
    {
        return $r->user();
    }

    private function canChat(User $u): bool
    {
        return $u->active && PermissionCatalog::allows($u, 'chat.use');
    }

    /** May this person change this conversation's name, theme, cover and members? The General group: the President Admin. A group: its maker or the President Admin. */
    private function mayManage(User $u, ChatConversation $c): bool
    {
        return $u->role === 'president_admin' || ($c->type === 'group' && (int) $c->created_by === $u->id);
    }

    /** Settings (members, name, theme, cover): members, and the President Admin for every group, who can run them without reading the messages. */
    private function maySee(User $u, ChatConversation $c): bool
    {
        return $this->mayUse($u, $c) || ($u->role === 'president_admin' && $c->type === 'group');
    }

    private function personOut(User $u): array
    {
        return ['id' => $u->id, 'name' => $u->name, 'username' => $u->username, 'avatar' => $u->avatar_v, 'role_label' => config("roles.roles.{$u->role}.label"),
            'department' => $u->department_key ? config("roles.departments.{$u->department_key}.name_en") : null];
    }

    /** May this person read and write this conversation? */
    private function mayUse(User $u, ChatConversation $c): bool
    {
        if ($c->type === 'general') {
            return true;   // chat.use is already required by the route
        }
        // direct chats and groups: only their members

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
        if ($m->file_kind) {   // the file itself is fetched with the sign-in token: chat/conversations/{c}/messages/{m}/file
            $out['file'] = ['kind' => $m->file_kind, 'ms' => $m->file_ms, 'size' => $m->file_size];
        }

        return $out;
    }

    private function preview(?ChatMessage $m): ?string
    {
        if (! $m) {
            return null;
        }

        if ($m->body !== null && $m->body !== '') {
            return mb_substr($m->body, 0, 80);
        }

        return $m->file_kind ? ($m->file_kind === 'audio' ? '[voice]' : '[photo]') : '['.$m->card_kind.']';
    }

    public function conversations(Request $request)
    {
        $me = $this->me($request);
        $general = ChatConversation::general($me->foundation_id);
        $this->memberRow($me, $general);

        $directs = ChatConversation::whereIn('type', ['direct', 'group'])
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
                'id' => $c->id, 'type' => $c->type, 'title' => $c->type === 'general' ? 'General' : ($c->type === 'group' ? $c->title : ($other['name'] ?? 'Chat')), 'other' => $other,
                'cover' => $c->cover_v, 'theme' => $c->theme,
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
        $conv = ChatConversation::whereIn('type', ['direct', 'group'])->whereIn('id', ChatMember::where('user_id', $me->id)->pluck('conversation_id'))->pluck('id')->push($ids);
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
            'conversation' => ['id' => $conversation->id, 'type' => $conversation->type, 'title' => $conversation->type === 'general' ? 'General' : ($conversation->type === 'group' ? $conversation->title : ($other['name'] ?? 'Chat')), 'other' => $other,
                'cover' => $conversation->cover_v, 'theme' => $conversation->theme, 'can_manage' => $this->mayManage($me, $conversation), 'is_group' => in_array($conversation->type, ['general', 'group'], true)],
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

    /** Sound files a phone or computer records (MediaRecorder): WebM/Ogg (Chrome, Firefox, Android), MP4/AAC (iPhone). */
    private const AUDIO = ['audio/webm' => 'webm', 'video/webm' => 'webm', 'audio/ogg' => 'ogg', 'application/ogg' => 'ogg', 'audio/mp4' => 'm4a', 'video/mp4' => 'm4a', 'audio/x-m4a' => 'm4a', 'audio/aac' => 'aac', 'audio/mpeg' => 'mp3', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav'];

    /** POST chat/conversations/{c}/files - a photo (kind=image) or a voice message (kind=audio), with optional text. */
    public function sendFile(Request $request, ChatConversation $conversation)
    {
        $me = $this->me($request);
        if (! $this->mayUse($me, $conversation)) {
            return ApiResponse::forbidden();
        }
        $data = $request->validate([
            'kind' => ['required', 'in:image,audio'],
            'file' => ['required', 'file', 'max:'.($request->input('kind') === 'audio' ? 3072 : 4096)],
            'body' => ['nullable', 'string', 'max:2000'],
            'ms' => ['nullable', 'integer', 'min:0', 'max:130000'],
        ]);
        $dir = 'chat/'.$me->foundation_id.'/'.$conversation->id;
        if ($data['kind'] === 'image') {
            $saved = \App\Support\PrivateImages::store($request->file('file'), $dir, 1600, 4096);   // decoded and saved again: nothing hidden in the file survives
            if (is_string($saved)) {
                return ApiResponse::error('VALIDATION_ERROR', $saved, 422, ['file' => [$saved]]);
            }
            $file = ['file_kind' => 'image', 'file_path' => $saved['path'], 'file_mime' => 'image/jpeg', 'file_size' => strlen((string) Storage::disk('local')->get($saved['path']))];
        } else {
            $up = $request->file('file');
            $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($up->getRealPath());
            if (! isset(self::AUDIO[$mime])) {
                return ApiResponse::error('VALIDATION_ERROR', 'This is not a voice recording.', 422, ['file' => ['This is not a voice recording.']]);
            }
            $path = $dir.'/'.bin2hex(random_bytes(16)).'.'.self::AUDIO[$mime];
            if (! Storage::disk('local')->put($path, (string) file_get_contents($up->getRealPath()))) {
                return ApiResponse::error('SAVE_FAILED', 'The recording could not be saved.', 500);
            }
            $file = ['file_kind' => 'audio', 'file_path' => $path, 'file_mime' => str_starts_with($mime, 'video/') ? 'audio/'.substr($mime, 6) : $mime, 'file_size' => $up->getSize(), 'file_ms' => min(120000, (int) ($data['ms'] ?? 0)) ?: null];
        }
        $body = trim((string) ($data['body'] ?? ''));
        $m = ChatMessage::create(['foundation_id' => $me->foundation_id, 'conversation_id' => $conversation->id, 'user_id' => $me->id, 'body' => $body === '' ? null : $body, 'created_at' => now()] + $file);
        $conversation->forceFill(['last_message_at' => $m->created_at])->save();
        $this->memberRow($me, $conversation)->update(['last_read_id' => $m->id]);

        return ApiResponse::created($this->messageOut($m->load('user'), $me));
    }

    /** GET chat/conversations/{c}/messages/{m}/file - only for the chat's members. */
    public function file(Request $request, ChatConversation $conversation, int $message)
    {
        $me = $this->me($request);
        if (! $this->mayUse($me, $conversation)) {
            return ApiResponse::forbidden();
        }
        $m = ChatMessage::where('conversation_id', $conversation->id)->where('id', $message)->first();
        if (! $m || ! $m->file_path || ! Storage::disk('local')->exists($m->file_path)) {
            return ApiResponse::notFound('No such file.');
        }

        return response(Storage::disk('local')->get($m->file_path), 200, ['Content-Type' => $m->file_mime ?: 'application/octet-stream', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** POST chat/groups - anyone who can chat makes a group and chooses who is in it. */
    public function createGroup(Request $request)
    {
        $me = $this->me($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:60'],
            'user_ids' => ['required', 'array', 'min:1', 'max:200'],
            'user_ids.*' => ['integer'],
            'theme' => ['nullable', 'in:'.implode(',', self::THEMES)],
        ]);
        $allowed = $this->chatPeople($me)->pluck('id')->all();
        $chosen = array_values(array_intersect(array_map('intval', $data['user_ids']), $allowed));
        if (! $chosen) {
            return ApiResponse::error('VALIDATION_ERROR', 'Choose at least one person for the group.', 422, ['user_ids' => ['Choose at least one person for the group.']]);
        }
        $c = ChatConversation::create(['foundation_id' => $me->foundation_id, 'type' => 'group', 'pair_key' => 'group-'.\Illuminate\Support\Str::random(14), 'title' => trim($data['title']), 'created_by' => $me->id, 'theme' => $data['theme'] ?? null]);
        $this->memberRow($me, $c);
        foreach ($chosen as $uid) {
            ChatMember::firstOrCreate(['conversation_id' => $c->id, 'user_id' => $uid]);
        }
        foreach ($chosen as $uid) {
            \App\Support\Notify::to($uid, $me->foundation_id, 'chat', 'You were added to a group', $c->title, 'room.html?c='.$c->id, 'grp:'.$c->id.':'.$uid);
        }
        Audit::record('chat.group_created', $request, null, null, ['conversation' => $c->id, 'members' => count($chosen) + 1]);

        return ApiResponse::created(['id' => $c->id, 'title' => $c->title]);
    }

    /** GET chat/conversations/{id}/info - the settings page: name, theme, cover, members and what this person may change. */
    public function info(Request $request, ChatConversation $conversation)
    {
        $me = $this->me($request);
        if (! $this->maySee($me, $conversation) || $conversation->type === 'direct') {
            return ApiResponse::forbidden();
        }
        $people = $conversation->type === 'general'
            ? $this->chatPeople($me)->prepend($me)
            : User::whereIn('id', ChatMember::where('conversation_id', $conversation->id)->pluck('user_id'))->orderBy('name')->get();

        return ApiResponse::ok([
            'id' => $conversation->id, 'type' => $conversation->type, 'title' => $conversation->type === 'general' ? 'General' : $conversation->title,
            'theme' => $conversation->theme, 'cover' => $conversation->cover_v, 'themes' => self::THEMES, 'can_manage' => $this->mayManage($me, $conversation),
            'owner_id' => $conversation->created_by, 'me' => $me->id,
            'members' => $people->map(fn (User $u) => $this->personOut($u) + ['owner' => (int) $conversation->created_by === $u->id])->values(),
        ]);
    }

    /** PATCH chat/conversations/{id} - rename (groups) and change the theme. */
    public function update(Request $request, ChatConversation $conversation)
    {
        $me = $this->me($request);
        if (! $this->maySee($me, $conversation) || $conversation->type === 'direct' || ! $this->mayManage($me, $conversation)) {
            return ApiResponse::forbidden();
        }
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'min:2', 'max:60'],
            'theme' => ['sometimes', 'nullable', 'in:'.implode(',', self::THEMES)],
        ]);
        if (isset($data['title']) && $conversation->type === 'group') {
            $conversation->title = trim($data['title']);
        }
        if (array_key_exists('theme', $data)) {
            $conversation->theme = $data['theme'];
        }
        $conversation->save();
        Audit::record('chat.group_updated', $request, null, null, ['conversation' => $conversation->id, 'fields' => array_keys($data)]);

        return ApiResponse::updated(['id' => $conversation->id, 'title' => $conversation->type === 'general' ? 'General' : $conversation->title, 'theme' => $conversation->theme]);
    }

    public function cover(Request $request, ChatConversation $conversation)
    {
        if (! $this->maySee($this->me($request), $conversation) || ! $conversation->cover_path) {
            return ApiResponse::notFound('No cover.');
        }

        return ApplicationController::file($conversation->cover_path, 'image/jpeg');
    }

    public function setCover(Request $request, ChatConversation $conversation)
    {
        $me = $this->me($request);
        if (! $this->maySee($me, $conversation) || $conversation->type === 'direct' || ! $this->mayManage($me, $conversation)) {
            return ApiResponse::forbidden();
        }
        $request->validate(['photo' => ['required', 'file', 'max:6144', 'mimes:jpg,jpeg,png,webp']]);
        $saved = \App\Support\PrivateImages::store($request->file('photo'), "chat/{$conversation->foundation_id}/{$conversation->id}", 1400, 6144);
        if (is_string($saved)) {
            return ApiResponse::error('VALIDATION_ERROR', $saved, 422, ['photo' => [$saved]]);
        }
        if ($conversation->cover_path) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($conversation->cover_path);
        }
        $conversation->forceFill(['cover_path' => $saved['path'], 'cover_v' => time()])->save();
        Audit::record('chat.cover_set', $request, null, null, ['conversation' => $conversation->id]);

        return ApiResponse::ok(['cover' => $conversation->cover_v]);
    }

    public function removeCover(Request $request, ChatConversation $conversation)
    {
        $me = $this->me($request);
        if (! $this->maySee($me, $conversation) || $conversation->type === 'direct' || ! $this->mayManage($me, $conversation)) {
            return ApiResponse::forbidden();
        }
        if ($conversation->cover_path) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($conversation->cover_path);
        }
        $conversation->forceFill(['cover_path' => null, 'cover_v' => null])->save();

        return ApiResponse::deleted('Cover removed.');
    }

    /** POST chat/conversations/{id}/members - add people to a group. */
    public function addMembers(Request $request, ChatConversation $conversation)
    {
        $me = $this->me($request);
        if ($conversation->type !== 'group' || ! $this->maySee($me, $conversation) || ! $this->mayManage($me, $conversation)) {
            return ApiResponse::forbidden();
        }
        $data = $request->validate(['user_ids' => ['required', 'array', 'min:1', 'max:200'], 'user_ids.*' => ['integer']]);
        $allowed = $this->chatPeople($me)->pluck('id')->all();
        $n = 0;
        foreach (array_intersect(array_map('intval', $data['user_ids']), $allowed) as $uid) {
            $m = ChatMember::firstOrCreate(['conversation_id' => $conversation->id, 'user_id' => $uid]);
            $n += $m->wasRecentlyCreated ? 1 : 0;
            if ($m->wasRecentlyCreated) {
                \App\Support\Notify::to($uid, $me->foundation_id, 'chat', 'You were added to a group', $conversation->title, 'room.html?c='.$conversation->id, 'grp:'.$conversation->id.':'.$uid);
            }
        }
        Audit::record('chat.members_added', $request, null, null, ['conversation' => $conversation->id, 'added' => $n]);

        return ApiResponse::ok(['added' => $n]);
    }

    /** DELETE chat/conversations/{id}/members/{user} - remove someone, or leave yourself. */
    public function removeMember(Request $request, ChatConversation $conversation, int $user)
    {
        $me = $this->me($request);
        if ($conversation->type !== 'group' || ! $this->maySee($me, $conversation)) {
            return ApiResponse::forbidden();
        }
        if ($user !== $me->id && (! $this->mayManage($me, $conversation) || (int) $conversation->created_by === $user)) {
            return ApiResponse::forbidden();
        }
        ChatMember::where('conversation_id', $conversation->id)->where('user_id', $user)->delete();
        Audit::record($user === $me->id ? 'chat.group_left' : 'chat.member_removed', $request, null, null, ['conversation' => $conversation->id, 'user' => $user]);

        return ApiResponse::deleted($user === $me->id ? 'You left the group.' : 'Removed.');
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
