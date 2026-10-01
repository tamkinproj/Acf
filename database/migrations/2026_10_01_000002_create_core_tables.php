<?php

use App\Sync\SyncSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            SyncSchema::id($table);
            $table->string('key', 50)->unique();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->boolean('is_system')->default(false);
            // Permission keys come from the code-defined PermissionCatalog; a JSON list keeps
            // a role's grants a single atomic, replicable value.
            $table->json('permissions');
            SyncSchema::columns($table);
        });

        Schema::create('users', function (Blueprint $table) {
            SyncSchema::id($table);
            $table->string('name', 150);
            $table->string('email', 190)->unique();
            $table->string('phone', 40)->nullable();
            $table->string('password');                       // server-only: never in a sync payload
            $table->foreignUuid('role_id')->constrained('roles')->restrictOnDelete();
            $table->string('status', 20)->default('active')->index(); // active | disabled
            $table->string('locale', 10)->nullable();
            $table->boolean('must_change_password')->default(false);
            $table->timestamp('last_login_at')->nullable();   // server-local, not replicated
            $table->uuid('last_login_device_id')->nullable();
            $table->rememberToken();
            SyncSchema::columns($table);
        });

        Schema::create('locations', function (Blueprint $table) {
            SyncSchema::id($table);
            $table->foreignUuid('parent_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->string('level', 20)->index();             // country|region|province|municipality|barangay|site
            $table->string('name', 150);
            $table->string('code', 50)->nullable()->index();
            $table->string('path', 255)->index();             // "/<uuid>/<uuid>/" server-derived
            $table->unsignedTinyInteger('depth')->default(0);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true);
            SyncSchema::columns($table);
            $table->index('parent_id');
        });

        Schema::create('foundations', function (Blueprint $table) {
            SyncSchema::id($table);
            $table->string('name', 200);
            $table->string('short_name', 60)->nullable();
            $table->text('description')->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('registration_number', 100)->nullable();
            $table->text('registration_info')->nullable();
            $table->string('logo_path', 255)->nullable();      // server-only storage path
            $table->string('logo_hash', 64)->nullable();       // replicated so devices know when to re-fetch
            $table->foreignUuid('default_location_id')->nullable()->constrained('locations')->nullOnDelete();
            SyncSchema::columns($table);
        });

        Schema::create('settings', function (Blueprint $table) {
            SyncSchema::id($table);
            $table->string('key', 100)->unique();
            $table->string('group', 50)->index();
            $table->json('value')->nullable();
            SyncSchema::columns($table);
        });

        Schema::create('devices', function (Blueprint $table) {
            SyncSchema::id($table);
            $table->string('device_code', 40)->unique();       // FOUNDATION-DEVICE-XXXXXXXX, immutable
            $table->string('name', 120);                       // friendly, editable
            $table->string('type', 20);
            $table->string('token_hash', 64)->nullable()->unique(); // sha256 of the device token; null = unclaimed
            $table->boolean('is_primary')->default(false);     // the installation's own device
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedBigInteger('last_pull_seq')->default(0);
            $table->string('app_version', 20)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->uuid('registered_by')->nullable();
            SyncSchema::columns($table);
        });

        // Append-only: no soft deletes, no updates (enforced in the model).
        Schema::create('audit_logs', function (Blueprint $table) {
            SyncSchema::id($table);
            $table->timestamp('occurred_at', 6)->index();
            $table->uuid('device_id')->nullable()->index();
            $table->uuid('user_id')->nullable()->index();
            $table->string('user_name', 150)->nullable();      // snapshot: survives user changes
            $table->string('action', 64)->index();              // e.g. user.created, auth.login
            $table->string('subject_type', 64)->nullable();
            $table->string('subject_id', 36)->nullable();
            $table->string('summary', 500);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            SyncSchema::columns($table, softDeletes: false);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        // Intentionally non-destructive.
    }
};
