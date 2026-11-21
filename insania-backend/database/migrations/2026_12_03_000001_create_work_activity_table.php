<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per person per day: when they were first and last active in the app, for how long, and how much they did.
        // Only counts and times, never what they looked at.
        Schema::create('work_activity', function (Blueprint $t) {
            $t->id();
            $t->foreignId('foundation_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('user_id');
            $t->date('day');                                   // the day in the office's time zone
            $t->dateTime('first_at')->nullable();              // UTC
            $t->dateTime('last_at')->nullable();
            $t->unsignedInteger('active_seconds')->default(0); // time between actions, gaps over 5 minutes do not count
            $t->unsignedInteger('requests')->default(0);
            $t->unsignedInteger('views')->default(0);          // looked at something
            $t->unsignedInteger('actions')->default(0);        // saved, added, changed or sent something
            $t->json('areas')->nullable();                     // {"orphans": 12, "chat": 4, ...} counts per part of the app
            $t->boolean('is_sample')->default(false);
            $t->timestamps();
            $t->unique(['user_id', 'day']);
            $t->index(['foundation_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_activity');
    }
};
