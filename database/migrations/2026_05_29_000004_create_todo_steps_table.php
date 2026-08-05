<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('todo_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('todo_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('execution_type')->default('Human');
            $table->string('status')->default('pending');
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('ai_prompt')->nullable();
            $table->longText('ai_result')->nullable();
            $table->boolean('requires_human_confirmation')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['execution_type', 'status']);
            $table->index(['todo_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('todo_steps');
    }
};
