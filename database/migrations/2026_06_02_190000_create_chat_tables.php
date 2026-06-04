<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('guest_token', 64)->unique();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('visitor_name')->nullable();
            $table->string('visitor_email')->nullable();
            $table->string('tracking_code', 32)->nullable();
            $table->string('status', 20)->default('open');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedInteger('unread_staff_count')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->string('sender_type', 20);
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
    }
};
