<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('name')->unique();
            $table->decimal('minimum_quantity', 12, 3)->unsigned()->default(0);
            $table->timestamps();
        });

        Schema::create('menu_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 3)->unsigned();
            $table->timestamps();
            $table->unique(['menu_id', 'material_id']);
        });

        Schema::create('material_stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->string('batch_code', 64);
            $table->date('expires_at')->nullable();
            $table->timestamps();
            $table->index(['material_id', 'expires_at']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_stock_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 3);
            $table->decimal('quantity', 12, 3)->unsigned();
            $table->string('description', 255)->nullable();
            $table->timestamps();
            $table->index(['material_stock_batch_id', 'type']);
        });

        Schema::create('transaction_recipe_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_detail_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->string('material_name');
            $table->string('unit_name', 32);
            $table->decimal('quantity', 12, 3)->unsigned();
            $table->timestamps();
            $table->unique(['transaction_detail_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_recipe_details');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('material_stock_batches');
        Schema::dropIfExists('menu_recipes');
        Schema::dropIfExists('materials');
        Schema::dropIfExists('units');
    }
};
