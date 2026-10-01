<?php

namespace App\Install;

use App\Models\InstallationLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Installation log: a file (storage/logs/install.log, available from the very first step, even
 * with no database) and, once the schema exists, the installation_log table. Context is
 * scrubbed - passwords and keys are never logged.
 */
class InstallLog
{
    /** @var list<array{step:string,status:string,message:?string,context:?array}> */
    private array $buffer = [];
    private bool $dbReady = false;

    public function add(string $step, string $status, ?string $message = null, ?array $context = null): void
    {
        $context = $context ? $this->scrub($context) : null;
        $entry = compact('step', 'status', 'message', 'context');

        Log::build(['driver' => 'single', 'path' => storage_path('logs/install.log')])
            ->info("[{$status}] {$step}".($message ? ": {$message}" : ''), $context ?? []);

        if ($this->dbReady) {
            $this->persist($entry);
        } else {
            $this->buffer[] = $entry;
        }
    }

    /** Call once the installation_log table exists; flushes everything logged so far. */
    public function databaseReady(): void
    {
        if (! Schema::hasTable('installation_log')) {
            return;
        }
        $this->dbReady = true;
        foreach ($this->buffer as $entry) {
            $this->persist($entry);
        }
        $this->buffer = [];
    }

    private function persist(array $entry): void
    {
        try {
            InstallationLog::create($entry + ['created_at' => now()]);
        } catch (\Throwable) {
            // Logging must never break the installation itself.
        }
    }

    private function scrub(array $context): array
    {
        foreach ($context as $k => $v) {
            if (is_array($v)) {
                $context[$k] = $this->scrub($v);
            } elseif (preg_match('/pass|secret|token|key/i', (string) $k)) {
                $context[$k] = '[redacted]';
            }
        }

        return $context;
    }
}
