<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('foundation_id')->constrained()->cascadeOnDelete();
            $table->string('name_en');
            $table->string('name_ar')->nullable();
            $table->string('location')->nullable();
            $table->decimal('budget', 12, 2)->default(0);
            $table->decimal('spent', 12, 2)->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        // Who did what. Never holds personal data itself, only ids and short labels.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('foundation_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 60);
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['foundation_id', 'created_at']);
            $table->index('action');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('foundation_id')->constrained()->cascadeOnDelete();
            $table->string('key', 80);
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['foundation_id', 'key']);
        });

        // Kept from the old system: keys for outside systems (a partner's reporting, for example).
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key_prefix', 16);
            $table->string('key_hash', 64)->unique();
            $table->json('abilities')->nullable();
            $table->unsignedBigInteger('foundation_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index('foundation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('projects');
    }
};
