<?php

namespace Tests\Unit;

use App\Install\DatabaseConfig;
use App\Install\EnvWriter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class InstallerPrimitivesTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir().'/envtest-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    public function test_env_writer_quotes_and_escapes_values(): void
    {
        (new EnvWriter($this->file))->write([
            'APP_NAME' => 'Al-Noor "Foundation" $HOME', 'DB_PASSWORD' => 'p@ss w\\ord#1', 'APP_DEBUG' => false, 'DB_PORT' => 3306, 'DB_HOST' => '127.0.0.1', 'APP_URL' => 'https://x.example.com',
        ]);
        $env = file_get_contents($this->file);

        $this->assertStringContainsString('APP_NAME="Al-Noor \\"Foundation\\" \\$HOME"', $env);
        $this->assertStringContainsString('DB_PASSWORD="p@ss w\\\\ord#1"', $env);
        $this->assertStringContainsString("APP_DEBUG=false\n", $env);
        $this->assertStringContainsString("DB_HOST=127.0.0.1\n", $env);
        $this->assertSame(0600, fileperms($this->file) & 0777);
    }

    public function test_env_writer_round_trips_through_a_real_dotenv_parser(): void
    {
        $nasty = "a'b\"c\$d #e \\f";
        (new EnvWriter($this->file))->write(['APP_NAME' => $nasty, 'DB_PASSWORD' => 'x']);
        $parsed = \Dotenv\Dotenv::parse(file_get_contents($this->file));

        $this->assertSame($nasty, $parsed['APP_NAME']);
        $this->assertCount(2, $parsed, 'no extra keys can be smuggled in');
    }

    public function test_env_writer_refuses_injection_and_unknown_keys(): void
    {
        $writer = new EnvWriter($this->file);
        foreach ([["APP_NAME" => "x\nAPP_DEBUG=true"], ["APP_NAME" => "x\rAPP_KEY=y"], ['EVIL_KEY' => 'x'], ['APP_NAME' => "nul\0byte"]] as $bad) {
            try {
                $writer->write($bad);
                $this->fail('should have refused '.json_encode($bad));
            } catch (InvalidArgumentException) {
                $this->assertFileDoesNotExist($this->file);
            }
        }
    }

    public function test_database_config_validation(): void
    {
        $this->assertSame('mysql', DatabaseConfig::mysql('db.example.com', 3306, 'foundation', 'user', 'pw')->driver);
        $this->assertSame('mysql', DatabaseConfig::mysql('::1', 3306, 'foundation', 'user', '')->driver);

        foreach ([
            ['host with space', 3306, 'db', 'u'], ["h\nost", 3306, 'db', 'u'], ['127.0.0.1', 0, 'db', 'u'], ['127.0.0.1', 99999, 'db', 'u'],
            ['127.0.0.1', 3306, 'db;drop', 'u'], ['127.0.0.1', 3306, '', 'u'], ['127.0.0.1', 3306, 'db', ''], ['127.0.0.1', 3306, str_repeat('a', 65), 'u'],
        ] as [$h, $p, $d, $u]) {
            try {
                DatabaseConfig::mysql($h, $p, $d, $u, 'pw');
                $this->fail("accepted: {$h}/{$p}/{$d}/{$u}");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        foreach (['../x', 'a/b', 'a.b', '', 'x y'] as $name) {
            try {
                DatabaseConfig::sqlite($name);
                $this->fail("accepted sqlite name: {$name}");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_cookie_path_follows_the_application_folder(): void
    {
        $this->assertSame('/', \App\Install\Installer::cookiePath('https://foundation.example.com'));
        $this->assertSame('/', \App\Install\Installer::cookiePath('https://foundation.example.com/'));
        $this->assertSame('/acf', \App\Install\Installer::cookiePath('https://manhaje.com/acf'));
        $this->assertSame('/acf/app', \App\Install\Installer::cookiePath('https://manhaje.com/acf/app/'));
        $this->assertSame('/', \App\Install\Installer::cookiePath('https://x.test/a;b=c'), 'anything odd falls back to the safe default');
    }
}
