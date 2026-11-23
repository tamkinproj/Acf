<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A photo or a voice message in a chat. The file is private (storage/app), only the chat's members can open it.
        Schema::table('chat_messages', function (Blueprint $t) {
            $t->string('file_kind', 10)->nullable();      // image | audio
            $t->string('file_path', 200)->nullable();
            $t->string('file_mime', 60)->nullable();
            $t->unsignedInteger('file_size')->nullable();
            $t->unsignedInteger('file_ms')->nullable();   // length of a voice message
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $t) {
            $t->dropColumn(['file_kind', 'file_path', 'file_mime', 'file_size', 'file_ms']);
        });
    }
};
