<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Office chat: one General group for the office, and one-to-one chats. Messages hold text and/or a link to a card (never a copy of its data). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('foundation_id')->constrained()->cascadeOnDelete();
            $t->string('type', 10);                       // general | direct
            $t->string('pair_key', 40);                   // "general" or "low-high" user ids
            $t->string('title', 120)->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamp('last_message_at')->nullable();
            $t->timestamps();
            $t->unique(['foundation_id', 'pair_key']);
        });

        Schema::create('chat_members', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('last_read_id')->default(0);
            $t->timestamps();
            $t->unique(['conversation_id', 'user_id']);
        });

        Schema::create('chat_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('foundation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('body')->nullable();
            $t->string('card_kind', 20)->nullable();      // child | application | project | payment | user | report | site
            $t->unsignedBigInteger('card_id')->nullable();
            $t->string('card_extra', 20)->nullable();     // a month (YYYY-MM) for payment and report cards
            $t->timestamp('created_at')->useCurrent();
            $t->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_members');
        Schema::dropIfExists('chat_conversations');
    }
};
