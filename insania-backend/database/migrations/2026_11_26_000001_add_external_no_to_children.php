<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The orphan's number on the sponsor organisation's own list (the "رقم اليتيم" column of their report sheets).
        // Used to recognise an orphan when the same sheet is imported again, so nobody is added twice.
        Schema::table('children', function (Blueprint $t) {
            $t->string('external_no', 40)->nullable()->after('ref');
            $t->index(['foundation_id', 'external_no']);
        });
    }

    public function down(): void
    {
        Schema::table('children', function (Blueprint $t) {
            $t->dropIndex(['foundation_id', 'external_no']);
            $t->dropColumn('external_no');
        });
    }
};
