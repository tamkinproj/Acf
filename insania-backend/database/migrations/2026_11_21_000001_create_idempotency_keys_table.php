<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remembers the answer to a write the staff app sent, so sending it again (after a lost connection) never repeats it.
        Schema::create('idempotency_keys', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('key', 100);
            $t->string('method', 10);
            $t->string('path', 190);
            $t->unsignedSmallInteger('status')->nullable();
            $t->mediumText('body')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['user_id', 'key']);
            $t->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
