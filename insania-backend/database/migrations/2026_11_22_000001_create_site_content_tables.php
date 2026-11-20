<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The public landing page's content: one working draft, and the version visitors see.
        Schema::create('site_contents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('foundation_id')->constrained()->cascadeOnDelete()->unique();
            $t->longText('draft')->nullable();
            $t->longText('published')->nullable();
            $t->unsignedInteger('version')->default(0);
            $t->timestamp('published_at')->nullable();
            $t->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        // Every publish is kept, so a mistake can be brought back into the draft and published again.
        Schema::create('site_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('foundation_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->longText('content');
            $t->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['foundation_id', 'version']);
        });

        Schema::create('site_images', function (Blueprint $t) {
            $t->id();
            $t->foreignId('foundation_id')->constrained()->cascadeOnDelete();
            $t->string('filename', 80);
            $t->string('mime', 40);
            $t->unsignedSmallInteger('width');
            $t->unsignedSmallInteger('height');
            $t->unsignedInteger('bytes');
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_images');
        Schema::dropIfExists('site_versions');
        Schema::dropIfExists('site_contents');
    }
};
