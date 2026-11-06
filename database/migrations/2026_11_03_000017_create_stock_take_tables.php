<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_takes', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 50)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('checked_at');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index('checked_at');
        });

        Schema::create('stock_take_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_take_id')->constrained()->restrictOnDelete();
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete();
            $table->string('material_name');
            $table->string('unit_name', 32);
            $table->decimal('system_quantity', 12, 3)->unsigned();
            $table->decimal('counted_quantity', 12, 3)->unsigned();
            $table->decimal('difference', 12, 3);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['stock_take_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_take_details');
        Schema::dropIfExists('stock_takes');
    }
};
