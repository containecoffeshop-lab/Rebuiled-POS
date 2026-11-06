<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->decimal('additional_price', 12, 0)->unsigned()->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['menu_id', 'name']);
        });

        Schema::create('menu_variant_recipes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 3)->unsigned();
            $table->timestamps();
            $table->unique(['menu_variant_id', 'material_id']);
        });

        Schema::table('transaction_details', function (Blueprint $table): void {
            $table->foreignId('menu_variant_id')->nullable()->after('menu_id')->constrained()->nullOnDelete();
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->string('transaction_kind', 32)->default('sale')->after('invoice');
            $table->foreignId('employee_id')->nullable()->after('customer_id')->constrained('users')->nullOnDelete();
            $table->index(['transaction_kind', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropIndex(['transaction_kind', 'status', 'created_at']);
            $table->dropConstrainedForeignId('employee_id');
            $table->dropColumn('transaction_kind');
        });

        Schema::table('transaction_details', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('menu_variant_id');
        });

        Schema::dropIfExists('menu_variant_recipes');
        Schema::dropIfExists('menu_variants');
    }
};
