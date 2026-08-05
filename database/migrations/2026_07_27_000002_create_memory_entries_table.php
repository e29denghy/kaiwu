<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memory_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_memory_source_id')->constrained()->cascadeOnDelete();
            $table->foreignId('todo_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_key', 64)->unique();
            $table->text('source_file');
            $table->string('source_heading');
            $table->timestamp('source_updated_at')->nullable();
            $table->string('outcome');
            $table->string('title');
            $table->text('summary')->nullable();
            $table->json('evidence')->nullable();
            $table->string('content_hash', 64);
            $table->boolean('is_current')->default(true);
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique('todo_id');
            $table->index(['project_memory_source_id', 'outcome', 'is_current'], 'memory_entries_source_outcome_current_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memory_entries');
    }
};
