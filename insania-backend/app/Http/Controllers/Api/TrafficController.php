<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Cloudflare;
use App\Services\CloudflareTraffic;
use App\Services\Traffic;
use App\Support\ApiResponse;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The System Admin's Security page: who reaches the server (addresses, countries, pages, sign-ins) and the block list.
 * Blocking is by hand only. Nothing here is shown to the office (permission system.traffic is the System Admin's alone).
 */
class TrafficController extends Controller
{
    private const RANGES = ['today', '7d', '30d'];

    private function from(?string $range): Carbon
    {
        return match ($range) {
            '7d' => now()->subDays(6)->startOfDay(),
            '30d' => now()->subDays(29)->startOfDay(),
            default => now()->startOfDay(),
        };
    }

    private function range(Request $request): string
    {
        $r = (string) $request->query('range', 'today');

        return in_array($r, self::RANGES, true) ? $r : 'today';
    }

    /** GET system/traffic?range=today|7d|30d : the overview */
    public function overview(Request $request)
    {
        $range = $this->range($request);
        $from = $this->from($range);
        $f = $from->format('Y-m-d H:i:s');
        $hits = fn () => DB::table('traffic_hits')->where('hour', '>=', $f);

        $t = $hits()->selectRaw('coalesce(sum(requests),0) r, coalesce(sum(signins),0) s, coalesce(sum(failed_logins),0) fl, coalesce(sum(blocked),0) b, coalesce(sum(throttled),0) th, count(distinct ip) v')->first();
        $countries = (int) $hits()->whereNotNull('country')->distinct()->count('country');

        // requests per hour (today) or per day (7 and 30 days), with no gaps
        $rows = $hits()->selectRaw('hour, sum(requests) r, sum(blocked) b')->groupBy('hour')->get();
        $daily = $range !== 'today';
        $series = [];
        $cur = $from->copy();
        $end = $daily ? now()->startOfDay() : now()->startOfHour();
        while ($cur <= $end) {
            $series[$cur->format($daily ? 'Y-m-d' : 'Y-m-d H')] = ['t' => $cur->toIso8601String(), 'requests' => 0, 'blocked' => 0];
            $daily ? $cur->addDay() : $cur->addHour();
        }
        foreach ($rows as $r) {
            $k = Carbon::parse($r->hour)->format($daily ? 'Y-m-d' : 'Y-m-d H');
            if (isset($series[$k])) {
                $series[$k]['requests'] += (int) $r->r;
                $series[$k]['blocked'] += (int) $r->b;
            }
        }

        $byCountry = $hits()->selectRaw('country, sum(requests) r, count(distinct ip) v')->groupBy('country')->orderByDesc('r')->limit(20)->get()
            ->map(fn ($r) => ['code' => $r->country, 'requests' => (int) $r->r, 'visitors' => (int) $r->v])->values();
        $paths = DB::table('traffic_paths')->where('hour', '>=', $f)->selectRaw('path, sum(requests) r')->groupBy('path')->orderByDesc('r')->limit(10)->get()
            ->map(fn ($r) => ['path' => $r->path, 'requests' => (int) $r->r])->values();
        $events = DB::table('traffic_events')->where('at', '>=', $f)->orderByDesc('id')->limit(20)->get()->map(fn ($e) => $this->eventOut($e))->values();
        $blocks = Traffic::blocks();
        $active = array_filter($blocks, fn ($until) => $until === null || $until > now()->getTimestamp());

        return ApiResponse::ok([
            'range' => $range, 'from' => $from->toIso8601String(), 'settings' => Traffic::settings(),
            'totals' => ['requests' => (int) $t->r, 'visitors' => (int) $t->v, 'countries' => $countries, 'signins' => (int) $t->s, 'failed' => (int) $t->fl, 'blocked' => (int) $t->b, 'throttled' => (int) $t->th],
            'series' => array_values($series), 'daily' => $daily, 'countries' => $byCountry, 'paths' => $paths,
            'visitors' => $this->visitorRows($f, null, 10)['rows'], 'events' => $events, 'blocked_now' => count($active),
            'my_ip' => Traffic::canon((string) $request->ip()), 'via_cloudflare' => (bool) $request->attributes->get('via_cloudflare'),
            'cloudflare' => Cloudflare::saved() !== null,
        ]);
    }

    /** GET system/traffic/cloudflare?range=&fresh=1 : Cloudflare's own figures (kept five minutes; fresh=1 asks again) */
    public function cloudflare(Request $request)
    {
        $cf = Cloudflare::saved();
        if (! $cf) {
            return ApiResponse::ok(['connected' => false]);
        }
        $range = $this->range($request);

        return ApiResponse::ok(CloudflareTraffic::overview($cf, $range, $this->from($range), $request->boolean('fresh')));
    }

    /** GET system/traffic/visitors?range=&q=&page= : every address, most requests first */
    public function visitors(Request $request)
    {
        $request->validate(['q' => ['nullable', 'string', 'max:45'], 'page' => ['nullable', 'integer', 'min:1']]);
        $f = $this->from($this->range($request))->format('Y-m-d H:i:s');
        $all = $this->visitorRows($f, trim((string) $request->query('q', '')), 5000)['rows'];
        $page = (int) $request->query('page', 1);
        $p = new LengthAwarePaginator(array_slice($all, ($page - 1) * 50, 50), count($all), 50, $page);

        return ApiResponse::collection($p);
    }

    private function visitorRows(string $from, ?string $q, int $limit): array
    {
        $query = DB::table('traffic_hits')->where('hour', '>=', $from)
            ->selectRaw('ip, max(country) country, sum(requests) r, sum(signins) s, sum(failed_logins) fl, sum(blocked) b, sum(throttled) th, min(first_at) first_at, max(last_at) last_at')
            ->groupBy('ip')->orderByDesc('r')->limit($limit);
        if ($q !== null && $q !== '') {
            $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';
            $query->where(fn ($w) => $w->where('ip', 'like', $like)->orWhere('country', strtoupper(substr($q, 0, 2))));
        }
        $blocks = Traffic::blocks();
        $now = now()->getTimestamp();

        return ['rows' => $query->get()->map(fn ($r) => [
            'ip' => $r->ip, 'country' => $r->country, 'requests' => (int) $r->r, 'signins' => (int) $r->s, 'failed' => (int) $r->fl, 'blocked_requests' => (int) $r->b, 'throttled' => (int) $r->th,
            'first_at' => Carbon::parse($r->first_at)->toIso8601String(), 'last_at' => Carbon::parse($r->last_at)->toIso8601String(),
            'is_blocked' => array_key_exists($r->ip, $blocks) && ($blocks[$r->ip] === null || $blocks[$r->ip] > $now),
        ])->values()->all()];
    }

    /** GET system/traffic/address?ip= : one address in detail */
    public function address(Request $request)
    {
        $request->validate(['ip' => ['required', 'ip']]);
        $ip = Traffic::canon((string) $request->query('ip'));
        $hits = fn () => DB::table('traffic_hits')->where('ip', $ip);
        $sum = $hits()->selectRaw('coalesce(sum(requests),0) r, coalesce(sum(signins),0) s, coalesce(sum(failed_logins),0) fl, coalesce(sum(blocked),0) b, coalesce(sum(throttled),0) th, min(first_at) first_at, max(last_at) last_at')->first();
        $block = DB::table('blocked_ips')->where('ip', $ip)->first();

        return ApiResponse::ok([
            'ip' => $ip, 'is_me' => $ip === Traffic::canon((string) $request->ip()), 'cloudflare' => Traffic::isCloudflare($ip),
            'requests' => (int) $sum->r, 'signins' => (int) $sum->s, 'failed' => (int) $sum->fl, 'blocked_requests' => (int) $sum->b, 'throttled' => (int) $sum->th,
            'first_at' => $sum->first_at ? Carbon::parse($sum->first_at)->toIso8601String() : null, 'last_at' => $sum->last_at ? Carbon::parse($sum->last_at)->toIso8601String() : null,
            'countries' => $hits()->whereNotNull('country')->distinct()->pluck('country')->values(),
            'devices' => $hits()->whereNotNull('user_agent')->orderByDesc('last_at')->limit(40)->pluck('user_agent')->unique()->take(5)->values(),
            'accounts_7d' => $this->accountsUsing($ip),
            'block' => $block ? $this->blockOut($block) : null,
            'events' => DB::table('traffic_events')->where('ip', $ip)->orderByDesc('id')->limit(50)->get()->map(fn ($e) => $this->eventOut($e))->values(),
        ]);
    }

    /** How many signed-in accounts used this address in the last 7 days (an office's shared address, a family's phone...). Counts only, no names. */
    private function accountsUsing(string $ip): int
    {
        return (int) DB::table('traffic_hits')->where('ip', $ip)->whereNotNull('user_id')->where('hour', '>=', now()->subDays(7)->format('Y-m-d H:i:s'))->distinct()->count('user_id');
    }

    /** GET system/traffic/events?range=&kind=&page= */
    public function events(Request $request)
    {
        $request->validate(['kind' => ['nullable', 'in:'.implode(',', Traffic::KINDS)], 'page' => ['nullable', 'integer', 'min:1']]);
        $f = $this->from($this->range($request))->format('Y-m-d H:i:s');
        $p = DB::table('traffic_events')->where('at', '>=', $f)->when($request->query('kind'), fn ($q, $k) => $q->where('kind', $k))
            ->orderByDesc('id')->paginate(50);
        $p->setCollection($p->getCollection()->map(fn ($e) => $this->eventOut($e)));

        return ApiResponse::collection($p);
    }

    private function eventOut(object $e): array
    {
        return ['id' => (int) $e->id, 'at' => Carbon::parse($e->at)->toIso8601String(), 'ip' => $e->ip, 'country' => $e->country, 'kind' => $e->kind, 'method' => $e->method,
            'path' => $e->path, 'status' => (int) $e->status, 'user_agent' => $e->user_agent, 'signed_in' => $e->user_id !== null];
    }

    /** GET system/traffic/blocks */
    public function blocks()
    {
        return ApiResponse::ok(['blocks' => $this->blockList()]);
    }

    private function blockList(): array
    {
        return DB::table('blocked_ips')->orderByDesc('id')->get()->map(fn ($b) => $this->blockOut($b))->values()->all();
    }

    private function blockOut(object $b): array
    {
        $until = $b->expires_at ? Carbon::parse($b->expires_at) : null;

        return ['id' => (int) $b->id, 'ip' => $b->ip, 'reason' => $b->reason, 'expires_at' => $until?->toIso8601String(), 'active' => (! $until || $until->isFuture()) && $b->cf_status !== 'removing',
            'cloudflare' => $b->cf_status ? ['status' => $b->cf_status, 'error' => $b->cf_error] : null,
            'by' => $b->created_by ? User::withoutGlobalScopes()->whereKey($b->created_by)->value('name') : null, 'at' => Carbon::parse($b->created_at)->toIso8601String()];
    }

    /** POST system/traffic/blocks {ip, reason, days (empty: until unblocked), confirm} : block an address by hand */
    public function block(Request $request)
    {
        $data = $request->validate([
            'ip' => ['required', 'ip'], 'reason' => ['nullable', 'string', 'max:200'], 'days' => ['nullable', 'integer', 'in:1,7,30,90'], 'confirm' => ['nullable', 'boolean'],
        ]);
        $ip = Traffic::canon($data['ip']);
        if ($ip === Traffic::canon((string) $request->ip())) {
            return ApiResponse::error('OWN_ADDRESS', 'This is the address you are using now. Blocking it would lock you out.', 422, ['ip' => ['This is the address you are using now.']]);
        }
        if (Traffic::isCloudflare($ip)) {
            return ApiResponse::error('CLOUDFLARE_ADDRESS', 'This is one of Cloudflare\'s own addresses: blocking it would block many visitors at once.', 422, ['ip' => ['This is a Cloudflare address.']]);
        }
        $used = $this->accountsUsing($ip);
        if ($used > 0 && empty($data['confirm'])) {
            return ApiResponse::error('IP_IN_USE', "$used signed-in ".($used === 1 ? 'account' : 'accounts').' used this address in the last 7 days (it may be the office\'s shared address or a family\'s phone). Blocking it locks them out too.', 409);   // no field error: the app shows this whole sentence
        }
        $until = ! empty($data['days']) ? now()->addDays((int) $data['days']) : null;
        $now = now()->format('Y-m-d H:i:s');
        $prev = DB::table('blocked_ips')->where('ip', $ip)->first();
        DB::table('blocked_ips')->updateOrInsert(['ip' => $ip], ['reason' => ($data['reason'] ?? '') !== '' ? $data['reason'] : null, 'expires_at' => $until?->format('Y-m-d H:i:s'),
            'created_by' => $request->user()->id, 'created_at' => $now, 'updated_at' => $now] + ($prev && $prev->cf_status === 'removing' ? ['cf_status' => 'ok', 'cf_error' => null] : []));
        Traffic::forgetBlocks();
        $cf = $this->toCloudflare((int) DB::table('blocked_ips')->where('ip', $ip)->value('id'));
        Audit::record('traffic.blocked', $request, null, null, ['ip' => $ip, 'days' => $data['days'] ?? null, 'accounts' => $used, 'cloudflare' => $cf]);

        return ApiResponse::created(['blocks' => $this->blockList()]);
    }

    /** Makes (or confirms) the block at Cloudflare. Returns ok, failed, or off (Cloudflare not connected). The app's own block works either way. */
    private function toCloudflare(int $id): string
    {
        $b = DB::table('blocked_ips')->where('id', $id)->first();
        $cf = Cloudflare::saved();
        if (! $b || ! $cf) {
            return 'off';
        }
        if ($b->cf_rule_id && $b->cf_status === 'ok') {
            return 'ok';
        }
        $r = $cf->blockIp($b->ip, 'Al Insania app: blocked by the System Admin'.($b->reason ? ' ('.$b->reason.')' : ''));
        DB::table('blocked_ips')->where('id', $id)->update($r['ok'] ? ['cf_rule_id' => $r['id'], 'cf_status' => 'ok', 'cf_error' => null] : ['cf_status' => 'failed', 'cf_error' => mb_substr((string) $r['error'], 0, 200)]);

        return $r['ok'] ? 'ok' : 'failed';
    }

    /** POST system/traffic/blocks/{id}/cloudflare : try Cloudflare again for a block it refused */
    public function retry(Request $request, int $id)
    {
        if (! DB::table('blocked_ips')->where('id', $id)->exists()) {
            return ApiResponse::notFound('No such block.');
        }
        $res = $this->toCloudflare($id);
        if ($res === 'failed') {
            return ApiResponse::error('CLOUDFLARE_REFUSED', (string) DB::table('blocked_ips')->where('id', $id)->value('cf_error'), 422);
        }

        return ApiResponse::ok(['blocks' => $this->blockList()]);
    }

    /** DELETE system/traffic/blocks/{id} */
    public function unblock(Request $request, int $id)
    {
        $b = DB::table('blocked_ips')->where('id', $id)->first();
        if (! $b) {
            return ApiResponse::notFound('No such block.');
        }
        $warning = null;
        if ($b->cf_rule_id && ($cf = Cloudflare::saved())) {
            $r = $cf->unblockIp($b->cf_rule_id);
            if (! $r['ok']) {
                // the app lets the address in now; Cloudflare is asked again every ten minutes until it is gone there too
                DB::table('blocked_ips')->where('id', $id)->update(['expires_at' => now()->format('Y-m-d H:i:s'), 'cf_status' => 'removing', 'cf_error' => mb_substr((string) $r['error'], 0, 200), 'updated_at' => now()]);
                $warning = 'Unblocked in the app, but Cloudflare did not remove it yet ('.$r['error'].'). The app will keep trying, or remove it in Cloudflare (Security, WAF, Tools).';
            }
        }
        if (! $warning) {
            DB::table('blocked_ips')->where('id', $id)->delete();
        }
        Traffic::forgetBlocks();
        Audit::record('traffic.unblocked', $request, null, null, ['ip' => $b->ip, 'cloudflare' => $b->cf_rule_id ? ($warning ? 'pending' : 'removed') : 'off']);

        return ApiResponse::ok(['blocks' => $this->blockList(), 'warning' => $warning]);
    }

    /** PATCH system/traffic/settings {logging, blocking, retention_days} */
    public function settings(Request $request)
    {
        $data = $request->validate([
            'logging' => ['sometimes', 'boolean'], 'blocking' => ['sometimes', 'boolean'],
            'retention_days' => ['sometimes', 'integer', 'min:'.Traffic::RETENTION[0], 'max:'.Traffic::RETENTION[1]],
            'alerts' => ['sometimes', 'boolean'],
            'alert_failed' => ['sometimes', 'integer', 'in:'.implode(',', Traffic::ALERT_FAILED)],
            'alert_refused' => ['sometimes', 'integer', 'in:'.implode(',', Traffic::ALERT_REFUSED)],
        ]);
        $before = Traffic::settings();
        $after = Traffic::saveSettings((int) $request->user()->foundation_id, $data);
        Audit::record('traffic.settings', $request, null, null, ['before' => $before, 'after' => $after]);

        return ApiResponse::ok(['settings' => $after]);
    }
}
