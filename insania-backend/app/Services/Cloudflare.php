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
            $r = Http::withToken($this->token)->acceptJson()->timeout(15)->baseUrl(self::BASE)->send($method, ltrim($path, '/'), $body === null ? [] : ['json' => $body]);
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
