<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setup_state', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->boolean('is_complete')->default(false);
        });

        DB::table('setup_state')->insert(['id' => 1, 'is_complete' => false]);
    }

    public function down(): void
    {
        Schema::dropIfExists('setup_state');
    }
};
