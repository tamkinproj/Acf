<?php

use App\Sync\SyncSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Aytam program's own data. Relational on purpose: one permanent master record per Aytam, reusable families and
 * guardians, and documents that keep their history. Nothing here is shared with other program types.
 */
return new class extends Migration
{
    private function ownership(Blueprint $table): void
    {
        $table->uuid('foundation_id')->index();
        $table->foreignUuid('program_id')->constrained('programs')->restrictOnDelete();
        if (DB::getDriverName() !== 'sqlite') {
            $table->foreign('foundation_id')->references('id')->on('foundations')->restrictOnDelete();
        }
    }

    public function up(): void
    {
        // Gap-free-ish permanent identifiers (AYT-000001): one counter per scope, advanced inside the creating transaction.
        Schema::create('sequences', function (Blueprint $table) {
            $table->string('scope_key', 120)->primary();
            $table->unsignedBigInteger('value')->default(0);
        });

        Schema::create('guardians', function (Blueprint $table) {
            SyncSchema::id($table);
            $this->ownership($table);
            $table->string('full_name', 200);
            $table->string('relationship', 60)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 190)->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            SyncSchema::columns($table);
            $table->index(['program_id', 'full_name']);
        });

        Schema::create('families', function (Blueprint $table) {
            SyncSchema::id($table);
            $this->ownership($table);
            $table->string('name', 200);
            $table->string('father_name', 200)->nullable();
            $table->string('father_status', 20)->nullable();      // living | deceased | unknown
            $table->string('mother_name', 200)->nullable();
            $table->string('mother_status', 20)->nullable();
            $table->uuid('guardian_id')->nullable()->index();
            $table->string('phone', 40)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('barangay', 100)->nullable();
            $table->text('address_detail')->nullable();
            $table->text('notes')->nullable();
            SyncSchema::columns($table);
            $table->index(['program_id', 'name']);
        });

        Schema::create('aytam', function (Blueprint $table) {
            SyncSchema::id($table);
            $this->ownership($table);
            $table->string('aytam_code', 24);                      // permanent: AYT-000001
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('arabic_name', 200)->nullable();
            $table->string('normalized_name', 300)->index();       // lower-case, accent-free, word-sorted: duplicate lookup
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('nationality', 80)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('barangay', 100)->nullable();
            $table->text('address_detail')->nullable();
            $table->uuid('family_id')->nullable()->index();
            $table->uuid('guardian_id')->nullable()->index();
            $table->string('education_level', 80)->nullable();
            $table->string('school', 200)->nullable();
            $table->string('grade', 40)->nullable();
            $table->text('education_notes')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 190)->nullable();
            // draft | pending_review | needs_correction | approved | active | inactive | archived
            $table->string('status', 24)->default('draft')->index();
            $table->string('status_note', 500)->nullable();
            $table->string('source', 20)->default('manual');       // manual | registration | import
            $table->uuid('registration_id')->nullable()->index();
            $table->string('legacy_ref', 100)->nullable()->index();  // identifier from the system the record came from
            $table->timestamp('approved_at')->nullable();
            $table->uuid('approved_by')->nullable();
            SyncSchema::columns($table);
            $table->unique(['program_id', 'aytam_code']);
            $table->index(['program_id', 'date_of_birth']);
            $table->index(['program_id', 'status']);
        });

        Schema::create('aytam_assignments', function (Blueprint $table) {
            SyncSchema::id($table);
            $this->ownership($table);
            $table->foreignUuid('aytam_id')->constrained('aytam')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('assigned_by')->nullable();
            SyncSchema::columns($table, softDeletes: false);
            $table->unique(['aytam_id', 'user_id']);
        });

        // One row per VERSION of a document. All versions of the same slot share `group_id`; one is current.
        Schema::create('documents', function (Blueprint $table) {
            SyncSchema::id($table);
            $this->ownership($table);
            $table->uuid('aytam_id')->nullable()->index();
            $table->uuid('registration_id')->nullable()->index();
            $table->uuid('group_id')->index();
            $table->unsignedInteger('doc_version')->default(1);
            $table->boolean('is_current')->default(true);
            $table->string('type', 40)->index();
            $table->string('original_name', 255);                  // display only; the stored name is random
            $table->string('storage_path', 255);                   // server-only, never in a payload
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64);
            $table->string('verification_status', 20)->default('pending')->index();   // pending | verified | rejected | expired
            $table->uuid('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->date('expires_on')->nullable();
            $table->text('notes')->nullable();
            SyncSchema::columns($table);
            $table->index(['group_id', 'is_current']);
        });
    }

    public function down(): void
    {
        // Intentionally non-destructive.
    }
};
