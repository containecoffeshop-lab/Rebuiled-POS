<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('route_name', 160)->nullable()->index();
            $table->string('method', 10);
            $table->unsignedSmallInteger('response_status');
            $table->string('subject_type', 160)->nullable();
            $table->string('subject_id', 80)->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();
            $table->index(['created_at', 'user_id']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
