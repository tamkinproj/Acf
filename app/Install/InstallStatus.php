<?php

namespace App\Install;

enum InstallStatus: string
{
    case NotInstalled = 'not_installed';
    case InProgress = 'in_progress';
    case Installed = 'installed';
    case Error = 'error';
    /** Lock/state files are missing, unreadable or inconsistent with .env. Never auto-repaired. */
    case Corrupt = 'corrupt';

    public function allowsInstaller(): bool
    {
        return in_array($this, [self::NotInstalled, self::InProgress, self::Error], true);
    }
}
