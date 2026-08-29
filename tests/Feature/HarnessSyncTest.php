<?php

namespace Tests\Feature;

use App\Models\HarnessConnection;
use App\Models\HarnessEvent;
use App\Models\Project;
use App\Models\Quest;
use App\Models\Workspace;
use App\Services\Quests\QuestWorkflow;
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

    public function test_deepseek_events_project_execution_lifecycle_back_to_the_quest(): void
    {
        $inbox = $this->directory.'/deepseek-events.jsonl';
        $connection = HarnessConnection::create([
            'name' => 'DeepSeek Local',
            'slug' => 'deepseek-local',
            'driver' => 'deepseek',
            'status' => 'active',
            'configuration' => [
                'inbox_path' => $inbox,
                'outbox_path' => $this->directory.'/deepseek-outbox',
            ],
        ]);
        $quest = Quest::create([
            'title' => 'Run a DSH verification',
            'goal' => 'Verify the approved workspace.',
            'acceptance_criteria' => ['Focused checks pass'],
            'constraints' => ['Do not deploy'],
            'verification' => ['Run the feature test'],
            'risk_level' => 'low',
            'requires_write' => false,
            'approval_status' => 'pending',
            'execution_mode' => 'immediate',
            'status' => 'awaiting_approval',
        ]);
        $workflow = app(QuestWorkflow::class);
        $workflow->approve($quest);
        $execution = $workflow->dispatch($quest->refresh(), $connection);
        file_put_contents($inbox, implode("\n", [
            json_encode([
                'schema' => 'kaiwu.event/v1',
                'id' => 'dsh-'.$execution->dispatch_id.'-started',
                'type' => 'execution.started',
                'status' => 'running',
                'title' => 'DeepSeek Harness started',
                'occurred_at' => '2026-08-17T10:00:00+08:00',
                'payload' => ['dispatch_id' => $execution->dispatch_id],
            ], JSON_THROW_ON_ERROR),
            json_encode([
                'schema' => 'kaiwu.event/v1',
                'id' => 'dsh-'.$execution->dispatch_id.'-completed',
                'type' => 'execution.completed',
                'status' => 'completed',
                'title' => 'DeepSeek Harness completed',
                'summary' => 'All focused checks passed.',
                'occurred_at' => '2026-08-17T10:05:00+08:00',
                'payload' => [
                    'dispatch_id' => $execution->dispatch_id,
                    'result' => 'All focused checks passed.',
                    'finish_reason' => 'completed',
                ],
            ], JSON_THROW_ON_ERROR),
        ])."\n");

        $this->artisan('harness:sync deepseek-local')->assertSuccessful();

        $this->assertDatabaseHas('quest_executions', [
            'id' => $execution->id,
            'status' => 'completed',
            'result' => 'All focused checks passed.',
        ]);
        $this->assertDatabaseHas('quests', [
            'id' => $quest->id,
            'status' => 'completed',
        ]);
        $this->assertNotNull($execution->refresh()->started_at);
        $this->assertNotNull($execution->finished_at);

        $execution->update([
            'status' => 'queued',
            'result' => null,
            'started_at' => null,
            'finished_at' => null,
        ]);
        $quest->update(['status' => 'dispatched']);

        $this->artisan('harness:sync deepseek-local')->assertSuccessful();

        $this->assertDatabaseHas('quest_executions', [
            'id' => $execution->id,
            'status' => 'completed',
            'result' => 'All focused checks passed.',
        ]);
        $this->assertDatabaseHas('quests', [
            'id' => $quest->id,
            'status' => 'completed',
        ]);
        $this->assertSame(2, $connection->events()->count());
    }

    public function test_deepseek_driver_can_be_created_from_the_harness_form(): void
    {
        $this->post('/harnesses', [
            'name' => 'DeepSeek Preview',
            'driver' => 'deepseek',
            'inbox_path' => $this->directory.'/events.jsonl',
            'outbox_path' => $this->directory.'/outbox',
        ])->assertRedirect();

        $this->assertDatabaseHas('harness_connections', [
            'slug' => 'deepseek-preview',
            'driver' => 'deepseek',
            'status' => 'active',
        ]);
    }
}
