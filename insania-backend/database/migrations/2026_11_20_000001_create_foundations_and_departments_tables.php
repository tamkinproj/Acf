<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Replaces the old `schools` table: the organisation every record belongs to.
        Schema::create('foundations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('slug')->unique();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('foundation_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('name_en');
            $table->string('name_ar')->nullable();
            $table->timestamps();

            $table->unique(['foundation_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
        Schema::dropIfExists('foundations');
    }
};
