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
        Schema::create('recurrings', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->text('description')->nullable();
            $table->float('amount');
            $table->string('frequency');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('last_payment_date')->nullable();
            $table->date('next_payment_date')->nullable();
            $table->foreignId('ledger_id')->constrained('ledgers')->onDelete('cascade');
            $table->foreignId('from_ledger_id')->nullable()->constrained('ledgers')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurrings');
    }
};
