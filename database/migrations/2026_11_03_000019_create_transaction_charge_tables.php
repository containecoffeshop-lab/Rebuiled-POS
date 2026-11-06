<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_charges', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 8);
            $table->string('name', 120);
            $table->string('value', 24);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->index(['code', 'is_active']);
        });

        Schema::create('transaction_charges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->string('code', 8);
            $table->string('name', 120);
            $table->decimal('amount', 12, 0)->unsigned();
            $table->timestamps();
            $table->index(['transaction_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_charges');
        Schema::dropIfExists('store_charges');
    }
};
