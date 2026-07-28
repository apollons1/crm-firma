<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE email_logs MODIFY status ENUM('sent', 'received', 'failed', 'pending') NOT NULL DEFAULT 'pending'"
        );

        // Backfill: emailurile primite deja sincronizate foloseau 'sent' ca
        // să însemne "procesat cu succes" — le mutăm pe noua valoare dedicată.
        DB::table('email_logs')
            ->where('direction', 'received')
            ->where('status', 'sent')
            ->update(['status' => 'received']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('email_logs')
            ->where('direction', 'received')
            ->where('status', 'received')
            ->update(['status' => 'sent']);

        DB::statement(
            "ALTER TABLE email_logs MODIFY status ENUM('sent', 'failed', 'pending') NOT NULL DEFAULT 'pending'"
        );
    }
};
