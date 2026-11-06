<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('bank_name', 80);
            $table->string('account_number', 80);
            $table->string('account_name', 120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['bank_name', 'is_active']);
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->foreignId('bank_account_id')->nullable()->after('payment_method')
                ->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('bank_account_id');
        });

        Schema::dropIfExists('bank_accounts');
    }
};
