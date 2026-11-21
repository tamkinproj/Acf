<?php

namespace Tests\Concerns;

use App\Models\BankAccount;
use App\Models\Child;
use App\Models\Department;
use App\Models\Foundation;
use App\Models\Guardian;
use App\Models\Project;
use App\Models\Sponsorship;
use App\Models\SponsorshipPayment;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Laravel\Sanctum\Sanctum;

trait BuildsWorld
{
    /** Role (and department) => demo account created by DemoSeeder. */
    protected array $accounts = [
        'system_admin' => 'system@insania.test',
        'admin_maintenance' => 'maintenance@insania.test',
        'security_admin' => 'security@insania.test',
        'president_admin' => 'president@insania.test',
        'staff:aytam' => 'aytam@insania.test',
        'staff:finance' => 'finance@insania.test',
        'staff:projects' => 'projects@insania.test',
        'staff:education' => 'education@insania.test',
        'staff:hr' => 'hr@insania.test',
        'user' => 'family@insania.test',
    ];

    protected function world(): void
    {
        $this->seed(DemoSeeder::class);
    }

    protected function actingAsRole(string $key): User
    {
        $user = User::where('email', $this->accounts[$key])->firstOrFail();
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    protected function actingAsEmail(string $email): User
    {
        $user = User::where('email', $email)->firstOrFail();
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    /** A second, separate foundation with its own people and data, to prove nothing leaks across. */
    protected function otherFoundation(): array
    {
        $f = Foundation::create(['name' => 'Other Foundation', 'slug' => 'other']);
        foreach (config('roles.departments') as $key => $d) {
            Department::create(['foundation_id' => $f->id, 'key' => $key, 'name_en' => $d['name_en']]);
        }

        $guardian = Guardian::create(['foundation_id' => $f->id, 'name' => 'Other Guardian']);
        $child = Child::create(['foundation_id' => $f->id, 'ref' => 'OTH-0001', 'name_en' => 'Other Child', 'guardian_id' => $guardian->id]);
        BankAccount::create(['foundation_id' => $f->id, 'child_id' => $child->id, 'holder' => 'Other Guardian', 'bank_name' => 'Other Bank', 'last4' => '9999', 'verified' => false]);
        $s = Sponsorship::create(['foundation_id' => $f->id, 'child_id' => $child->id, 'channel' => 'direct', 'status' => 'active', 'pay_type' => 'cash', 'monthly_amount' => 300]);
        SponsorshipPayment::create(['foundation_id' => $f->id, 'sponsorship_id' => $s->id, 'month' => '2026-11', 'amount' => 300, 'method' => 'cash', 'status' => 'pending']);
        $project = Project::create(['foundation_id' => $f->id, 'name_en' => 'Other Project', 'budget' => 100, 'spent' => 10]);

        $mk = fn (string $email, string $role, ?string $dept = null, $guardianId = null) => User::create([
            'name' => $email, 'email' => $email, 'password' => 'OtherPass#2026', 'role' => $role, 'foundation_id' => $f->id, 'active' => true,
            'department_id' => $dept ? Department::where('foundation_id', $f->id)->where('key', $dept)->value('id') : null, 'guardian_id' => $guardianId,
        ]);

        return [
            'foundation' => $f, 'child' => $child, 'guardian' => $guardian, 'project' => $project, 'sponsorship' => $s,
            'president' => $mk('other-president@x.test', 'president_admin'),
            'security' => $mk('other-security@x.test', 'security_admin'),
            'family' => $mk('other-family@x.test', 'user', null, $guardian->id),
        ];
    }

    protected function childId(string $ref): int
    {
        return (int) Child::withoutGlobalScopes()->where('ref', $ref)->value('id');
    }
}
