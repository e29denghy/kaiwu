<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('harness_connections', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('driver')->default('jsonl');
            $table->string('status')->default('active');
            $table->json('configuration')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index(['driver', 'status']);
        });

        Schema::create('harness_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('harness_connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id');
            $table->string('event_type');
            $table->string('status')->nullable();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['harness_connection_id', 'external_id']);
            $table->index(['project_id', 'occurred_at']);
            $table->index(['event_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harness_events');
        Schema::dropIfExists('harness_connections');
    }
};
