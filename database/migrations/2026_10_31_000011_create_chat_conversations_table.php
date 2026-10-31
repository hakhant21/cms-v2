<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per live chat conversation started from the public site.
 *
 * `token` is the conversation's credential: the visitor keeps it in the
 * session and subscribes to the broadcast channel named after it, so a guest
 * can receive replies without an account while nobody else can guess the
 * channel. A row is only created when the visitor actually starts chatting,
 * so ordinary page views leave the table empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();

            $table->string('token', 64)->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('locale', 8)->nullable(); // language the visitor was browsing in

            $table->string('status')->default('open'); // open | closed
            $table->timestamp('last_message_at')->nullable();

            // Two counters instead of read timestamps: each side only ever asks
            // "how many are waiting for me", and a badge must not run a subquery
            // per row in the list. They move together with every message.
            $table->unsignedInteger('agent_unread')->default(0);
            $table->unsignedInteger('visitor_unread')->default(0);
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};
