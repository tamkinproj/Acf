<?php

use App\Support\Partners;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tables whose records belong to a partner. */
    private const OWNED = ['children', 'child_applications', 'edu_institutions', 'edu_teachers', 'edu_curricula'];

    public function up(): void
    {
        // The partner charities (Dar Al Ber Society, Dubai Charity, ...). Never deleted: active, paused or not set up yet.
        Schema::create('partners', function (Blueprint $t) {
            $t->id();
            $t->foreignId('foundation_id')->constrained()->cascadeOnDelete();
            $t->string('code', 40);
            $t->string('name_en', 150);
            $t->string('name_ar', 150)->nullable();
            $t->string('logo', 200)->nullable();
            $t->string('form_key', 40)->nullable();
            $t->string('status', 12)->default('not_setup');   // active | paused | not_setup
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamp('paused_at')->nullable();
            $t->unsignedBigInteger('paused_by')->nullable();
            $t->timestamps();
            $t->unique(['foundation_id', 'code']);
        });

        foreach (self::OWNED as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('partner_id')->nullable()->after('foundation_id');
                $t->index('partner_id');
            });
        }

        // every foundation gets its partners; everything that exists today belongs to Dar Al Ber
        foreach (DB::table('foundations')->pluck('id') as $fid) {
            Partners::ensure((int) $fid);
            $dar = DB::table('partners')->where('foundation_id', $fid)->where('code', Partners::DEFAULT)->value('id');
            foreach (self::OWNED as $table) {
                DB::table($table)->where('foundation_id', $fid)->whereNull('partner_id')->update(['partner_id' => $dar]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::OWNED as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropIndex(['partner_id']);
                $t->dropColumn('partner_id');
            });
        }
        Schema::dropIfExists('partners');
    }
};
