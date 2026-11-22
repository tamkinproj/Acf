<?php

use App\Support\NameParts;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The names the way the Dar Al Ber form asks for them: first, father, grandfather, family (Arabic and English; orphan, mother, responsible person).
        Schema::table('children', function (Blueprint $t) {
            foreach (NameParts::keys() as $k) {
                $t->string($k, 100)->nullable();
            }
        });

        // records saved before: split the full names they already have
        DB::table('children')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $r) {
                $set = [];
                foreach (NameParts::WHO as $who => $langs) {
                    foreach ($langs as $lang => $full) {
                        foreach (NameParts::split($r->{$full}) as $i => $v) {
                            if ($v !== null) {
                                $set[NameParts::key($who, NameParts::PARTS[$i], $lang)] = mb_substr($v, 0, 100);
                            }
                        }
                    }
                }
                if ($set) {
                    DB::table('children')->where('id', $r->id)->update($set);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('children', function (Blueprint $t) {
            $t->dropColumn(NameParts::keys());
        });
    }
};
