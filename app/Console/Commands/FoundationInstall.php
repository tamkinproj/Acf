<?php

namespace App\Console\Commands;

use App\Core\Settings\SettingsCatalog;
use App\Core\Users\PasswordPolicy;
use App\Install\DatabaseConfig;
use App\Install\Installer;
use App\Install\InstallState;
use App\Install\InstallStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Non-interactive installer for developers, CI and scripted deployments. It feeds the same answers the wizard
 * collects into the same Installer, so every safety rule (empty database, lock written last, ...) still applies.
 *
 * Secrets: prefer the FOUNDATION_ADMIN_PASSWORD / FOUNDATION_DB_PASSWORD environment variables or the prompt over
 * command-line options, which end up in shell history and process listings.
 */
class FoundationInstall extends Command
{
    protected $signature = 'foundation:install
        {--driver=sqlite : sqlite or mysql}
        {--sqlite-name=foundation : SQLite file name (stored in storage/app/db)}
        {--db-host=127.0.0.1} {--db-port=3306} {--db-name=} {--db-user=} {--db-password= : or env FOUNDATION_DB_PASSWORD}
        {--app-name=Foundation Management System} {--app-url=http://localhost:8000}
        {--timezone=Asia/Manila} {--locale=en} {--currency=PHP} {--deployment-model=standalone}
        {--foundation-name=} {--foundation-short-name=}
        {--admin-name=} {--admin-email=} {--admin-password= : or env FOUNDATION_ADMIN_PASSWORD, or prompt}
        {--device-name=Main Office} {--device-type=office}';

    protected $description = 'Install the system without the web wizard (same checks, same installer).';

    public function handle(InstallState $state, Installer $installer): int
    {
        $status = $state->status();
        if (! config('app.key')) {
            // No .env yet (a fresh checkout): the web wizard gets a temporary key from the installer gate; do the same here.
            config(['app.key' => $state->bootstrapKey()]);
        }
        if (! $status->allowsInstaller()) {
            $this->error($status === InstallStatus::Installed ? 'This system is already installed.' : 'The installation state is damaged ('.$status->value.'). Run foundation:status.');

            return self::FAILURE;
        }

        $dbPassword = (string) ($this->option('db-password') ?: getenv('FOUNDATION_DB_PASSWORD') ?: '');
        $adminPassword = (string) ($this->option('admin-password') ?: getenv('FOUNDATION_ADMIN_PASSWORD') ?: '');
        if ($adminPassword === '' && $this->input->isInteractive()) {
            $adminPassword = (string) $this->secret('Administrator password');
        }

        try {
            $db = $this->option('driver') === 'sqlite'
                ? ['driver' => 'sqlite', 'sqlite_name' => (string) $this->option('sqlite-name')]
                : ['driver' => 'mysql', 'host' => (string) $this->option('db-host'), 'port' => (int) $this->option('db-port'),
                    'database' => (string) $this->option('db-name'), 'username' => (string) $this->option('db-user')];
            $config = DatabaseConfig::fromArray($db + ['password' => $dbPassword]);   // validates every value
            $test = $config->test();
            if (! $test['ok']) {
                $this->error($test['message']);

                return self::FAILURE;
            }
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $data = Validator::make([
            'app_name' => $this->option('app-name'), 'app_url' => rtrim((string) $this->option('app-url'), '/'),
            'timezone' => $this->option('timezone'), 'locale' => $this->option('locale'), 'currency' => $this->option('currency'),
            'deployment_model' => $this->option('deployment-model'),
            'foundation_name' => $this->option('foundation-name'), 'short_name' => $this->option('foundation-short-name'),
            'admin_name' => $this->option('admin-name'), 'admin_email' => $this->option('admin-email'), 'admin_password' => $adminPassword,
            'device_name' => $this->option('device-name'), 'device_type' => $this->option('device-type'),
        ], [
            'app_name' => ['required', 'string', 'max:120'], 'app_url' => ['required', 'url:http,https', 'max:255'],
            'timezone' => ['required', Rule::in(\DateTimeZone::listIdentifiers())],
            'locale' => ['required', Rule::in(SettingsCatalog::LOCALES)], 'currency' => ['required', Rule::in(SettingsCatalog::CURRENCIES)],
            'deployment_model' => ['required', Rule::in(config('foundation.deployment_models'))],
            'foundation_name' => ['required', 'string', 'max:200'], 'short_name' => ['nullable', 'string', 'max:60'],
            'admin_name' => ['required', 'string', 'max:150'], 'admin_email' => ['required', 'email:rfc', 'max:190'],
            'admin_password' => ['required', 'string', PasswordPolicy::rule()],
            'device_name' => ['required', 'string', 'max:120'], 'device_type' => ['required', Rule::in(config('foundation.device.types'))],
        ]);
        if ($data->fails()) {
            foreach ($data->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }
        $v = $data->validated();

        $state->put([
            'requirements' => true,
            'database' => $db,
            'system' => collect($v)->only(['app_name', 'app_url', 'timezone', 'locale', 'currency', 'deployment_model'])->all(),
            'foundation' => ['name' => $v['foundation_name'], 'short_name' => $v['short_name'] ?? null],
            'admin' => ['name' => $v['admin_name'], 'email' => strtolower($v['admin_email'])],
            'device' => ['name' => $v['device_name'], 'type' => $v['device_type']],
        ]);
        $state->putSecret('db_password_enc', $dbPassword);
        $state->putSecret('admin_password_enc', $adminPassword);

        $result = $installer->run();
        if (! $result['ok']) {
            $this->error($result['error']);

            return self::FAILURE;
        }

        $this->info('Installed.');
        foreach ($result['summary'] as $label => $value) {
            $this->line(sprintf('  %-14s %s', ucfirst($label), $value));
        }

        return self::SUCCESS;
    }
}
