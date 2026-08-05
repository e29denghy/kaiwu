<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Todo;
use App\Models\Workspace;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardFilterTest extends TestCase
{
    use RefreshDatabase;

    private string $memoryRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->memoryRoot = sys_get_temp_dir().'/dashboard-memory-'.Str::uuid();
        (new Filesystem)->ensureDirectoryExists($this->memoryRoot);

        config([
            'codex-memory.root' => $this->memoryRoot,
            'codex-memory.engineering_roots' => [],
        ]);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->memoryRoot);

        parent::tearDown();
    }

    public function test_dashboard_orders_projects_and_filters_todays_tasks_by_project(): void
    {
        $workspace = Workspace::create([
            'name' => '工作',
            'slug' => 'work',
        ]);
        $laterProject = Project::create([
            'workspace_id' => $workspace->id,
            'name' => 'Internet',
            'slug' => 'internet',
            'priority' => 'P0',
            'sort_order' => 30,
            'status' => 'active',
        ]);
        $firstProject = Project::create([
            'workspace_id' => $workspace->id,
            'name' => 'Alpha Project P0',
            'slug' => 'alpha-project',
            'priority' => 'P0',
            'sort_order' => 10,
            'status' => 'active',
        ]);
        Todo::create([
            'workspace_id' => $workspace->id,
            'project_id' => $firstProject->id,
            'title' => '今日待办',
            'priority' => 'P0',
            'status' => 'pending',
        ]);
        Todo::create([
            'workspace_id' => $workspace->id,
            'project_id' => $firstProject->id,
            'title' => '今日完成',
            'priority' => 'P1',
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        $focusTodo = Todo::create([
            'workspace_id' => $workspace->id,
            'project_id' => $firstProject->id,
            'title' => '今日聚焦',
            'priority' => 'P0',
            'status' => 'in_progress',
            'planning_state' => 'today',
            'scheduled_for' => today()->toDateString(),
            'focus_rank' => 1,
        ]);
        DB::table('todos')->where('id', $focusTodo->id)->update([
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);
        $oldTodo = Todo::create([
            'workspace_id' => $workspace->id,
            'project_id' => $laterProject->id,
            'title' => '旧任务',
            'priority' => 'P1',
            'status' => 'pending',
        ]);
        DB::table('todos')->where('id', $oldTodo->id)->update([
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        $this->get("/?project={$firstProject->id}&period=today&status=todo")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('filters.project_id', $firstProject->id)
                ->where('filters.period', 'today')
                ->where('filters.status', 'todo')
                ->has('projectTasks', 1)
                ->where('projectTasks.0.title', '今日待办')
                ->has('todayFocus', 1)
                ->where('todayFocus.0.title', '今日聚焦')
                ->where('stats.today_focus', 1)
                ->where('activeProjects.0.id', $firstProject->id)
                ->where('activeProjects.1.id', $laterProject->id)
                ->has('todayActiveProjects', 1)
                ->where('todayActiveProjects.0.id', $firstProject->id)
                ->where('stats.today_projects', 1)
                ->where('stats.today_tasks', 2)
                ->where('stats.today_completed', 1));
    }
}
