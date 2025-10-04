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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_id')->constrained('ledgers')->onDelete('cascade');
            $table->foreignId('from_ledger_id')->nullable()->constrained('ledgers')->onDelete('set null');
            $table->datetime('occurred_at')->default(now());
            $table->string('type');
            $table->string('description')->nullable();
            $table->float('amount');
            $table->foreignId('recurring_id')->nullable()->constrained('recurrings')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
