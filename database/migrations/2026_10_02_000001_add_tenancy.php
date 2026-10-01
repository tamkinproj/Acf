<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Multi-tenancy. A foundation becomes a tenant: its users, roles, settings, places, devices, activity and
 * sync history all carry foundation_id, and the application scopes every query to the current tenant.
 *
 * A NULL foundation_id means "owned by the platform" (platform administrators and their role).
 * An existing single-foundation installation is adopted as the first tenant, so nothing is lost on upgrade.
 */
return new class extends Migration
{
    /** Tenant-owned tables that already exist. */
    private const TABLES = ['roles', 'users', 'locations', 'settings', 'devices', 'audit_logs', 'sync_changes', 'sync_conflicts'];

    public function up(): void
    {
        Schema::table('foundations', function (Blueprint $table) {
            $table->string('slug', 80)->nullable()->unique();
            $table->string('legal_name', 200)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('status', 20)->default('active')->index();   // active | inactive | suspended
            $table->string('status_reason', 255)->nullable();
            $table->timestamp('status_changed_at')->nullable();
        });

        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->uuid('foundation_id')->nullable()->index();
                if (DB::getDriverName() !== 'sqlite') {
                    $table->foreign('foundation_id', "{$name}_foundation_fk")->references('id')->on('foundations')->restrictOnDelete();
                }
            });
        }

        Schema::table('roles', function (Blueprint $table) {
            $table->string('scope', 20)->default('foundation')->index();   // platform | foundation | program
            $table->string('module', 40)->nullable();                      // program roles: the module they belong to
            $table->dropUnique(['key']);
            $table->unique(['foundation_id', 'key']);
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->unique(['foundation_id', 'key']);
        });

        $this->adoptExistingFoundation();
    }

    /** An installation created before multi-tenancy has exactly one foundation: it becomes the first tenant. */
    private function adoptExistingFoundation(): void
    {
        $foundation = DB::table('foundations')->orderBy('created_at')->first();
        if (! $foundation) {
            return;
        }

        DB::table('foundations')->where('id', $foundation->id)->update([
            'slug' => Str::slug($foundation->short_name ?: $foundation->name) ?: 'foundation',
            'status' => 'active',
        ]);
        foreach (self::TABLES as $name) {
            DB::table($name)->whereNull('foundation_id')->update(['foundation_id' => $foundation->id]);
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive.
    }
};
