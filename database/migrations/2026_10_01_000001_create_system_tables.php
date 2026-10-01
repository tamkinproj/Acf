<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Infrastructure tables. None of these replicate between devices:
 * they describe THIS installation, not foundation business data.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Installation facts: installed_at, version, schema_version, install_id, deployment model.
        Schema::create('system_state', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->json('value')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('installation_log', function (Blueprint $table) {
            $table->id();
            $table->string('step', 60)->index();
            $table->string('status', 20);          // started | ok | failed | info
            $table->text('message')->nullable();
            $table->json('context')->nullable();   // never contains secrets
            $table->timestamp('created_at')->nullable();
        });

        // Per-installation (never synced) settings: upstream URL, local preferences.
        Schema::create('device_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        // Laravel database sessions. user_id is a UUID here, not a bigint.
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('user_id', 36)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        // Intentionally non-destructive: never drop installation state automatically.
    }
};
