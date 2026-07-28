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
        Schema::table('google_tokens', function (Blueprint $table) {
            $table->boolean('auto_associate')->default(true)->after('scopes');
            $table->boolean('mark_as_read')->default(false)->after('auto_associate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('google_tokens', function (Blueprint $table) {
            $table->dropColumn(['auto_associate', 'mark_as_read']);
        });
    }
};
