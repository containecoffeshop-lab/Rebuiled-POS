<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('loyalty_points_earned')->default(0);
            $table->unsignedBigInteger('loyalty_points_redeemed')->default(0);
            $table->decimal('loyalty_discount', 12, 0)->unsigned()->default(0);
        });

        Schema::create('loyalty_point_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('transaction_return_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('entry_type', 24);
            $table->bigInteger('points');
            $table->string('description', 255)->nullable();
            $table->timestamps();
            $table->unique(['transaction_return_id', 'entry_type']);
            $table->index(['transaction_id', 'entry_type']);
            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_point_entries');

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['loyalty_points_earned', 'loyalty_points_redeemed', 'loyalty_discount']);
        });
    }
};
