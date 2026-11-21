<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What the bell shows for each person: a short message, where it leads, and whether they have seen it.
        Schema::create('notices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('foundation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('type', 30);                     // application | education | chat | security | system
            $t->string('title', 150);
            $t->string('body', 300)->nullable();
            $t->string('url', 190)->nullable();
            $t->string('dedupe_key', 80)->nullable();   // the same event is never told twice to the same person
            $t->timestamp('read_at')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['user_id', 'read_at']);
            $t->unique(['user_id', 'dedupe_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notices');
    }
};
