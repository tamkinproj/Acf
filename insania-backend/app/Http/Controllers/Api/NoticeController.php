<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use App\Support\ApiResponse;
use App\Support\Notify;
use App\Support\PermissionCatalog;
use Illuminate\Http\Request;

/** The bell: every signed-in person has one. Unread chat messages are listed there too (they are already counted by the chat). */
class NoticeController extends Controller
{
    private function chatRows(Request $request): array
    {
        if (! PermissionCatalog::allows($request->user(), 'chat.use')) {
            return [];
        }
        $rows = app(ChatController::class)->conversations($request)->getData(true)['data'] ?? [];

        return collect($rows)->filter(fn ($c) => ($c['unread'] ?? 0) > 0)->map(fn ($c) => [
            'kind' => 'chat', 'id' => 'c'.$c['id'], 'type' => 'chat', 'unread' => true, 'at' => $c['last_at'], 'count' => $c['unread'],
            'title' => $c['type'] === 'general' ? 'General' : $c['title'], 'body' => ($c['last_by'] ? $c['last_by'].': ' : '').($c['last'] ?? ''), 'url' => 'room.html?c='.$c['id'],
        ])->values()->all();
    }

    /** GET notices/count - cheap: the app asks every few seconds. */
    public function count(Request $request)
    {
        $user = $request->user();
        Notify::sweepEducation($user);
        $stored = Notice::where('user_id', $user->id)->whereNull('read_at')->count();
        $chat = array_sum(array_column($this->chatRows($request), 'count'));

        return ApiResponse::ok(['notices' => $stored, 'chat' => $chat, 'total' => $stored + $chat]);
    }

    /** GET notices - the list. */
    public function index(Request $request)
    {
        $user = $request->user();
        Notify::sweepEducation($user);
        $stored = Notice::where('user_id', $user->id)->orderByDesc('id')->limit(60)->get()->map(fn (Notice $n) => [
            'kind' => 'notice', 'id' => $n->id, 'type' => $n->type, 'title' => $n->title, 'body' => $n->body, 'url' => $n->url, 'unread' => $n->read_at === null, 'at' => $n->created_at?->toIso8601String(), 'count' => 1,
        ])->all();
        $items = collect(array_merge($this->chatRows($request), $stored))->sortByDesc(fn ($r) => strtotime((string) $r['at']))->values();

        return ApiResponse::ok(['items' => $items, 'unread' => $items->where('unread', true)->sum('count')]);
    }

    /** POST notices/read - everything, or the given ids. */
    public function read(Request $request)
    {
        $data = $request->validate(['ids' => ['nullable', 'array', 'max:200'], 'ids.*' => ['integer']]);
        $q = Notice::where('user_id', $request->user()->id)->whereNull('read_at');
        if (! empty($data['ids'])) {
            $q->whereIn('id', $data['ids']);
        }
        $q->update(['read_at' => now()]);

        return ApiResponse::ok(['read' => true]);
    }
}
