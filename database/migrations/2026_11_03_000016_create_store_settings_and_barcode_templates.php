<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('barcode_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('paper_type', 10);
            $table->decimal('paper_width_mm', 8, 2);
            $table->decimal('paper_height_mm', 8, 2)->nullable();
            $table->decimal('label_width_mm', 8, 2);
            $table->decimal('label_height_mm', 8, 2);
            $table->decimal('gap_horizontal_mm', 8, 2)->default(0);
            $table->decimal('gap_vertical_mm', 8, 2)->default(0);
            $table->decimal('margin_top_mm', 8, 2)->default(0);
            $table->decimal('margin_left_mm', 8, 2)->default(0);
            $table->unsignedSmallInteger('columns')->default(1);
            $table->unsignedSmallInteger('rows')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barcode_templates');
        Schema::dropIfExists('store_settings');
    }
};
