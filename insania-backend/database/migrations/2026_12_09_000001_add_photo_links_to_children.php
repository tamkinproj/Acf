<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The links to the photos sent with the office's update form (Google Drive). Kept as text: the photos are not downloaded.
        Schema::table('children', function (Blueprint $t) {
            $t->text('photo_links')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('children', function (Blueprint $t) {
            $t->dropColumn('photo_links');
        });
    }
};
