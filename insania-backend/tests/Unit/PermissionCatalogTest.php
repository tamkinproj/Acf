<?php

namespace Tests\Unit;

use App\Support\Csv;
use App\Support\PermissionCatalog;
use PHPUnit\Framework\TestCase;

/** Guards the access configuration itself, so a typo or a careless grant cannot slip in. */
class PermissionCatalogTest extends TestCase
{
    private function config(): array
    {
        return require __DIR__.'/../../config/roles.php';
    }

    public function test_every_granted_key_exists_in_the_catalog(): void
    {
        $cfg = $this->config();
        $granted = array_merge(...array_values($cfg['grants']), ...array_map(fn ($d) => $d['grants'], array_values($cfg['departments'])));

        foreach ($granted as $key) {
            $this->assertTrue(PermissionCatalog::isValidPermissionKey($key), "Unknown permission key in config/roles.php: $key");
        }
    }

    public function test_technical_roles_hold_no_record_or_money_permission(): void
    {
        $cfg = $this->config();
        $forbidden = ['children.', 'documents.', 'bank.', 'payments.', 'reports.', 'projects.', 'family.'];

        foreach (['system_admin', 'admin_maintenance', 'security_admin'] as $role) {
            foreach ($cfg['grants'][$role] as $key) {
                foreach ($forbidden as $prefix) {
                    $this->assertStringStartsNotWith($prefix, $key, "$role must not hold $key");
                }
            }
        }
    }

    public function test_system_admin_cannot_be_handed_out_through_the_api(): void
    {
        $this->assertNotContains('system_admin', $this->config()['assignable_by_security_admin']);
    }

    public function test_only_the_president_and_security_admin_manage_users_and_only_system_admin_changes_settings(): void
    {
        $cfg = $this->config();
        $holders = fn (string $key) => array_keys(array_filter($cfg['grants'], fn ($g) => in_array($key, $g, true)));

        $this->assertEqualsCanonicalizing(['president_admin', 'security_admin'], $holders('users.manage'));
        $this->assertSame(['system_admin'], $holders('system.settings'));
        $this->assertEqualsCanonicalizing(['president_admin', 'security_admin'], $holders('audit.read'));
    }

    public function test_csv_cells_cannot_run_as_formulas(): void
    {
        foreach (['=1+1', '+1', '-1', '@SUM(A1)', "\t=x", "\r=x"] as $evil) {
            $this->assertStringStartsWith('"\'', Csv::cell($evil), "Cell not neutralised: ".json_encode($evil));
        }
        $this->assertSame('"Plain text"', Csv::cell('Plain text'));
        $this->assertSame('"a ""quoted"" word"', Csv::cell('a "quoted" word'));
    }
}
