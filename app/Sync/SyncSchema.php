<?php

namespace App\Sync;

use Illuminate\Database\Schema\Blueprint;

/**
 * The one place that defines the replication columns every synced table
 * carries. Keeping it here means a future module cannot "forget" a column
 * the sync engine relies on.
 *
 *  id               UUIDv7, generated on whichever device creates the row
 *  version          server-assigned revision; +1 per accepted change (conflict basis)
 *  origin_device_id device that made the last accepted change (soft reference)
 *  created_by / updated_by / deleted_by   acting user (soft references)
 *  deleted_at       soft-delete tombstone, so deletes replicate
 *
 * Actor/device columns are intentionally NOT foreign keys: audit history must
 * survive device/user removal, and sync order must never depend on them.
 */
final class SyncSchema
{
    public static function id(Blueprint $table): void
    {
        $table->uuid('id')->primary();
    }

    public static function columns(Blueprint $table, bool $softDeletes = true): void
    {
        $table->unsignedInteger('version')->default(1);
        $table->uuid('origin_device_id')->nullable()->index();
        $table->uuid('created_by')->nullable();
        $table->uuid('updated_by')->nullable();
        if ($softDeletes) {
            $table->uuid('deleted_by')->nullable();
        }
        $table->timestamps();
        if ($softDeletes) {
            $table->softDeletes();
            $table->index('deleted_at');
        }
        $table->index('updated_at');
    }
}
