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
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->enum('status', ['queued', 'sent', 'delivered', 'read', 'failed', 'received'])
                ->default('queued')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->enum('status', ['queued', 'sent', 'delivered', 'read', 'failed'])
                ->default('queued')
                ->change();
        });
    }
};
