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
        // TEXT e limitat la ~64KB în MySQL — insuficient pentru emailuri HTML
        // reale (newslettere, marketing) care pot depăși ușor pragul ăsta.
        Schema::table('email_logs', function (Blueprint $table) {
            $table->longText('body')->change();
            $table->longText('error_message')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->text('body')->change();
            $table->text('error_message')->nullable()->change();
        });
    }
};
