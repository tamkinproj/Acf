<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A phone or computer the person chose to trust at sign-in. It holds a long secret (only its hash is kept here);
        // requests that carry it are not slowed down by the request limits.
        Schema::create('trusted_devices', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('token_hash', 64)->unique();
            $t->string('name', 80);
            $t->string('last_ip', 45)->nullable();
            $t->dateTime('last_used_at')->nullable();
            $t->dateTime('expires_at');
            $t->timestamps();
            $t->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_devices');
    }
};
