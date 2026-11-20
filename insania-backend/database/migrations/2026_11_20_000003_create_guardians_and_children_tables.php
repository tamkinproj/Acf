<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('foundation_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('relation', 60)->nullable();
            $table->string('phone', 40)->nullable();
            $table->timestamps();
        });

        // Replaces the old `orphan_profiles` (which hung off a school user): a child is its own record.
        Schema::create('children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('foundation_id')->constrained()->cascadeOnDelete();
            $table->string('ref', 30);
            $table->string('name_en');
            $table->string('name_ar')->nullable();
            $table->string('gender', 1)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('city')->nullable();
            $table->string('health_status', 30)->default('good');
            $table->string('grade', 40)->nullable();
            $table->string('mother_name')->nullable();
            $table->string('mother_marital_status', 40)->nullable();
            $table->date('father_death_date')->nullable();
            $table->unsignedSmallInteger('brothers')->default(0);
            $table->unsignedSmallInteger('sisters')->default(0);
            $table->foreignId('guardian_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['foundation_id', 'ref']);
            $table->index(['foundation_id', 'status']);
        });

        // Replaces the old `user_documents`: typed, private files per child. Only the path is stored here.
        Schema::create('child_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('foundation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->unique(['child_id', 'type']);
        });

        // Only the last four digits are ever stored.
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('foundation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->string('holder');
            $table->string('bank_name');
            $table->string('last4', 4);
            $table->boolean('verified')->default(false);
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique('child_id');
        });

        Schema::create('channel_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('foundation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 20);
            $table->date('since');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_histories');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('child_documents');
        Schema::dropIfExists('children');
        Schema::dropIfExists('guardians');
    }
};
