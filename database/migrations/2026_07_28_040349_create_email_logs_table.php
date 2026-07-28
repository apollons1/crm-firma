<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('google_token_id')
                ->nullable()
                ->constrained('google_tokens')
                ->onDelete('set null');
            $table->foreignId('sent_by_user_id')->constrained('users');
            $table->foreignId('opportunity_id')
                ->nullable()
                ->constrained('opportunities')
                ->onDelete('set null');
            $table->foreignId('client_id')
                ->nullable()
                ->constrained('clients')
                ->onDelete('set null');
            $table->foreignId('contact_id')
                ->nullable()
                ->constrained('contacts')
                ->onDelete('set null');
            $table->string('to');
            $table->string('cc')->nullable();
            $table->string('subject');
            $table->text('body');
            $table->string('gmail_message_id')->nullable();
            $table->enum('direction', ['sent', 'received']);
            $table->enum('status', ['sent', 'failed', 'pending'])->default('pending');
            $table->text('error_message')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
