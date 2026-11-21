<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

/** Laravel 10's plain "email" rule lets line breaks through (CVE-2026-48019). Every email we accept uses the strict filter too. */
class EmailHeaderInjectionTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    private const BAD = ["\"x\r\nBcc: attacker@example.org\"@example.org", "\"a\r\n b\"@example.org", "a(\r\n comment)@example.org"];

    public function test_line_breaks_in_an_email_are_refused_everywhere(): void
    {
        $this->world();
        foreach (self::BAD as $bad) {
            $this->postJson('/api/login', ['email' => $bad, 'password' => 'whatever'])->assertStatus(422);
            $this->app['auth']->forgetGuards();
            $this->actingAsRole('security_admin');
            $this->postJson('/api/users', ['name' => 'X', 'email' => $bad, 'role' => 'staff', 'department_key' => 'aytam', 'password' => 'Str0ng#Zq9xKw'])->assertStatus(422)->assertJsonValidationErrors('email');
        }
    }

    public function test_normal_addresses_still_work(): void
    {
        $this->world();
        $this->actingAsRole('security_admin');
        $this->postJson('/api/users', ['name' => 'Ok', 'email' => 'first.last+tag@sub.example.org', 'role' => 'staff', 'department_key' => 'aytam', 'password' => 'Str0ng#Zq9xKw'])->assertCreated();
    }
}
