<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Group chats reuse the conversation table (type "group"). Every conversation can have a cover photo and a colour theme.
        Schema::table('chat_conversations', function (Blueprint $t) {
            $t->string('cover_path', 190)->nullable()->after('title');
            $t->unsignedBigInteger('cover_v')->nullable()->after('cover_path');
            $t->string('theme', 20)->nullable()->after('cover_v');
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $t) {
            $t->dropColumn(['cover_path', 'cover_v', 'theme']);
        });
    }
};
