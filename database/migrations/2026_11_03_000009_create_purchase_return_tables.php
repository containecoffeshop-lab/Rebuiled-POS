<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_code', 40)->unique();
            $table->foreignId('purchase_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->date('return_date');
            $table->string('settlement_method', 24);
            $table->string('status', 20)->default('pending');
            $table->decimal('amount', 12, 0)->unsigned()->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'return_date']);
            $table->index(['purchase_id', 'status']);
        });

        Schema::create('purchase_return_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 3)->unsigned();
            $table->decimal('amount', 12, 0)->unsigned()->default(0);
            $table->date('replacement_expires_at')->nullable();
            $table->string('reason', 255);
            $table->timestamps();
            $table->index(['purchase_item_id', 'purchase_return_id'], 'purchase_return_item_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_details');
        Schema::dropIfExists('purchase_returns');
    }
};
