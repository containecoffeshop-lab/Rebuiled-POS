<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_details', function (Blueprint $table) {
            $table->string('item_type', 16)->default('menu')->after('menu_id');
            $table->foreignId('product_variant_id')->nullable()->after('menu_id')->constrained()->restrictOnDelete();
            $table->index(['item_type', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::table('transaction_details', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
            $table->dropIndex(['item_type', 'product_variant_id']);
            $table->dropColumn(['item_type', 'product_variant_id']);
        });
    }
};
