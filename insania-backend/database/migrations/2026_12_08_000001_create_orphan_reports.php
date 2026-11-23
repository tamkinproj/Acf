<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The yearly report a partner asks for (Dar Al Ber's periodic orphan report), one per orphan per year.
        // Everything else on the report comes from the orphan's record when the PDF is made; this keeps only what is
        // written for that year: the person responsible (it can change, e.g. a mother working abroad), the choices
        // that differ from the record, and the three texts.
        Schema::create('orphan_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('foundation_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('partner_id')->nullable()->index();
            $t->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $t->unsignedSmallInteger('year');
            $t->string('responsible_name', 150)->nullable();
            $t->string('responsible_relation', 60)->nullable();
            $t->json('choices')->nullable();
            $t->text('changes')->nullable();
            $t->text('comment')->nullable();
            $t->text('message')->nullable();
            $t->string('codes', 120)->nullable();      // which written variants the texts came from
            $t->string('status', 12)->default('draft');   // draft | approved
            $t->unsignedBigInteger('approved_by')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
            $t->unique(['child_id', 'year']);
            $t->index(['foundation_id', 'partner_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orphan_reports');
    }
};
