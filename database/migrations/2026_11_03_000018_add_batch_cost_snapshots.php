<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_stock_batches', function (Blueprint $table): void {
            $table->decimal('unit_cost', 21, 6)->nullable();
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->decimal('unit_cost', 21, 6)->nullable();
            $table->decimal('cost_amount', 15, 0)->nullable();
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->decimal('cogs_total', 15, 0)->unsigned()->nullable();
            $table->boolean('cogs_complete')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropColumn(['cogs_total', 'cogs_complete']);
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropColumn(['unit_cost', 'cost_amount']);
        });

        Schema::table('material_stock_batches', function (Blueprint $table): void {
            $table->dropColumn('unit_cost');
        });
    }
};
