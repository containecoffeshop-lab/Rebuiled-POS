<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
            $table->index('name');
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_code', 40)->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->date('purchase_date');
            $table->string('status', 20)->default('pending');
            $table->string('payment_status', 20)->default('unpaid');
            $table->decimal('subtotal', 12, 0)->unsigned();
            $table->string('discount_type', 20)->nullable();
            $table->decimal('discount_value', 12, 0)->unsigned()->default(0);
            $table->decimal('discount', 12, 0)->unsigned()->default(0);
            $table->decimal('grand_total', 12, 0)->unsigned();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'purchase_date']);
            $table->index(['payment_status', 'purchase_date']);
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->string('material_name', 120);
            $table->string('unit_name', 32);
            $table->decimal('quantity', 12, 3)->unsigned();
            $table->decimal('unit_cost', 12, 0)->unsigned();
            $table->decimal('line_total', 12, 0)->unsigned();
            $table->date('expires_at')->nullable();
            $table->timestamps();
            $table->index(['purchase_id', 'material_id']);
        });

        Schema::create('purchase_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->date('paid_at');
            $table->decimal('amount', 12, 0)->unsigned();
            $table->string('payment_method', 20);
            $table->timestamps();
            $table->index(['purchase_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_payments');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('suppliers');
    }
};
