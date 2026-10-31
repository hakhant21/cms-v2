<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The transcript of a live chat. `sender` is what both sides render from: a
 * visitor bubble, an agent bubble or a system line such as "chat closed".
 * Agent messages keep the user who wrote them so the transcript stays honest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('chat_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('sender', 10); // visitor | agent | system
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');

            $table->timestamps();

            // The transcript is always read in order, and unread counts are
            // asked per conversation, so both live in the same composite index.
            $table->index(['chat_conversation_id', 'id']);
            $table->index(['chat_conversation_id', 'sender']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
