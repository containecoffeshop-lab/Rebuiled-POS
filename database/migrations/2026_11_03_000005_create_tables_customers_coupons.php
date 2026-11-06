<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable()->unique();
            $table->string('address')->nullable();
            $table->timestamps();
        });

        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->unsignedSmallInteger('capacity')->default(2);
            $table->uuid('qr_token')->unique();
            $table->string('status', 20)->default('available');
            $table->timestamps();
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('type', 20);
            $table->decimal('value', 12, 0)->unsigned();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('cashier_shift_id')->constrained()->nullOnDelete();
            $table->foreignId('dining_table_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            $table->foreignId('coupon_id')->nullable()->after('dining_table_id')->constrained()->nullOnDelete();
            $table->string('coupon_code', 40)->nullable()->after('coupon_id');
            $table->decimal('discount', 12, 0)->unsigned()->default(0)->after('subtotal');
            $table->decimal('grand_total', 12, 0)->unsigned()->default(0)->after('discount');
            $table->index(['customer_id', 'created_at']);
        });

        DB::table('transactions')->where('grand_total', 0)->update(['grand_total' => DB::raw('subtotal')]);
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['customer_id', 'created_at']);
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropConstrainedForeignId('dining_table_id');
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn(['coupon_code', 'discount', 'grand_total']);
        });

        Schema::dropIfExists('coupons');
        Schema::dropIfExists('dining_tables');
        Schema::dropIfExists('customers');
    }
};
