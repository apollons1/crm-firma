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
            $table->string('from')->nullable()->after('google_token_id');
        });

        // sent_by_user_id nu are sens pentru emailurile primite (direction=received),
        // deci trebuie să devină opțional. Fără doctrine/dbal, facem modificarea prin
        // SQL brut: scoatem FK-ul, relaxăm coloana, apoi îl punem la loc.
        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropForeign(['sent_by_user_id']);
        });

        DB::statement('ALTER TABLE email_logs MODIFY sent_by_user_id BIGINT UNSIGNED NULL');

        Schema::table('email_logs', function (Blueprint $table) {
            $table->foreign('sent_by_user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropForeign(['sent_by_user_id']);
        });

        DB::statement('ALTER TABLE email_logs MODIFY sent_by_user_id BIGINT UNSIGNED NOT NULL');

        Schema::table('email_logs', function (Blueprint $table) {
            $table->foreign('sent_by_user_id')->references('id')->on('users');
            $table->dropColumn('from');
        });
    }
};
