<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Reminder;
use App\Models\Todo;
use App\Models\TodoStep;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_todo_creation_creates_a_single_deadline_reminder_and_completion_closes_it(): void
    {
        $workspace = Workspace::create(['name' => '工作', 'slug' => 'work']);
        $project = Project::create([
            'workspace_id' => $workspace->id,
            'name' => 'P0 项目',
            'slug' => 'p0-project',
            'priority' => 'P0',
            'status' => 'active',
        ]);

        $this->post('/todos', [
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'title' => '完成发布检查',
            'description' => '确认发布前的待办事项。',
            'priority' => 'P0',
            'status' => 'pending',
            'due_at' => now()->addDay()->toDateTimeString(),
        ])->assertRedirect();

        $todo = Todo::firstOrFail();

        $this->assertDatabaseHas('reminders', [
            'todo_id' => $todo->id,
            'status' => 'pending',
            'channel' => 'system',
        ]);

        $this->patch("/todos/{$todo->id}/status", ['status' => 'completed'])->assertRedirect();

        $this->assertDatabaseHas('todos', [
            'id' => $todo->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('reminders', [
            'todo_id' => $todo->id,
            'status' => 'completed',
        ]);
    }

    public function test_ai_decomposition_creates_steps_and_human_reminders(): void
    {
        $workspace = Workspace::create(['name' => '工作', 'slug' => 'work']);
        $todo = Todo::create([
            'workspace_id' => $workspace->id,
            'title' => '优化 Laravel SDK 发布流程',
            'description' => '为开发任务生成可执行的 Codex Prompt。',
            'priority' => 'P1',
            'status' => 'pending',
        ]);

        $this->post("/todos/{$todo->id}/decompose")->assertRedirect();

        $this->assertSame(3, TodoStep::where('todo_id', $todo->id)->count());
        $this->assertDatabaseHas('todos', [
            'id' => $todo->id,
            'status' => 'waiting_confirmation',
        ]);
        $this->assertSame(2, Reminder::where('todo_id', $todo->id)->count());

        TodoStep::where('todo_id', $todo->id)->each(function (TodoStep $step): void {
            $this->patch("/todo-steps/{$step->id}/status", ['status' => 'completed'])->assertRedirect();
        });

        $this->assertDatabaseHas('todo_steps', [
            'todo_id' => $todo->id,
            'status' => 'completed',
        ]);
    }
}
