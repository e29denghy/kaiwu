<?php

namespace Tests\Feature;

use App\Models\HarnessConnection;
use App\Models\HarnessEvent;
use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class HarnessSyncTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/kaiwu-harness-'.Str::uuid();
        (new Filesystem)->ensureDirectoryExists($this->directory);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_jsonl_events_are_normalized_and_synced_idempotently(): void
    {
        $workspace = Workspace::create(['name' => 'Work', 'slug' => 'work']);
        $project = Project::create([
            'workspace_id' => $workspace->id,
            'name' => 'Alpha',
            'slug' => 'alpha',
            'priority' => 'P1',
            'status' => 'active',
        ]);
        $inbox = $this->directory.'/events.jsonl';
        file_put_contents($inbox, implode("\n", [
            json_encode([
                'schema' => 'kaiwu.event/v1',
                'id' => 'evt-001',
                'type' => 'execution.completed',
                'status' => 'completed',
                'title' => 'Regression tests passed',
                'summary' => 'The harness completed the approved quest.',
                'project_slug' => 'alpha',
                'occurred_at' => '2026-08-05T09:00:00+08:00',
            ], JSON_THROW_ON_ERROR),
            '{malformed',
        ])."\n");
        HarnessConnection::create([
            'name' => 'Codex Local',
            'slug' => 'codex-local',
            'driver' => 'jsonl',
            'status' => 'active',
            'configuration' => [
                'inbox_path' => $inbox,
                'outbox_path' => $this->directory.'/outbox',
            ],
        ]);

        $this->artisan('harness:sync')->assertSuccessful();
        $this->artisan('harness:sync')->assertSuccessful();

        $this->assertSame(1, HarnessEvent::count());
        $this->assertDatabaseHas('harness_events', [
            'external_id' => 'evt-001',
            'project_id' => $project->id,
            'event_type' => 'execution.completed',
            'status' => 'completed',
        ]);
    }
}
