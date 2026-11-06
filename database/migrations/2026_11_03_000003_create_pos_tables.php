<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('starting_cash', 12, 0)->unsigned()->default(0);
            $table->decimal('ending_cash', 12, 0)->unsigned()->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'closed_at']);
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('invoice', 24)->unique();
            $table->foreignId('cashier_shift_id')->constrained()->restrictOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone', 30)->nullable();
            $table->string('transaction_type', 20)->default('takeaway');
            $table->string('status', 20)->default('pending');
            $table->string('kitchen_status', 20)->default('pending');
            $table->string('payment_method', 20)->nullable();
            $table->decimal('subtotal', 12, 0)->unsigned();
            $table->decimal('amount_paid', 12, 0)->unsigned()->default(0);
            $table->decimal('change_due', 12, 0)->unsigned()->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['kitchen_status', 'created_at']);
        });

        Schema::create('transaction_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_name');
            $table->decimal('price', 12, 0)->unsigned();
            $table->unsignedSmallInteger('quantity');
            $table->decimal('line_total', 12, 0)->unsigned();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_details');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('cashier_shifts');
    }
};
