<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('return_code', 40)->unique();
            $table->date('return_date');
            $table->string('refund_method', 20);
            $table->string('status', 20)->default('pending');
            $table->decimal('refund_amount', 12, 0)->unsigned()->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['transaction_id', 'status']);
            $table->index(['return_date', 'status', 'refund_method']);
        });

        Schema::create('transaction_return_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_detail_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->decimal('refund_amount', 12, 0)->unsigned()->default(0);
            $table->string('reason', 255);
            $table->timestamps();
            $table->index('transaction_detail_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_return_details');
        Schema::dropIfExists('transaction_returns');
    }
};
