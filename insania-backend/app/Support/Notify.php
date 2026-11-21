<?php

namespace App\Support;

use App\Models\Notice;
use App\Models\User;

/** Puts a message on someone's bell. Short texts only: never names, numbers or documents of the people we care for. */
final class Notify
{
    public static function to(int $userId, int $foundationId, string $type, string $title, ?string $body = null, ?string $url = null, ?string $key = null): void
    {
        $row = ['foundation_id' => $foundationId, 'user_id' => $userId, 'type' => $type, 'title' => mb_substr($title, 0, 150), 'body' => $body === null ? null : mb_substr($body, 0, 300), 'url' => $url, 'created_at' => now()];
        if ($key !== null) {
            Notice::firstOrCreate(['user_id' => $userId, 'dedupe_key' => $key], $row);

            return;
        }
        Notice::create($row);
    }

    /** Everyone in the foundation who holds the permission (except one person, such as the one who did the thing). */
    public static function toPermission(string $permission, int $foundationId, string $type, string $title, ?string $body = null, ?string $url = null, ?string $key = null, ?int $except = null): int
    {
        $n = 0;
        foreach (User::where('foundation_id', $foundationId)->where('active', true)->when($except, fn ($q) => $q->where('id', '!=', $except))->get() as $u) {
            if (PermissionCatalog::allows($u, $permission)) {
                self::to($u->id, $foundationId, $type, $title, $body, $url, $key === null ? null : $key);
                $n++;
            }
        }

        return $n;
    }

    /**
     * Contract warnings for the Education team: told once when a contract is within 30 days of its end, and once when it has ended.
     * Runs at most once an hour per person.
     */
    public static function sweepEducation(User $user): void
    {
        if (! PermissionCatalog::allows($user, 'education.view')) {
            return;
        }
        $lock = 'notice-sweep:'.$user->id;
        if (\Illuminate\Support\Facades\Cache::has($lock)) {
            return;
        }
        \Illuminate\Support\Facades\Cache::put($lock, 1, 3600);
        foreach (\App\Models\Education\Teacher::with('contracts')->where('status', 'active')->get() as $t) {
            $c = $t->current();
            if (! $c || $c->state() === 'active') {
                continue;
            }
            $state = $c->state();
            $title = $state === 'expired' ? 'Contract ended · '.$t->ref : 'Contract ending soon · '.$t->ref;
            $body = $state === 'expired' ? 'Ended on '.$c->end_date->toDateString().'. Renew it from the teacher\'s page.' : $c->daysLeft().' days left (ends '.$c->end_date->toDateString().').';
            self::to($user->id, $user->foundation_id, 'education', $title, $body, 'edu-teacher.html?id='.$t->id, 'edu:'.$c->id.':'.$state);
        }
    }
}
