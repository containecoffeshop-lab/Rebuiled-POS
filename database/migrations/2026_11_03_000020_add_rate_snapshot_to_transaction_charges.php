<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_charges', function (Blueprint $table): void {
            $table->string('rate', 24)->default('');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_charges', function (Blueprint $table): void {
            $table->dropColumn('rate');
        });
    }
};
