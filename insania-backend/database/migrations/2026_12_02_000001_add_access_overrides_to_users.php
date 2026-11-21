<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What the President ticked differently from the department's usual access for one staff account: {"add": [...], "remove": [...]}
        Schema::table('users', function (Blueprint $t) {
            $t->json('access_overrides')->nullable()->after('preferences');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('access_overrides');
        });
    }
};
