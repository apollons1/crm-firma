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
        // TEXT e limitat la ~64KB în MySQL — insuficient pentru emailuri HTML
        // reale (newslettere, marketing) care pot depăși ușor pragul ăsta.
        DB::statement('ALTER TABLE email_logs MODIFY body LONGTEXT NOT NULL');
        DB::statement('ALTER TABLE email_logs MODIFY error_message LONGTEXT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE email_logs MODIFY body TEXT NOT NULL');
        DB::statement('ALTER TABLE email_logs MODIFY error_message TEXT NULL');
    }
};
