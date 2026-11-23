<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * Talks to Cloudflare's API for the website's zone, so the System Admin can switch the protections on and off from the app.
 * The API token is kept encrypted (with the app key) and is never sent back to the browser.
 */
final class Cloudflare
{
    public const BASE = 'https://api.cloudflare.com/client/v4';
    public const LEVELS = ['off', 'essentially_off', 'low', 'medium', 'high', 'under_attack'];
    /** phase => short name shown in the app */
    public const PHASES = ['http_ratelimit' => 'rate', 'http_request_firewall_custom' => 'custom'];

    public function __construct(private string $token, public readonly string $zone)
    {
    }

    /** Cloudflare's API address (config services.cloudflare.api only changes it for a test server). */
    public static function base(): string
    {
        return rtrim((string) (config('services.cloudflare.api') ?: self::BASE), '/');
    }

    /** The saved connection of this foundation, or null. */
    public static function saved(): ?self
    {
        $s = Setting::whereIn('key', ['cloudflare_token', 'cloudflare_zone'])->pluck('value', 'key');
        if (empty($s['cloudflare_token']) || empty($s['cloudflare_zone'])) {
            return null;
        }
        try {
            return new self(Crypt::decryptString($s['cloudflare_token']), $s['cloudflare_zone']);
        } catch (\Throwable) {
            return null;   // the app key changed: connect again
        }
    }

    public static function save(int $foundationId, string $token, string $zone, string $zoneName): void
    {
        foreach (['cloudflare_token' => Crypt::encryptString($token), 'cloudflare_zone' => $zone, 'cloudflare_zone_name' => $zoneName] as $k => $v) {
            Setting::withoutGlobalScopes()->updateOrCreate(['foundation_id' => $foundationId, 'key' => $k], ['value' => $v]);
        }
    }

    public static function forget(int $foundationId): void
    {
        Setting::withoutGlobalScopes()->where('foundation_id', $foundationId)->whereIn('key', ['cloudflare_token', 'cloudflare_zone', 'cloudflare_zone_name'])->delete();
    }

    public static function withToken(string $token): self
    {
        return new self($token, '');
    }

    /** @return array{ok: bool, result: mixed, error: ?string} */
    public function call(string $method, string $path, ?array $body = null): array
    {
        try {
            $r = Http::withToken($this->token)->acceptJson()->timeout(15)->baseUrl(self::base())->send($method, ltrim($path, '/'), $body === null ? [] : ['json' => $body]);
        } catch (\Throwable) {
            return ['ok' => false, 'result' => null, 'error' => 'Could not reach Cloudflare from this server.'];
        }
        $j = $r->json();
        if (! $r->successful() || ! is_array($j) || empty($j['success'])) {
            $msg = is_array($j) ? ($j['errors'][0]['message'] ?? null) : null;

            return ['ok' => false, 'result' => null, 'error' => $msg ?: 'Cloudflare answered with an error ('.$r->status().').'];
        }

        return ['ok' => true, 'result' => $j['result'] ?? null, 'error' => null];
    }

    public function z(string $path = ''): string
    {
        return 'zones/'.$this->zone.($path !== '' ? '/'.ltrim($path, '/') : '');
    }

    /** The Cloudflare account the website belongs to (needs Zone: Read). Remembered for a day. */
    public function accountId(): ?string
    {
        $id = \Illuminate\Support\Facades\Cache::get('cf-account:'.$this->zone);
        if (! $id) {
            $r = $this->call('GET', $this->z());
            $id = $r['ok'] ? ($r['result']['account']['id'] ?? null) : null;
            if ($id) {
                \Illuminate\Support\Facades\Cache::put('cf-account:'.$this->zone, $id, 86400);
            }
        }

        return $id ?: null;
    }

