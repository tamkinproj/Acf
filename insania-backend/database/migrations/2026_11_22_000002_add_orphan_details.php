<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('children', function (Blueprint $t) {
            foreach (['nationality', 'birth_country', 'residence_country', 'madrasa_grade', 'quran_memorization', 'responsible_person', 'responsible_name_ar', 'responsible_name_en', 'mother_name_ar', 'sponsor_name', 'channel_label'] as $c) {
                $t->string($c, 150)->nullable();
            }
            $t->string('source_label', 150)->nullable();
            $t->string('sponsor_file_no', 60)->nullable();
            $t->string('responsible_relation', 60)->nullable();
            $t->string('study_level', 20)->nullable();
            $t->string('performance', 20)->nullable();
            $t->string('islamic_commitment', 20)->nullable();
            $t->string('mother_works', 3)->nullable();
            $t->decimal('mother_salary', 12, 2)->nullable();
            $t->unsignedSmallInteger('aided_siblings')->nullable();
            $t->text('address_ar')->nullable();
            $t->text('address_en')->nullable();
            $t->text('health_details')->nullable();
            $t->text('reason_not_studying')->nullable();
            $t->text('other_aid')->nullable();
            $t->text('notable_changes')->nullable();
            $t->text('organization_comment')->nullable();
            $t->text('message_to_sponsor')->nullable();
            $t->text('delay_reason')->nullable();
            $t->date('responsible_since')->nullable();
            $t->date('expected_send_date')->nullable();
            $t->timestamp('form_submitted_at')->nullable();
            $t->boolean('is_sample')->default(false);
        });

        // Photos of a child: private files, only the path is stored.
        Schema::create('child_photos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('foundation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('child_id')->constrained()->cascadeOnDelete();
            $t->string('path');
            $t->string('mime', 60);
            $t->timestamps();
        });

        // What people send through the public form, waiting for the Aytam staff to review.
        Schema::create('child_applications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('foundation_id')->constrained()->cascadeOnDelete();
            $t->string('status', 20)->default('new');            // new, approved, rejected
            $t->json('data');
            $t->json('photos')->nullable();                       // private paths
            $t->foreignId('child_id')->nullable()->constrained()->nullOnDelete();
            $t->string('ip_hash', 64)->nullable();
            $t->unsignedBigInteger('reviewed_by')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->string('note', 300)->nullable();
            $t->timestamps();
            $t->index(['foundation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_applications');
        Schema::dropIfExists('child_photos');
        Schema::table('children', function (Blueprint $t) {
            $t->dropColumn(['nationality', 'birth_country', 'residence_country', 'madrasa_grade', 'quran_memorization', 'responsible_person', 'responsible_name_ar', 'responsible_name_en', 'mother_name_ar', 'sponsor_name', 'channel_label', 'source_label', 'sponsor_file_no', 'responsible_relation', 'study_level', 'performance', 'islamic_commitment', 'mother_works', 'mother_salary', 'aided_siblings', 'address_ar', 'address_en', 'health_details', 'reason_not_studying', 'other_aid', 'notable_changes', 'organization_comment', 'message_to_sponsor', 'delay_reason', 'responsible_since', 'expected_send_date', 'form_submitted_at', 'is_sample']);
        });
    }
};
