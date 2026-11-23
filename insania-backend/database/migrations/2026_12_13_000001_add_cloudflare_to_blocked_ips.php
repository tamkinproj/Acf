<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 of the Security page: a block is also made at Cloudflare (so the request stops before the hosting),
 * and each address's refused requests are counted for the alerts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blocked_ips', function (Blueprint $t) {
            $t->string('cf_rule_id', 64)->nullable();   // Cloudflare's IP Access rule
            $t->string('cf_status', 12)->nullable();    // ok, failed, removing, removed; empty: Cloudflare not connected
            $t->string('cf_error', 200)->nullable();
        });
        Schema::table('traffic_hits', function (Blueprint $t) {
            $t->unsignedInteger('refused')->default(0); // not found, refused, too many requests, sign-in locked
        });
    }

    public function down(): void
    {
        Schema::table('blocked_ips', fn (Blueprint $t) => $t->dropColumn(['cf_rule_id', 'cf_status', 'cf_error']));
        Schema::table('traffic_hits', fn (Blueprint $t) => $t->dropColumn('refused'));
    }
};
