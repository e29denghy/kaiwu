<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Todo;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $workspace = Workspace::firstOrCreate(
            ['slug' => 'work'],
            [
                'name' => '工作',
                'description' => '由人批准、由多个 Agent Harness 协作推进的工作。',
                'sort_order' => 1,
            ],
        );

        $project = Project::firstOrCreate(
            ['workspace_id' => $workspace->id, 'slug' => 'kaiwu-demo'],
            [
                'name' => '开物示例项目',
                'description' => '体验 Harness 事件归一化、Quest 审批和 Outbox 派发。',
                'priority' => 'P1',
                'sort_order' => 10,
                'status' => 'active',
            ],
        );

        Todo::firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'project_id' => $project->id,
                'title' => '接入第一个 Harness',
            ],
            [
                'description' => '创建连接、同步事件，然后生成并批准一个 Quest。',
                'priority' => 'P1',
                'status' => 'pending',
                'planning_state' => 'today',
                'scheduled_for' => now()->toDateString(),
                'focus_rank' => 1,
                'due_at' => now()->endOfDay(),
            ],
        );
    }
}
