<?php

namespace Tests\Feature;

use App\Install\DatabaseConfig;
use Illuminate\Support\Facades\DB;
use Tests\InstallTestCase;

class DatabaseEmptinessTest extends InstallTestCase
{
    public function test_sqlite_emptiness_check(): void
    {
        $config = DatabaseConfig::sqlite($this->dbName);
        $this->assertTrue($config->test()['ok']);
        $this->assertFalse($config->hasExistingTables(), 'a brand-new file is empty');

        $pdo = new \PDO('sqlite:'.$this->dbFile);
        $pdo->exec('CREATE TABLE t (id INTEGER)');
        $this->assertTrue($config->hasExistingTables());
    }

    /** Runs only against a real MySQL/MariaDB server: DB_CONNECTION=mysql DB_USERNAME=… DB_PASSWORD=… */
    public function test_mysql_only_counts_the_selected_database_not_every_database_the_user_can_see(): void
    {
        if (env('DB_CONNECTION') !== 'mysql') {
            $this->markTestSkipped('Needs a MySQL/MariaDB server.');
        }
        $admin = new \PDO(sprintf('mysql:host=%s;port=%s', env('DB_HOST', '127.0.0.1'), env('DB_PORT', '3306')), env('DB_USERNAME'), env('DB_PASSWORD'));
        $busy = 'fdn_busy_'.bin2hex(random_bytes(3));
        $empty = 'fdn_empty_'.bin2hex(random_bytes(3));
        try {
            foreach ([$busy, $empty] as $db) {
                $admin->exec("CREATE DATABASE `{$db}`");
            }
            $admin->exec("CREATE TABLE `{$busy}`.t (id INT)");

            $mk = fn (string $db) => DatabaseConfig::mysql(env('DB_HOST', '127.0.0.1'), (int) env('DB_PORT', 3306), $db, env('DB_USERNAME'), (string) env('DB_PASSWORD'));
            $this->assertTrue($mk($busy)->hasExistingTables());
            $this->assertFalse($mk($empty)->hasExistingTables(), 'tables in a neighbouring database must not count');
        } finally {
            foreach ([$busy, $empty] as $db) {
                $admin->exec("DROP DATABASE IF EXISTS `{$db}`");
            }
        }
    }
}
