<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 120)->unique();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();
            $table->index(['is_available', 'category_id']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('label', 120);
            $table->string('barcode', 120)->nullable()->unique();
            $table->decimal('selling_price', 12, 0)->unsigned();
            $table->decimal('minimum_quantity', 12, 3)->unsigned()->default(0);
            $table->boolean('is_available')->default(true);
            $table->timestamps();
            $table->unique(['product_id', 'label']);
            $table->index(['is_available', 'product_id']);
        });

        Schema::create('product_stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->string('batch_code', 64);
            $table->date('expires_at')->nullable();
            $table->decimal('unit_cost', 12, 0)->unsigned()->nullable();
            $table->timestamps();
            $table->unique(['product_variant_id', 'batch_code']);
            $table->index(['product_variant_id', 'expires_at']);
        });

        Schema::create('product_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_stock_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 3);
            $table->decimal('quantity', 12, 3)->unsigned();
            $table->decimal('unit_cost', 12, 0)->unsigned()->nullable();
            $table->decimal('cost_amount', 12, 0)->unsigned()->nullable();
            $table->string('description', 255)->nullable();
            $table->timestamps();
            $table->index(['product_stock_batch_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stock_movements');
        Schema::dropIfExists('product_stock_batches');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
    }
};
