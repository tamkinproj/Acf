<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Partner sponsors such as Dar Al Ber (the old `sponsors`, now tied to a foundation, not a school).
        Schema::create('sponsors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('foundation_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('organization')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // The old `orphan_sponsorships`. A null sponsor means a direct donor.
        Schema::create('sponsorships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('foundation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sponsor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 20)->default('direct');
            $table->string('status', 20)->default('pending');
            $table->string('pay_type', 10)->default('bank');
            $table->decimal('monthly_amount', 10, 2)->default(0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->index(['foundation_id', 'status']);
        });

        // The old `sponsorship_payments`, now one row per sponsorship per month.
        Schema::create('sponsorship_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('foundation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sponsorship_id')->constrained()->cascadeOnDelete();
            $table->char('month', 7);
            $table->decimal('amount', 10, 2);
            $table->string('method', 10);
            $table->string('status', 10)->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();

            $table->unique(['sponsorship_id', 'month']);
            $table->index(['foundation_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsorship_payments');
        Schema::dropIfExists('sponsorships');
        Schema::dropIfExists('sponsors');
    }
};
