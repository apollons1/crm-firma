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
        Schema::table('email_logs', function (Blueprint $table) {
            $table->string('from')->nullable()->after('google_token_id');
        });

        // sent_by_user_id nu are sens pentru emailurile primite (direction=received),
        // deci trebuie să devină opțional. change() e nativ în Laravel 11+ (fără
        // doctrine/dbal) și portabil MySQL/SQLite — FK-ul existent rămâne intact,
        // doar nullability-ul coloanei se schimbă.
        Schema::table('email_logs', function (Blueprint $table) {
            $table->foreignId('sent_by_user_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->foreignId('sent_by_user_id')->nullable(false)->change();
            $table->dropColumn('from');
        });
    }
};
