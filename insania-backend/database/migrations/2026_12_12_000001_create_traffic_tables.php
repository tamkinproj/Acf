<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who is reaching the server (System Admin's Security page). Kept small on purpose for shared hosting:
 * one row per address per hour, one row per page per hour, and full detail only for the requests worth a look
 * (blocked, sign-ins, failed sign-ins, refused, not found). Everything is deleted after the retention period (30 days).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traffic_hits', function (Blueprint $t) {
            $t->id();
            $t->dateTime('hour');                          // the start of the hour
            $t->string('ip', 45);
            $t->char('country', 2)->nullable();            // from Cloudflare, when the request really came through Cloudflare
            $t->unsignedInteger('requests')->default(0);
            $t->unsignedInteger('signins')->default(0);
            $t->unsignedInteger('failed_logins')->default(0);
            $t->unsignedInteger('blocked')->default(0);    // refused because the address is blocked
            $t->unsignedInteger('throttled')->default(0);  // "too many requests" from the app's own limits
            $t->unsignedInteger('events')->default(0);     // detail rows written this hour (capped)
            $t->unsignedBigInteger('user_id')->nullable(); // the last signed-in account seen from this address this hour
            $t->string('user_agent', 200)->nullable();
            $t->string('last_path', 160)->nullable();
            $t->dateTime('first_at');
            $t->dateTime('last_at');
            $t->unique(['hour', 'ip']);
            $t->index('ip');
            $t->index('last_at');
        });

        Schema::create('traffic_paths', function (Blueprint $t) {
            $t->id();
            $t->dateTime('hour');
            $t->string('path', 160);
            $t->unsignedInteger('requests')->default(0);
            $t->unique(['hour', 'path']);
        });

        Schema::create('traffic_events', function (Blueprint $t) {
            $t->id();
            $t->dateTime('at');
            $t->string('ip', 45);
            $t->char('country', 2)->nullable();
            $t->string('kind', 20);                        // blocked, login, login_failed, login_locked, throttled, denied, not_found
            $t->string('method', 8);
            $t->string('path', 160);
            $t->unsignedSmallInteger('status');
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('user_agent', 200)->nullable();
            $t->index('at');
            $t->index(['ip', 'at']);
        });

        Schema::create('blocked_ips', function (Blueprint $t) {
            $t->id();
            $t->string('ip', 45)->unique();
            $t->string('reason', 200)->nullable();
            $t->dateTime('expires_at')->nullable();        // empty: until someone unblocks it
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_ips');
        Schema::dropIfExists('traffic_events');
        Schema::dropIfExists('traffic_paths');
        Schema::dropIfExists('traffic_hits');
    }
};
