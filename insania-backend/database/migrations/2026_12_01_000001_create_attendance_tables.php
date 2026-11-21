<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The office's employees. Linked to a sign-in account when they clock in from their own phone.
        Schema::create('att_employees', function (Blueprint $t) {
            $t->id();
            $t->foreignId('foundation_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('employee_no', 30)->nullable();
            $t->string('name_en', 150);
            $t->string('name_ar', 150)->nullable();
            $t->string('position', 100)->nullable();
            $t->string('department', 100)->nullable();
            $t->string('phone', 40)->nullable();
            $t->date('hired_on')->nullable();
            $t->decimal('monthly_salary', 12, 2)->default(0);
            $t->string('currency', 3)->default('PHP');
            $t->string('shift_start', 5)->default('08:00');
            $t->string('shift_end', 5)->default('17:00');
            $t->unsignedSmallInteger('break_minutes')->default(60);       // unpaid break inside the shift
            $t->unsignedSmallInteger('grace_minutes')->default(0);        // up to this many minutes late costs nothing
            $t->string('work_days', 20)->default('1,2,3,4,5');            // 1 = Monday ... 7 = Sunday
            $t->string('face_path')->nullable();                          // the reference face photo, private
            $t->string('status', 10)->default('active');                  // active | ended
            $t->boolean('is_sample')->default(false);
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['foundation_id', 'status']);
            $t->unique(['user_id']);
        });

        // One row per employee per day they clocked, or that a manager marked.
        Schema::create('att_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('foundation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained('att_employees')->cascadeOnDelete();
            $t->date('work_date');
            $t->dateTime('clock_in_at')->nullable();                      // stored in UTC
            $t->dateTime('clock_out_at')->nullable();
            $t->string('in_photo')->nullable();
            $t->string('out_photo')->nullable();
            $t->string('in_method', 10)->nullable();                      // face | manual
            $t->string('out_method', 10)->nullable();
            $t->string('status', 10)->default('present');                 // present | absent | excused
            $t->unsignedSmallInteger('late_minutes')->default(0);
            $t->unsignedSmallInteger('early_minutes')->default(0);
            $t->unsignedSmallInteger('overtime_minutes')->default(0);
            $t->string('note', 300)->nullable();
            $t->unsignedBigInteger('edited_by')->nullable();
            $t->boolean('is_sample')->default(false);
            $t->timestamps();
            $t->unique(['employee_id', 'work_date']);
            $t->index(['foundation_id', 'work_date']);
        });

        Schema::create('att_leaves', function (Blueprint $t) {
            $t->id();
            $t->foreignId('foundation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained('att_employees')->cascadeOnDelete();
            $t->date('start_date');
            $t->date('end_date');
            $t->string('kind', 12)->default('annual');                    // annual | sick | emergency | unpaid | other
            $t->boolean('paid')->default(true);
            $t->string('reason', 300)->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->boolean('is_sample')->default(false);
            $t->timestamps();
            $t->index(['foundation_id', 'start_date']);
        });

        Schema::create('att_holidays', function (Blueprint $t) {
            $t->id();
            $t->foreignId('foundation_id')->constrained()->cascadeOnDelete();
            $t->date('holiday_date');
            $t->string('name_en', 100);
            $t->string('name_ar', 100)->nullable();
            $t->boolean('is_sample')->default(false);
            $t->timestamps();
            $t->unique(['foundation_id', 'holiday_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_holidays');
        Schema::dropIfExists('att_leaves');
        Schema::dropIfExists('att_records');
        Schema::dropIfExists('att_employees');
    }
};
