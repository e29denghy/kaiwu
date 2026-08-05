<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_memory_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('scope_key', 64)->unique();
            $table->text('scope_cwd');
            $table->string('discovered_name');
            $table->text('registry_path');
            $table->string('status')->default('pending');
            $table->string('content_hash', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_memory_sources');
    }
};
