<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->enum('status', ['sent', 'received', 'failed', 'pending'])->default('pending')->change();
        });

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

        Schema::table('email_logs', function (Blueprint $table) {
            $table->enum('status', ['sent', 'failed', 'pending'])->default('pending')->change();
        });
    }
};
