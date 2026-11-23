<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The shape of a voice message (how loud it was over time), measured while recording, so every phone draws the same waveform.
        Schema::table('chat_messages', function (Blueprint $t) {
            $t->string('file_peaks', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $t) {
            $t->dropColumn('file_peaks');
        });
    }
};
