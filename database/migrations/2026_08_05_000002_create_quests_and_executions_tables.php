<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('todo_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('goal');
            $table->json('acceptance_criteria');
            $table->json('constraints')->nullable();
            $table->json('verification')->nullable();
            $table->string('risk_level')->default('medium');
            $table->boolean('requires_write')->default(true);
            $table->string('approval_status')->default('pending');
            $table->string('execution_mode')->default('immediate');
            $table->timestamp('scheduled_for')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['approval_status', 'status']);
            $table->index(['execution_mode', 'scheduled_for']);
        });

        Schema::create('quest_executions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('harness_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('attempt');
            $table->string('dispatch_id')->unique();
            $table->string('status')->default('queued');
            $table->text('outbox_path')->nullable();
            $table->longText('result')->nullable();
            $table->longText('error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['quest_id', 'attempt']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quest_executions');
        Schema::dropIfExists('quests');
    }
};
