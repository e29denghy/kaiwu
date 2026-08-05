<?php

namespace Tests\Feature;

use App\Models\MemoryEntry;
use App\Models\Project;
use App\Models\ProjectMemorySource;
use App\Models\Todo;
use App\Models\Workspace;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CodexMemorySyncTest extends TestCase
{
    use RefreshDatabase;

    private string $engineeringRoot;

    private string $memoryRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = Str::uuid();
        $this->memoryRoot = sys_get_temp_dir()."/kaiwu-memory-{$suffix}";
        $this->engineeringRoot = sys_get_temp_dir()."/kaiwu-code-{$suffix}";
        (new Filesystem)->ensureDirectoryExists($this->memoryRoot);
        (new Filesystem)->ensureDirectoryExists($this->engineeringRoot);

        config([
            'codex-memory.root' => $this->memoryRoot,
            'codex-memory.engineering_roots' => [$this->engineeringRoot],
        ]);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->memoryRoot);
        (new Filesystem)->deleteDirectory($this->engineeringRoot);

        parent::tearDown();
    }

    public function test_it_discovers_project_knowledge_before_creating_projects_or_todos(): void
    {
        $this->writeProjectKnowledge();

        $this->post('/memory-sources/sync')->assertRedirect();

        $source = ProjectMemorySource::firstOrFail();

        $this->assertSame(realpath($this->engineeringRoot.'/alpha-project'), $source->scope_cwd);
        $this->assertSame('pending', $source->status);
        $this->assertNull($source->project_id);
        $this->assertSame(
            realpath($this->engineeringRoot.'/alpha-project/AGENTS.md'),
            $source->metadata['agents_path'],
        );
        $this->assertSame(5, MemoryEntry::count());
        $this->assertSame(0, Project::count());
        $this->assertSame(0, Todo::count());
        $this->assertDatabaseMissing('memory_entries', [
            'title' => '这条 DevLog 参考不能生成任务',
        ]);
    }

    public function test_linking_a_confirmed_source_materializes_todo_doing_and_done_idempotently(): void
    {
        $this->writeProjectKnowledge();
        $project = $this->createProject();

        $this->post('/memory-sources/sync')->assertRedirect();
        $source = ProjectMemorySource::firstOrFail();
        $this->patch("/memory-sources/{$source->id}", [
            'project_id' => $project->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('todos', [
            'project_id' => $project->id,
            'title' => '修复发布门禁',
            'priority' => 'P0',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('todos', [
            'project_id' => $project->id,
            'title' => '联调生成状态',
            'status' => 'in_progress',
        ]);
        $this->assertDatabaseHas('todos', [
            'project_id' => $project->id,
            'title' => '生产发布',
            'status' => 'completed',
        ]);
        $this->assertSame(
            '2026-07-20',
            MemoryEntry::where('title', '生产发布')
                ->firstOrFail()
                ->source_updated_at
                ->toDateString(),
        );
        $this->assertDatabaseHas('todos', [
            'project_id' => $project->id,
            'title' => '确认剩余迁移',
            'priority' => 'P1',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('todos', [
            'project_id' => $project->id,
            'title' => '生产发布',
            'planning_state' => 'archive',
        ]);
        $this->assertDatabaseHas('todos', [
            'project_id' => $project->id,
            'title' => '修复发布门禁',
            'planning_state' => 'backlog',
        ]);
        $this->assertSame(5, Todo::count());

        $completed = Todo::where('title', '生产发布')->firstOrFail();
        $this->patch("/todos/{$completed->id}/status", [
            'status' => 'in_progress',
        ])->assertRedirect();

        $this->post('/memory-sources/sync')->assertRedirect();

        $this->assertSame(5, Todo::count());
        $this->assertDatabaseHas('todos', [
            'id' => $completed->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_the_sync_command_accepts_an_explicit_knowledge_root(): void
    {
        $this->writeProjectKnowledge();

        $this->artisan('memory:sync', [
            '--root' => $this->memoryRoot,
        ])->assertSuccessful();

        $this->assertSame(1, ProjectMemorySource::count());
        $this->assertSame(5, MemoryEntry::count());
    }

    public function test_a_confirmed_source_can_be_linked_before_the_first_scan(): void
    {
        $knowledgePath = $this->writeProjectKnowledge();
        $project = $this->createProject();
        ProjectMemorySource::create([
            'project_id' => $project->id,
            'scope_key' => hash('sha256', realpath($knowledgePath)),
            'scope_cwd' => $this->engineeringRoot.'/alpha-project',
            'discovered_name' => 'Alpha Project',
            'registry_path' => $knowledgePath.'/Projects/Alpha Project/TODO.md',
            'status' => 'linked',
            'metadata' => ['pre_registered' => true],
        ]);

        $this->post('/memory-sources/sync')->assertRedirect();

        $source = ProjectMemorySource::firstOrFail();

        $this->assertSame('linked', $source->status);
        $this->assertSame($project->id, $source->project_id);
        $this->assertSame(5, Todo::where('project_id', $project->id)->count());
    }

    public function test_opening_the_todo_page_automatically_syncs_when_due(): void
    {
        $knowledgePath = $this->writeProjectKnowledge();
        $project = $this->createProject();
        ProjectMemorySource::create([
            'project_id' => $project->id,
            'scope_key' => hash('sha256', realpath($knowledgePath)),
            'scope_cwd' => $this->engineeringRoot.'/alpha-project',
            'discovered_name' => 'Alpha Project',
            'registry_path' => $knowledgePath.'/Projects/Alpha Project/TODO.md',
            'status' => 'linked',
            'metadata' => ['pre_registered' => true],
            'last_synced_at' => now()->subHour(),
        ]);

        $this->get('/todos')->assertOk();

        $this->assertSame(5, Todo::where('project_id', $project->id)->count());
    }

    private function createProject(): Project
    {
        $workspace = Workspace::create([
            'name' => '工作',
            'slug' => 'work',
        ]);

        return Project::create([
            'workspace_id' => $workspace->id,
            'name' => 'Alpha Project P0',
            'slug' => 'alpha-project',
            'priority' => 'P0',
            'status' => 'active',
        ]);
    }

    private function writeProjectKnowledge(): string
    {
        $knowledgePath = $this->memoryRoot.'/alpha-project';
        $projectMemoryPath = $knowledgePath.'/Projects/Alpha Project';
        $engineeringPath = $this->engineeringRoot.'/alpha-project';
        (new Filesystem)->ensureDirectoryExists($projectMemoryPath);
        (new Filesystem)->ensureDirectoryExists($engineeringPath);

        file_put_contents($projectMemoryPath.'/TODO.md', <<<'MEMORY'
# Alpha Project TODO

## Todo

- [P0] 修复发布门禁

## Doing

- [ ] 联调生成状态
- 无

## 2026-07-20 发布

### Done

- [x] 生产发布
- 已完成归档

## 2026-07-27 更新

### Todo

- [P1] 确认剩余迁移
MEMORY);

        file_put_contents($projectMemoryPath.'/DevLog.md', <<<'MEMORY'
# DevLog

- [ ] 这条 DevLog 参考不能生成任务
MEMORY);
        file_put_contents($projectMemoryPath.'/Review.md', "# Review\n");
        file_put_contents(
            $engineeringPath.'/AGENTS.md',
            "# Project\n\nMemory path: `{$knowledgePath}`\n",
        );

        return $knowledgePath;
    }
}
