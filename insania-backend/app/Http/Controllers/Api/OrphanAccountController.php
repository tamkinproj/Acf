<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\User;
use App\Rules\StrongPassword;
use App\Support\ApiResponse;
use App\Support\Audit;
use App\Support\StepUp;
use App\Support\Usernames;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Sign-in accounts for the orphans themselves. Staff create them (one by one or for everybody who has none); each gets a username
 * and a one-time temporary password that is shown here only once and must be replaced by the orphan on the first sign-in.
 */
class OrphanAccountController extends Controller
{
    private const BATCH = 40;   // passwords are hashed on purpose slowly, so a big list is made in steps

    public function summary()
    {
        $total = Child::count();
        $with = Child::whereHas('account')->count();

        return ApiResponse::ok(['total' => $total, 'with_account' => $with, 'without_account' => $total - $with]);
    }

    /** POST children/accounts - makes accounts for orphans that have none (the next batch). Needs the staff member's own password. */
    public function store(Request $request)
    {
        $request->validate(['confirm_password' => ['required', 'string']]);
        if ($denied = StepUp::check($request)) {
            return $denied;
        }
        $fid = $request->user()->foundation_id;
        $todo = Child::whereDoesntHave('account')->orderBy('id')->limit(self::BATCH)->get();
        $made = $todo->map(fn (Child $c) => $this->make($c, $fid))->all();
        $remaining = Child::whereDoesntHave('account')->count();
        Audit::record('orphan.accounts_created', $request, null, null, ['count' => count($made)]);

        return ApiResponse::ok(['created' => count($made), 'remaining' => $remaining, 'credentials' => $made]);
    }

    /** POST children/{child}/account - one orphan: makes the account, or sets a new temporary password if there is one already. */
    public function reset(Request $request, Child $child)
    {
        $request->validate(['confirm_password' => ['required', 'string']]);
        if ($denied = StepUp::check($request)) {
            return $denied;
        }
        $user = $child->account()->first();
        if (! $user) {
            $cred = $this->make($child, $request->user()->foundation_id);
            Audit::record('orphan.account_created', $request, 'child', $child->id);

            return ApiResponse::created($cred);
        }
        $pw = $this->tempPassword([$user->name, $user->email, $user->username]);
        $user->forceFill(['password' => $pw, 'must_change_password' => true, 'active' => true])->save();
        $user->tokens()->delete();
        Audit::record('orphan.account_reset', $request, 'child', $child->id);

        return ApiResponse::ok($this->row($child, $user, $pw));
    }

    private function make(Child $c, int $foundationId): array
    {
        $base = $c->external_no ? 'o'.preg_replace('/[^a-z0-9._-]/', '', strtolower((string) $c->external_no)) : strtolower((string) $c->ref);
        $username = Usernames::suggest($base, fn ($x) => User::where('username', $x)->exists());
        $name = $c->name_en ?: $c->name_ar;
        $email = $username.'@orphans.invalid';
        $pw = $this->tempPassword([$name, $email, $username]);
        $user = User::create([
            'name' => mb_substr($name, 0, 150), 'email' => $email, 'username' => $username, 'password' => $pw, 'role' => 'user',
            'foundation_id' => $foundationId, 'child_id' => $c->id, 'active' => true, 'must_change_password' => true,
        ]);

        return $this->row($c, $user, $pw);
    }

    private function row(Child $c, User $u, string $pw): array
    {
        return ['child_id' => $c->id, 'ref' => $c->ref, 'external_no' => $c->external_no, 'name_en' => $c->name_en, 'name_ar' => $c->name_ar, 'username' => $u->username, 'password' => $pw];
    }

    /** Easy to read out loud (no 0/O or 1/l), like Kdm7-Qrt4-Wnp9, and always accepted by the password rules. */
    private function tempPassword(array $avoid): string
    {
        $pick = fn (string $set, int $n) => collect(range(1, $n))->map(fn () => $set[random_int(0, strlen($set) - 1)])->implode('');
        for ($i = 0; $i < 50; $i++) {
            $chars = str_split($pick('ABCDEFGHJKLMNPQRSTUVWXYZ', 3).$pick('abcdefghjkmnpqrstuvwxyz', 6).$pick('23456789', 3));
            shuffle($chars);
            $s = implode('', $chars);
            $pw = substr($s, 0, 4).'-'.substr($s, 4, 4).'-'.substr($s, 8, 4);
            $ok = Validator::make(['p' => $pw], ['p' => [Password::min(12)->mixedCase()->numbers(), StrongPassword::for($avoid)]])->passes();
            if ($ok) {
                return $pw;
            }
        }

        return 'Zq7m-Kd3x-Rw9p-'.random_int(10, 99);
    }
}
