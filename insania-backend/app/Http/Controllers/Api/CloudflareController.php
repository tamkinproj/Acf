<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Cloudflare;
use App\Support\ApiResponse;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/** The System Admin's Cloudflare switches: pause, security level, bot fight mode, leaked credentials, and the zone's rules. */
class CloudflareController extends Controller
{
    private function status(Cloudflare $cf): array
    {
        $errors = [];
        $zone = $cf->call('GET', $cf->z());
        $level = $cf->call('GET', $cf->z('settings/security_level'));
        $bot = $cf->call('GET', $cf->z('bot_management'));
        $leak = $cf->call('GET', $cf->z('leaked-credential-checks'));
        foreach (['zone' => $zone, 'security_level' => $level, 'bot_fight' => $bot, 'leaked' => $leak] as $k => $r) {
            if (! $r['ok']) {
                $errors[$k] = $r['error'];
            }
        }
        $rules = $cf->rules();

        return [
            'connected' => true,
            'zone' => $zone['ok'] ? ['id' => $cf->zone, 'name' => $zone['result']['name'] ?? '', 'paused' => (bool) ($zone['result']['paused'] ?? false), 'status' => $zone['result']['status'] ?? ''] : null,
            'security_level' => $level['ok'] ? ($level['result']['value'] ?? null) : null,
            'bot_fight' => $bot['ok'] ? (bool) ($bot['result']['fight_mode'] ?? false) : null,
            'leaked' => $leak['ok'] ? (bool) ($leak['result']['enabled'] ?? false) : null,
            'rules' => $rules['rules'],
            'errors' => $errors + array_map(fn ($e) => $e, $rules['errors']),
        ];
    }

    public function show()
    {
        $cf = Cloudflare::saved();

        return ApiResponse::ok($cf ? $this->status($cf) : ['connected' => false]);
    }

    public function connect(Request $request)
    {
        $data = $request->validate([
            'api_token' => ['required', 'string', 'min:20', 'max:300', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'zone_id' => ['nullable', 'string', 'regex:/^[a-f0-9]{32}$/'],
            'admin_password' => ['required', 'string', 'max:200'],
        ]);
        if (! Hash::check($data['admin_password'], $request->user()->password)) {
            Audit::record('cloudflare.connect_denied', $request);

            return ApiResponse::error('WRONG_PASSWORD', 'Your own password is not right.', 422, ['admin_password' => ['Your own password is not right.']]);
        }

        $probe = Cloudflare::withToken($data['api_token']);
        if (! empty($data['zone_id'])) {
            $z = $probe->call('GET', 'zones/'.$data['zone_id']);
            $zone = $z['ok'] ? ['id' => $data['zone_id'], 'name' => $z['result']['name'] ?? ''] : null;
            $why = $z['error'];
        } else {
            $zone = $probe->findZone($request->getHost());
            $why = 'No Cloudflare zone was found for '.$request->getHost().'. Check that the token may read this zone.';
        }
        if (! $zone) {
            return ApiResponse::error('CLOUDFLARE_REFUSED', $why ?: 'Cloudflare did not accept this token.', 422, ['api_token' => [$why ?: 'Cloudflare did not accept this token.']]);
        }

        Cloudflare::save((int) $request->user()->foundation_id, $data['api_token'], $zone['id'], $zone['name']);
        Audit::record('cloudflare.connected', $request, null, null, ['zone' => $zone['name']]);

        return ApiResponse::ok($this->status(Cloudflare::saved()));
    }

    public function update(Request $request)
    {
        $cf = Cloudflare::saved();
        if (! $cf) {
            return ApiResponse::error('NOT_CONNECTED', 'Connect Cloudflare first.', 422);
        }
        $d = $request->validate([
            'paused' => ['sometimes', 'boolean'],
            'security_level' => ['sometimes', 'in:'.implode(',', Cloudflare::LEVELS)],
            'bot_fight' => ['sometimes', 'boolean'],
            'leaked' => ['sometimes', 'boolean'],
        ]);
        $failed = [];
        if (array_key_exists('paused', $d)) {
            $r = $cf->call('PATCH', $cf->z(), ['paused' => (bool) $d['paused']]);
            $r['ok'] || $failed['paused'] = $r['error'];
        }
        if (isset($d['security_level'])) {
            $r = $cf->call('PATCH', $cf->z('settings/security_level'), ['value' => $d['security_level']]);
            $r['ok'] || $failed['security_level'] = $r['error'];
        }
        if (array_key_exists('bot_fight', $d)) {
            $r = $cf->call('PUT', $cf->z('bot_management'), ['fight_mode' => (bool) $d['bot_fight']]);
            $r['ok'] || $failed['bot_fight'] = $r['error'];
        }
        if (array_key_exists('leaked', $d)) {
            $r = $cf->call('POST', $cf->z('leaked-credential-checks'), ['enabled' => (bool) $d['leaked']]);
            $r['ok'] || $failed['leaked'] = $r['error'];
        }
        Audit::record('cloudflare.changed', $request, null, null, ['set' => array_diff_key($d, $failed), 'failed' => array_keys($failed)]);
        if ($failed) {
            return ApiResponse::error('CLOUDFLARE_REFUSED', (string) array_values($failed)[0], 422, ['cloudflare' => array_values($failed)]);
        }

        return ApiResponse::ok($this->status($cf));
    }

    public function rule(Request $request, string $rule)
    {
        $cf = Cloudflare::saved();
        if (! $cf) {
            return ApiResponse::error('NOT_CONNECTED', 'Connect Cloudflare first.', 422);
        }
        $d = $request->validate(['enabled' => ['required', 'boolean'], 'phase' => ['required', 'in:'.implode(',', array_keys(Cloudflare::PHASES))]]);
        if (! preg_match('/^[a-f0-9]{32}$/', $rule)) {
            return ApiResponse::notFound('No such rule.');
        }
        $set = $cf->call('GET', $cf->z('rulesets/phases/'.$d['phase'].'/entrypoint'));
        $cur = collect($set['result']['rules'] ?? [])->firstWhere('id', $rule);
        if (! $set['ok'] || ! $cur) {
            return ApiResponse::notFound('No such rule.');
        }
        // the rule is sent back whole with only its switch changed
        $body = array_intersect_key($cur, array_flip(['action', 'action_parameters', 'expression', 'description', 'ratelimit', 'logging']));
        $body['enabled'] = (bool) $d['enabled'];
        $r = $cf->call('PATCH', $cf->z('rulesets/'.$set['result']['id'].'/rules/'.$rule), $body);
        Audit::record('cloudflare.rule', $request, null, null, ['rule' => mb_substr((string) ($cur['description'] ?? $rule), 0, 80), 'enabled' => $body['enabled'], 'ok' => $r['ok']]);
        if (! $r['ok']) {
            return ApiResponse::error('CLOUDFLARE_REFUSED', (string) $r['error'], 422, ['cloudflare' => [$r['error']]]);
        }

        return ApiResponse::ok($this->status($cf));
    }

    public function disconnect(Request $request)
    {
        Cloudflare::forget((int) $request->user()->foundation_id);
        Audit::record('cloudflare.disconnected', $request);

        return ApiResponse::ok(['connected' => false]);
    }
}
