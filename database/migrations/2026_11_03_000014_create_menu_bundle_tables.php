<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->boolean('is_bundle')->default(false)->index();
        });

        Schema::create('menu_bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_menu_id')->constrained('menus')->cascadeOnDelete();
            $table->foreignId('component_menu_id')->constrained('menus')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['bundle_menu_id', 'component_menu_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_bundle_items');
        Schema::table('menus', function (Blueprint $table) {
            $table->dropColumn('is_bundle');
        });
    }
};
