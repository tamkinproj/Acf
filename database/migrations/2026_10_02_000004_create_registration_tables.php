<?php

use App\Sync\SyncSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registration: a form the Mushrif designs, the immutable versions that were published, and what applicants submitted.
 * Forms never create their own tables: every answer maps onto the canonical Aytam model when a registration is approved.
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
        Schema::create('registration_forms', function (Blueprint $table) {
            SyncSchema::id($table);
            $this->ownership($table);
            $table->string('title', 200);
            $table->text('description')->nullable();                  // instructions shown above the form
            $table->string('status', 20)->default('draft')->index();  // draft | published | unpublished
            $table->string('public_token', 64)->nullable()->unique(); // the registration link; created on first publish
            $table->unsignedInteger('published_version')->default(0);
            $table->json('settings')->nullable();                     // success_message, closes_on
            SyncSchema::columns($table);
        });

        Schema::create('registration_form_sections', function (Blueprint $table) {
            SyncSchema::id($table);
            $this->ownership($table);
            $table->foreignUuid('form_id')->constrained('registration_forms')->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            SyncSchema::columns($table);
        });

        Schema::create('registration_form_fields', function (Blueprint $table) {
            SyncSchema::id($table);
            $this->ownership($table);
            $table->foreignUuid('form_id')->constrained('registration_forms')->cascadeOnDelete();
            $table->foreignUuid('section_id')->constrained('registration_form_sections')->cascadeOnDelete();
            $table->string('field_key', 60);                           // stable name inside the form
            $table->string('label', 255);
            $table->string('type', 30);
            $table->boolean('required')->default(false);
            $table->text('help_text')->nullable();
            $table->json('options')->nullable();                       // choice types
            $table->string('maps_to', 60)->nullable();                 // canonical field, e.g. aytam.first_name
            $table->string('document_type', 40)->nullable();           // file types
            $table->unsignedSmallInteger('position')->default(0);
            SyncSchema::columns($table);
        });

        // What was actually published. Submissions point here, so editing a form later never changes the meaning of old answers.
        Schema::create('registration_form_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('foundation_id')->index();
            $table->foreignUuid('form_id')->constrained('registration_forms')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('schema');
            $table->uuid('published_by')->nullable();
            $table->timestamp('published_at');
            $table->unique(['form_id', 'version']);
        });

        Schema::create('registrations', function (Blueprint $table) {
            SyncSchema::id($table);
            $this->ownership($table);
            $table->foreignUuid('form_id')->constrained('registration_forms')->restrictOnDelete();
            $table->foreignUuid('form_version_id')->constrained('registration_form_versions')->restrictOnDelete();
            $table->string('reference', 20);                           // what the applicant quotes: REG-7K2M9Q
            $table->string('status', 24)->default('pending_review')->index();   // pending_review | needs_correction | approved
            $table->string('applicant_name', 200)->nullable();
            $table->string('applicant_email', 190)->nullable();
            $table->string('applicant_phone', 40)->nullable();
            $table->json('answers');
            $table->string('access_token_hash', 64)->unique();         // the applicant's private status/correction link
            $table->timestamp('submitted_at');
            $table->timestamp('last_submitted_at');
            $table->unsignedSmallInteger('submission_count')->default(1);
            $table->uuid('reviewer_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();            // what the applicant is asked to correct
            $table->uuid('aytam_id')->nullable()->index();
            $table->string('duplicate_decision', 20)->nullable();      // create_new | use_existing
            $table->string('submitted_ip', 45)->nullable();
            SyncSchema::columns($table);
            $table->unique(['foundation_id', 'reference']);
            $table->index(['program_id', 'status']);
        });

        // Review history: append-only, never edited.
        Schema::create('registration_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('foundation_id')->index();
            $table->foreignUuid('registration_id')->constrained('registrations')->cascadeOnDelete();
            $table->string('type', 30);                                // submitted | resubmitted | returned | approved
            $table->uuid('actor_id')->nullable();
            $table->string('actor_name', 150)->nullable();
            $table->string('note', 500)->nullable();
            $table->json('data')->nullable();
            $table->timestamp('created_at', 6);
            $table->index(['registration_id', 'created_at']);
        });
    }

    public function down(): void
    {
        // Intentionally non-destructive.
    }
};
