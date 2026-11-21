<?php

use App\Support\ArabicNames;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            // An orphan's own sign-in account is linked to the orphan's record. A new account must choose its own password first.
            $t->unsignedBigInteger('child_id')->nullable()->unique()->after('guardian_id');
            $t->boolean('must_change_password')->default(false)->after('active');
        });

        // Orphans imported from the Arabic sheets got the Arabic name in the English field too: write it in Latin letters instead.
        foreach (DB::table('children')->whereColumn('name_en', 'name_ar')->get(['id', 'name_ar']) as $c) {
            if (ArabicNames::hasArabic((string) $c->name_ar)) {
                DB::table('children')->where('id', $c->id)->update(['name_en' => mb_substr(ArabicNames::toEnglish($c->name_ar), 0, 150)]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropUnique(['child_id']);
            $t->dropColumn(['child_id', 'must_change_password']);
        });
    }
};
