<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use App\Models\Foundation;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * The old system's "API Locker", as a command: issues a key for an outside system (a partner's reporting, for example).
 * The key is shown once and only its hash is stored.
 */
class CreateApiKey extends Command
{
    protected $signature = 'insania:create-api-key {name : Who the key is for} {foundation_id} {--ability=* : What it may do, e.g. partner.summary} {--days=365 : Days until it expires}';

    protected $description = 'Issue an API key for an outside system (shown once).';

    public function handle(): int
    {
        if (! Foundation::whereKey($this->argument('foundation_id'))->exists()) {
            $this->error('No such foundation.');

            return self::FAILURE;
        }

        $abilities = $this->option('ability') ?: ['partner.summary'];
        $raw = 'pk_'.Str::random(40);

        ApiKey::create([
            'name' => $this->argument('name'),
            'key_prefix' => substr($raw, 0, 8),
            'key_hash' => hash('sha256', $raw),
            'abilities' => array_values($abilities),
            'foundation_id' => (int) $this->argument('foundation_id'),
            'expires_at' => now()->addDays((int) $this->option('days')),
        ]);

        $this->info('API key (copy it now, it cannot be shown again):');
        $this->line($raw);

        return self::SUCCESS;
    }
}
