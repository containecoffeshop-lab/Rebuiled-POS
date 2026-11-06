<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashier_shifts', function (Blueprint $table): void {
            $table->decimal('expected_cash', 15, 0)->unsigned()->nullable();
            $table->decimal('cash_difference', 16, 0)->nullable();
            $table->text('closing_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cashier_shifts', function (Blueprint $table): void {
            $table->dropColumn(['expected_cash', 'cash_difference', 'closing_note']);
        });
    }
};
