<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->onDelete('set null');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('estimated_value', 12, 2)->nullable();
            $table->string('currency')->default('RON');
            $table->enum('status', ['lead', 'qualified', 'proposal', 'negotiation', 'won', 'lost'])->default('lead');
            $table->unsignedTinyInteger('probability')->default(0);
            $table->date('expected_close_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