    /**
     * Blocks an address at Cloudflare (an IP Access rule: Account > Account Firewall Access Rules: Edit).
     * It applies to every website in the Cloudflare account. An address already blocked there is reused.
     *
     * @return array{ok: bool, id: ?string, error: ?string}
     */
    public function blockIp(string $ip, string $note): array
    {
        $acc = $this->accountId();
        if (! $acc) {
            return ['ok' => false, 'id' => null, 'error' => 'Cloudflare did not say which account the website is in (the token needs Zone: Read).'];
        }
        $target = str_contains($ip, ':') ? 'ip6' : 'ip';
        $r = $this->call('POST', "accounts/$acc/firewall/access_rules/rules", ['mode' => 'block', 'configuration' => ['target' => $target, 'value' => $ip], 'notes' => mb_substr($note, 0, 400)]);
        if ($r['ok'] && ! empty($r['result']['id'])) {
            return ['ok' => true, 'id' => (string) $r['result']['id'], 'error' => null];
        }
        // already there (made earlier, or by hand in Cloudflare): use that rule if it blocks
        $f = $this->call('GET', "accounts/$acc/firewall/access_rules/rules?configuration.target=$target&configuration.value=".rawurlencode($ip));
        foreach ((array) ($f['ok'] ? $f['result'] : []) as $rule) {
            if (($rule['mode'] ?? '') === 'block' && ! empty($rule['id'])) {
                return ['ok' => true, 'id' => (string) $rule['id'], 'error' => null];
            }
        }

        return ['ok' => false, 'id' => null, 'error' => $r['error'] ?: 'Cloudflare did not accept the block.'];
    }

    /** @return array{ok: bool, error: ?string} */
    public function unblockIp(string $ruleId): array
    {
        $acc = $this->accountId();
        if (! $acc) {
            return ['ok' => false, 'error' => 'Cloudflare did not say which account the website is in (the token needs Zone: Read).'];
        }
        $r = $this->call('DELETE', "accounts/$acc/firewall/access_rules/rules/".rawurlencode($ruleId));
        if (! $r['ok'] && preg_match('/not found|does not exist|could not find/i', (string) $r['error'])) {
            return ['ok' => true, 'error' => null];   // already gone
        }

        return ['ok' => $r['ok'], 'error' => $r['error']];
    }

    /** Cloudflare's GraphQL Analytics API (Zone > Analytics: Read). @return array{ok: bool, data: ?array, error: ?string} */
    public function graphql(string $query, array $variables): array
    {
        try {
            $r = Http::withToken($this->token)->acceptJson()->timeout(20)->post(self::base().'/graphql', ['query' => $query, 'variables' => $variables]);
        } catch (\Throwable) {
            return ['ok' => false, 'data' => null, 'error' => 'Could not reach Cloudflare from this server.'];
        }
        $j = $r->json();
        if (! is_array($j)) {
            return ['ok' => false, 'data' => null, 'error' => 'Cloudflare answered with an error ('.$r->status().').'];
        }
        if (! empty($j['errors'])) {
            return ['ok' => false, 'data' => $j['data'] ?? null, 'error' => (string) ($j['errors'][0]['message'] ?? 'Cloudflare refused this question.')];
        }

        return ['ok' => true, 'data' => $j['data'] ?? null, 'error' => null];
    }

    /** Finds the zone of a website address (tries the address, then its parent). @return array{id: string, name: string}|null */
    public function findZone(string $host): ?array
    {
        $parts = explode('.', strtolower($host));
        for ($i = 0; $i < count($parts) - 1; $i++) {
            $name = implode('.', array_slice($parts, $i));
            $r = $this->call('GET', 'zones?name='.urlencode($name));
            if ($r['ok'] && ! empty($r['result'][0]['id'])) {
                return ['id' => $r['result'][0]['id'], 'name' => $r['result'][0]['name']];
            }
        }

        // a token that can see exactly one zone: that is the website's
        $all = $this->call('GET', 'zones?per_page=2');
        if ($all['ok'] && is_array($all['result']) && count($all['result']) === 1 && ! empty($all['result'][0]['id'])) {
            return ['id' => $all['result'][0]['id'], 'name' => $all['result'][0]['name'] ?? ''];
        }

        return null;
    }

    /** Every rule of the rule-based protections we let the System Admin switch. */
    public function rules(): array
    {
        $out = ['rules' => [], 'errors' => []];
        foreach (self::PHASES as $phase => $kind) {
            $r = $this->call('GET', $this->z("rulesets/phases/$phase/entrypoint"));
            if (! $r['ok']) {
                if (! str_contains((string) $r['error'], 'could not find')) {
                    $out['errors'][$kind] = $r['error'];
                }
                continue;
            }
            foreach (($r['result']['rules'] ?? []) as $rule) {
                $out['rules'][] = ['id' => $rule['id'], 'ruleset' => $r['result']['id'], 'phase' => $phase, 'kind' => $kind, 'name' => $rule['description'] ?? '', 'action' => $rule['action'] ?? '', 'enabled' => (bool) ($rule['enabled'] ?? true)];
            }
        }

        return $out;
    }
}
