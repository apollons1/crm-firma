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
        Schema::create('system_alerts', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['downtime', 'high_cpu', 'failed_backup', 'security_threat']);
            $table->enum('severity', ['info', 'warning', 'critical']);
            $table->text('message');
            $table->json('metadata')->nullable();
            $table->timestamp('triggered_at');
            $table->timestamp('resolved_at')->nullable();
            $table->json('notified_users')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('severity');
            $table->index('triggered_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_alerts');
    }
};
