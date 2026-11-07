<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashier_shifts', function (Blueprint $table): void {
            $table->unsignedTinyInteger('shift_number')->nullable();
        });

        Schema::create('cycle_count_tasks', function (Blueprint $table): void {
            $table->id();
            $table->date('task_date');
            $table->unsignedTinyInteger('shift_number');
            $table->string('status', 20)->default('pending');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('counted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('cashier_shift_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('counted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['task_date', 'shift_number', 'status']);
        });

        Schema::create('cycle_count_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cycle_count_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->string('material_name');
            $table->string('unit_name');
            $table->decimal('system_quantity', 12, 3)->nullable();
            $table->decimal('counted_quantity', 12, 3)->nullable();
            $table->decimal('difference', 13, 3)->nullable();
            $table->decimal('stock_before_approval', 12, 3)->nullable();
            $table->decimal('stock_after_approval', 12, 3)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->unique(['cycle_count_task_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_count_items');
        Schema::dropIfExists('cycle_count_tasks');
        Schema::table('cashier_shifts', function (Blueprint $table): void {
            $table->dropColumn('shift_number');
        });
    }
};
