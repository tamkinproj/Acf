<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Photos of the papers sent with an application (the "upload documents" way of applying). Private paths only. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('child_applications', function (Blueprint $t) {
            $t->json('documents')->nullable();
            $t->string('method', 20)->nullable();        // form or documents
        });
    }

    public function down(): void
    {
        Schema::table('child_applications', function (Blueprint $t) {
            $t->dropColumn(['documents', 'method']);
        });
    }
};
