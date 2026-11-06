<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_audit_tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('task_code', 32)->unique();
            $table->foreignId('shift1_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('shift2_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 24)->default('awaiting_shift2');
            $table->text('note')->nullable();
            $table->timestamp('shift1_completed_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('stock_audit_task_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_audit_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete();
            $table->string('material_name', 120);
            $table->string('unit_name', 32);
            $table->decimal('system_quantity', 12, 3)->unsigned();
            $table->decimal('shift1_quantity', 12, 3)->unsigned();
            $table->decimal('shift2_quantity', 12, 3)->unsigned()->nullable();
            $table->string('shift1_note', 500)->nullable();
            $table->string('shift2_note', 500)->nullable();
            $table->timestamps();
            $table->unique(['stock_audit_task_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_audit_task_items');
        Schema::dropIfExists('stock_audit_tasks');
    }
};
