<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Places where Alimni teachers teach: the foundation's own madrasa, and partner markaz (centres).
        Schema::create('edu_institutions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('foundation_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 10);                       // madrasa | markaz
            $t->string('partnership', 10)->nullable();    // own | partner
            $t->string('name_en', 150);
            $t->string('name_ar', 150)->nullable();
            $t->string('city', 100)->nullable();
            $t->string('address', 300)->nullable();
            $t->string('contact_name', 150)->nullable();
            $t->string('phone', 40)->nullable();
            $t->date('since')->nullable();
            $t->string('status', 10)->default('active'); // active | ended
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['foundation_id', 'kind']);
        });

        // A curriculum (for example the one from Dar Al Ber) is a list of units that teachers follow.
        Schema::create('edu_curricula', function (Blueprint $t) {
            $t->id();
            $t->foreignId('foundation_id')->constrained()->cascadeOnDelete();
            $t->string('title_en', 150);
            $t->string('title_ar', 150)->nullable();
            $t->string('source', 100)->nullable();
            $t->string('level', 60)->nullable();
            $t->text('description')->nullable();
            $t->string('status', 10)->default('active'); // active | archived
            $t->timestamps();
        });
        Schema::create('edu_units', function (Blueprint $t) {
            $t->id();
            $t->foreignId('foundation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('curriculum_id')->constrained('edu_curricula')->cascadeOnDelete();
            $t->unsignedSmallInteger('position')->default(0);
            $t->string('title_en', 200);
            $t->string('title_ar', 200)->nullable();
            $t->text('description')->nullable();
            $t->timestamps();
        });

        // The Alimni teachers.
        Schema::create('edu_teachers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('foundation_id')->constrained()->cascadeOnDelete();
            $t->string('ref', 30);
            $t->string('name_en', 150);
            $t->string('name_ar', 150)->nullable();
            $t->string('gender', 1)->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('email', 150)->nullable();
            $t->string('city', 100)->nullable();
            $t->string('qualification', 150)->nullable();
            $t->string('specialization', 150)->nullable();
            $t->date('hired_on')->nullable();
            $t->foreignId('institution_id')->nullable()->constrained('edu_institutions')->nullOnDelete();
            $t->foreignId('curriculum_id')->nullable()->constrained('edu_curricula')->nullOnDelete();
            $t->string('status', 10)->default('active'); // active | inactive
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['foundation_id', 'ref']);
        });

        // Contracts last 6 months by default and can be renewed (a renewal is a new contract that follows the old one).
        Schema::create('edu_contracts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('foundation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('teacher_id')->constrained('edu_teachers')->cascadeOnDelete();
            $t->date('start_date');
            $t->date('end_date');
            $t->decimal('monthly_salary', 10, 2)->default(0);
            $t->string('status', 10)->default('active'); // active | ended
            $t->string('note', 300)->nullable();
            $t->timestamps();
            $t->index(['teacher_id', 'start_date']);
        });

        // One salary line per teacher per month (the "sweldo").
        Schema::create('edu_salaries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('foundation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('teacher_id')->constrained('edu_teachers')->cascadeOnDelete();
            $t->foreignId('contract_id')->nullable()->constrained('edu_contracts')->nullOnDelete();
            $t->char('month', 7);
            $t->decimal('amount', 10, 2);
            $t->string('method', 10)->default('cash');   // cash | bank
            $t->string('status', 10)->default('pending'); // pending | paid
            $t->timestamp('paid_at')->nullable();
            $t->unsignedBigInteger('recorded_by')->nullable();
            $t->timestamps();
            $t->unique(['teacher_id', 'month']);
            $t->index(['foundation_id', 'month']);
        });

        Schema::create('edu_progress', function (Blueprint $t) {
            $t->id();
            $t->foreignId('teacher_id')->constrained('edu_teachers')->cascadeOnDelete();
            $t->foreignId('unit_id')->constrained('edu_units')->cascadeOnDelete();
            $t->date('done_on');
            $t->timestamps();
            $t->unique(['teacher_id', 'unit_id']);
        });

        // The Education department: it replaces the empty "Teaching" one (accounts keep their department).
        foreach (DB::table('foundations')->pluck('id') as $fid) {
            $teaching = DB::table('departments')->where('foundation_id', $fid)->where('key', 'teaching')->first();
            $has = DB::table('departments')->where('foundation_id', $fid)->where('key', 'education')->exists();
            if ($teaching && ! $has) {
                DB::table('departments')->where('id', $teaching->id)->update(['key' => 'education', 'name_en' => 'Education', 'name_ar' => 'التعليم']);
            } elseif (! $has) {
                DB::table('departments')->insert(['foundation_id' => $fid, 'key' => 'education', 'name_en' => 'Education', 'name_ar' => 'التعليم', 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        foreach (['edu_progress', 'edu_salaries', 'edu_contracts', 'edu_teachers', 'edu_units', 'edu_curricula', 'edu_institutions'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
