<?php

namespace Tests\Feature;

use App\Models\HarnessConnection;
use App\Models\Quest;
use App\Services\Harness\HarnessProtocolValidator;
use App\Services\Quests\QuestWorkflow;
use DomainException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/kaiwu-quest-'.Str::uuid();
        (new Filesystem)->ensureDirectoryExists($this->directory);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_only_an_approved_quest_is_dispatched_and_retries_append_attempts(): void
    {
        $connection = HarnessConnection::create([
            'name' => 'Local Harness',
            'slug' => 'local-harness',
            'driver' => 'jsonl',
            'status' => 'active',
            'configuration' => [
                'inbox_path' => $this->directory.'/events.jsonl',
                'outbox_path' => $this->directory.'/outbox',
            ],
        ]);
        $quest = Quest::create([
            'title' => 'Add a health endpoint',
            'goal' => 'Expose a read-only health endpoint.',
            'acceptance_criteria' => ['GET /health returns 200'],
            'constraints' => ['Do not deploy'],
            'verification' => ['Run the feature test'],
            'risk_level' => 'low',
            'requires_write' => true,
            'approval_status' => 'pending',
            'execution_mode' => 'immediate',
            'status' => 'awaiting_approval',
        ]);
        $workflow = app(QuestWorkflow::class);

        $this->expectException(DomainException::class);
        $workflow->dispatch($quest, $connection);
    }

    public function test_dispatch_writes_a_versioned_envelope_and_retry_preserves_history(): void
    {
        $connection = HarnessConnection::create([
            'name' => 'Local Harness',
            'slug' => 'local-harness',
            'driver' => 'jsonl',
            'status' => 'active',
            'configuration' => [
                'inbox_path' => $this->directory.'/events.jsonl',
                'outbox_path' => $this->directory.'/outbox',
            ],
        ]);
        $quest = Quest::create([
            'title' => 'Add a health endpoint',
            'goal' => 'Expose a read-only health endpoint.',
            'acceptance_criteria' => ['GET /health returns 200'],
            'constraints' => ['Do not deploy'],
            'verification' => ['Run the feature test'],
            'risk_level' => 'low',
            'requires_write' => true,
            'approval_status' => 'pending',
            'execution_mode' => 'immediate',
            'status' => 'awaiting_approval',
        ]);
        $workflow = app(QuestWorkflow::class);
        $workflow->approve($quest);
        $first = $workflow->dispatch($quest->refresh(), $connection);

        $this->assertFileExists($first->outbox_path.'/quest.json');
        $payload = json_decode(file_get_contents($first->outbox_path.'/quest.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('kaiwu.quest/v1', $payload['schema']);
        $this->assertSame('approved', $payload['approval']['status']);
        $this->assertSame(1, $payload['attempt']);
        app(HarnessProtocolValidator::class)->validate($payload, HarnessProtocolValidator::QUEST_SCHEMA);

        $workflow->markFailed($first, 'Harness process exited');
        $second = $workflow->dispatch($quest->refresh(), $connection);

        $this->assertSame(2, $second->attempt);
        $this->assertNotSame($first->dispatch_id, $second->dispatch_id);
        $this->assertSame(2, $quest->executions()->count());
    }
}
