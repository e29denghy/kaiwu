<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('todos', function (Blueprint $table): void {
            $table->foreignId('project_module_id')
                ->nullable()
                ->after('project_id')
                ->constrained('project_modules')
                ->nullOnDelete();
            $table->string('planning_state')->default('backlog')->after('status');
            $table->date('scheduled_for')->nullable()->after('due_at');
            $table->unsignedTinyInteger('focus_rank')->nullable()->after('scheduled_for');

            $table->index(['planning_state', 'scheduled_for', 'focus_rank'], 'todos_daily_planning_index');
            $table->index(['project_id', 'project_module_id', 'planning_state'], 'todos_module_planning_index');
        });

        // Existing dated memory snapshots remain visible, but leave the daily backlog.
        foreach (DB::table('memory_entries')->whereNotNull('todo_id')->get(['todo_id', 'source_heading']) as $entry) {
            if (preg_match('/(?:20\d{2}-\d{1,2}-\d{1,2}|20\d{2}年\d{1,2}月\d{1,2}日)/u', (string) $entry->source_heading)) {
                DB::table('todos')
                    ->where('id', $entry->todo_id)
                    ->where('planning_state', 'backlog')
                    ->update(['planning_state' => 'archive', 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('todos', function (Blueprint $table): void {
            $table->dropIndex('todos_daily_planning_index');
            $table->dropIndex('todos_module_planning_index');
            $table->dropForeign(['project_module_id']);
            $table->dropColumn([
                'project_module_id',
                'planning_state',
                'scheduled_for',
                'focus_rank',
            ]);
        });
    }
};
