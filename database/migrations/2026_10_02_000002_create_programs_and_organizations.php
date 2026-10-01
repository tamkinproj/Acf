<?php

use App\Sync\SyncSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Programs and organizations: foundation-level structure shared by every program type.
 * All rows carry the replication columns so offline sync can adopt them later without a schema change.
 */
return new class extends Migration
{
    private function foundationKey(Blueprint $table): void
    {
        $table->uuid('foundation_id')->index();
        if (DB::getDriverName() !== 'sqlite') {
            $table->foreign('foundation_id')->references('id')->on('foundations')->restrictOnDelete();
        }
    }

    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            SyncSchema::id($table);
            $this->foundationKey($table);
            $table->string('name', 150);
            $table->string('slug', 80);
            $table->text('description')->nullable();
            $table->string('category', 40)->index();            // aytam, relief, education, ...
            $table->string('module', 40)->nullable()->index();  // the implemented module serving it, if any
            $table->string('logo_path', 255)->nullable();
            $table->string('status', 20)->default('draft')->index();   // draft | active | inactive | archived
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->json('config')->nullable();
            SyncSchema::columns($table);
            $table->unique(['foundation_id', 'slug']);
        });

        // Program team: which people work in which program, in which program role.
        Schema::create('program_users', function (Blueprint $table) {
            SyncSchema::id($table);
            $this->foundationKey($table);
            $table->foreignUuid('program_id')->constrained('programs')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('role_id')->constrained('roles')->restrictOnDelete();
            SyncSchema::columns($table, softDeletes: false);
            $table->unique(['program_id', 'user_id']);
        });

        Schema::create('organizations', function (Blueprint $table) {
            SyncSchema::id($table);
            $this->foundationKey($table);
            $table->string('name', 200);
            $table->string('type', 40)->index();
            $table->string('country', 100)->nullable();
            $table->string('location', 200)->nullable();
            $table->text('address')->nullable();
            $table->string('email', 190)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('website', 255)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('active')->index();   // active | inactive
            SyncSchema::columns($table);
            $table->index(['foundation_id', 'name']);
        });

        Schema::create('organization_contacts', function (Blueprint $table) {
            SyncSchema::id($table);
            $this->foundationKey($table);
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('title', 120)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('phone', 40)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            SyncSchema::columns($table);
        });

        // An organization is a foundation-level record; HOW it relates to one program lives here.
        Schema::create('program_organizations', function (Blueprint $table) {
            SyncSchema::id($table);
            $this->foundationKey($table);
            $table->foreignUuid('program_id')->constrained('programs')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('relationship', 40)->default('partner');
            $table->string('status', 20)->default('active');
            $table->json('config')->nullable();
            $table->text('notes')->nullable();
            SyncSchema::columns($table, softDeletes: false);
            $table->unique(['program_id', 'organization_id']);
        });
    }

    public function down(): void
    {
        // Intentionally non-destructive.
    }
};
