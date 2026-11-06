<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->string('purchase_type', 16)->default('materials')->after('purchase_code');
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable()->after('material_id')->constrained()->restrictOnDelete();
            $table->string('item_type', 16)->default('material')->after('product_variant_id');
            $table->foreignId('material_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
            $table->dropColumn(['product_variant_id', 'item_type']);
            $table->foreignId('material_id')->nullable(false)->change();
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn('purchase_type');
        });
    }
};
