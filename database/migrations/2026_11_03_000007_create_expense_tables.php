<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->timestamps();
        });

        Schema::create('expense_subcategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->timestamps();
            $table->unique(['expense_category_id', 'name']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_code', 40)->unique();
            $table->string('reference_number', 120)->nullable();
            $table->date('expense_date');
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('expense_subcategory_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 12, 0)->unsigned();
            $table->string('payment_status', 20)->default('unpaid');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index(['expense_date', 'payment_status']);
            $table->index(['expense_category_id', 'expense_date']);
        });

        Schema::create('expense_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->date('paid_at');
            $table->decimal('amount', 12, 0)->unsigned();
            $table->string('payment_method', 20);
            $table->timestamps();
            $table->index(['expense_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_payments');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_subcategories');
        Schema::dropIfExists('expense_categories');
    }
};
