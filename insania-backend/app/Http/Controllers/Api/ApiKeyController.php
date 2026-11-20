<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Support\ApiResponse;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Partner API keys without a terminal. Only the System Admin; the key is shown once and only its hash is stored. */
class ApiKeyController extends Controller
{
    private const ABILITIES = ['partner.summary'];

    private function row(ApiKey $k): array
    {
        return ['id' => $k->id, 'name' => $k->name, 'prefix' => $k->key_prefix, 'abilities' => $k->abilities,
            'expires_at' => $k->expires_at?->toIso8601String(), 'last_used_at' => $k->last_used_at?->toIso8601String(),
            'revoked' => (bool) $k->revoked_at, 'active' => $k->isActive()];
    }

    public function index(Request $request)
    {
        return ApiResponse::ok(ApiKey::where('foundation_id', $request->user()->foundation_id)->orderByDesc('id')->get()->map(fn ($k) => $this->row($k)));
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'abilities' => ['sometimes', 'array', 'min:1'],
            'abilities.*' => ['string', 'in:'.implode(',', self::ABILITIES)],
            'days' => ['sometimes', 'integer', 'min:1', 'max:1095'],
        ]);

        $raw = 'pk_'.Str::random(40);
        $key = ApiKey::create([
            'name' => $d['name'], 'key_prefix' => substr($raw, 0, 8), 'key_hash' => hash('sha256', $raw),
            'abilities' => array_values($d['abilities'] ?? self::ABILITIES), 'foundation_id' => $request->user()->foundation_id,
            'created_by' => $request->user()->id, 'expires_at' => now()->addDays($d['days'] ?? 365),
        ]);
        Audit::record('api_key.created', $request, 'api_key', $key->id);

        return ApiResponse::created($this->row($key) + ['key' => $raw], ['note' => 'Copy the key now. It cannot be shown again.']);
    }

    public function destroy(Request $request, int $id)
    {
        $key = ApiKey::where('foundation_id', $request->user()->foundation_id)->find($id);
        if (! $key) {
            return ApiResponse::notFound();
        }
        $key->update(['revoked_at' => now()]);
        Audit::record('api_key.revoked', $request, 'api_key', $key->id);

        return ApiResponse::ok($this->row($key->fresh()));
    }
}
