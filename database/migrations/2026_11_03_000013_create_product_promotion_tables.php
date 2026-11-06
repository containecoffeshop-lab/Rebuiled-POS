<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('discount_type', 20);
            $table->decimal('discount_value', 12, 0)->unsigned();
            $table->unsignedSmallInteger('minimum_quantity')->default(1);
            $table->boolean('all_menus')->default(true);
            $table->boolean('all_customers')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('product_promotion_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['product_promotion_id', 'menu_id']);
        });

        Schema::create('product_promotion_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['product_promotion_id', 'customer_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('promotion_discount', 12, 0)->unsigned()->default(0)->after('discount');
            $table->decimal('coupon_discount', 12, 0)->unsigned()->default(0)->after('promotion_discount');
        });

        Schema::table('transaction_details', function (Blueprint $table) {
            $table->decimal('promotion_discount', 12, 0)->unsigned()->default(0)->after('line_total');
            $table->string('promotion_name', 120)->nullable()->after('promotion_discount');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_details', function (Blueprint $table) {
            $table->dropColumn(['promotion_discount', 'promotion_name']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['promotion_discount', 'coupon_discount']);
        });

        Schema::dropIfExists('product_promotion_customers');
        Schema::dropIfExists('product_promotion_menus');
        Schema::dropIfExists('product_promotions');
    }
};
