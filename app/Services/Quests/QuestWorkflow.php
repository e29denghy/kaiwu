<?php

namespace App\Services\Quests;

use App\Models\HarnessConnection;
use App\Models\Quest;
use App\Models\QuestExecution;
use DomainException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Throwable;

class QuestWorkflow
{
    public function __construct(private readonly Filesystem $files) {}

    public function approve(Quest $quest): Quest
    {
        if (in_array($quest->status, ['cancelled', 'completed'], true)) {
            throw new DomainException('终态 Quest 不能批准。');
        }

        $quest->update([
            'approval_status' => 'approved',
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        return $quest->refresh();
    }

    public function dispatch(Quest $quest, HarnessConnection $connection): QuestExecution
    {
        if ($quest->approval_status !== 'approved' || $quest->approved_at === null) {
            throw new DomainException('Quest 尚未人工批准，禁止派发。');
        }

        if ($connection->status !== 'active') {
            throw new DomainException('目标 Harness 连接未启用。');
        }

        if (! in_array($quest->status, ['approved', 'failed'], true)) {
            throw new DomainException("Quest 当前状态 {$quest->status} 不能派发。");
        }

        $outboxRoot = trim((string) ($connection->configuration['outbox_path'] ?? ''));

        if ($outboxRoot === '') {
            throw new DomainException('目标 Harness 尚未配置 outbox_path。');
        }

        $attempt = (int) $quest->executions()->max('attempt') + 1;
        $dispatchId = (string) Str::uuid();
        $dispatchDirectory = rtrim($outboxRoot, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.$dispatchId;
        $execution = QuestExecution::create([
            'quest_id' => $quest->id,
            'harness_connection_id' => $connection->id,
            'attempt' => $attempt,
            'dispatch_id' => $dispatchId,
            'status' => 'queued',
            'outbox_path' => $dispatchDirectory,
            'metadata' => ['schema' => 'kaiwu.execution/v1'],
        ]);

        try {
            $this->files->ensureDirectoryExists($dispatchDirectory);
            $this->writeJson($dispatchDirectory.'/quest.json', [
                'schema' => 'kaiwu.quest/v1',
                'id' => $dispatchId,
                'quest_id' => $quest->id,
                'attempt' => $attempt,
                'title' => $quest->title,
                'goal' => $quest->goal,
                'project' => $quest->project?->only(['id', 'name', 'slug']),
                'acceptance_criteria' => $quest->acceptance_criteria,
                'constraints' => $quest->constraints ?? [],
                'verification' => $quest->verification ?? [],
                'risk_level' => $quest->risk_level,
                'requires_write' => $quest->requires_write,
                'approval' => [
                    'status' => $quest->approval_status,
                    'approved_at' => $quest->approved_at?->toIso8601String(),
                ],
                'created_at' => now()->toIso8601String(),
            ]);
            $this->writeJson($dispatchDirectory.'/status.json', [
                'schema' => 'kaiwu.execution-status/v1',
                'dispatch_id' => $dispatchId,
                'status' => 'queued',
                'updated_at' => now()->toIso8601String(),
            ]);
            $quest->update(['status' => 'queued']);
        } catch (Throwable $exception) {
            $execution->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'finished_at' => now(),
            ]);
            $quest->update(['status' => 'failed']);

            throw $exception;
        }

        return $execution->refresh();
    }

    public function markFailed(QuestExecution $execution, string $error): QuestExecution
    {
        $execution->update([
            'status' => 'failed',
            'error' => $error,
            'finished_at' => now(),
        ]);
        $execution->quest()->update(['status' => 'failed']);

        return $execution->refresh();
    }

    public function cancel(Quest $quest): Quest
    {
        if ($quest->status === 'completed') {
            throw new DomainException('已完成 Quest 不能取消。');
        }

        $quest->update(['status' => 'cancelled']);
        $quest->executions()->whereIn('status', ['queued', 'running'])->update([
            'status' => 'cancelled',
            'finished_at' => now(),
        ]);

        return $quest->refresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeJson(string $path, array $payload): void
    {
        $json = json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
        $temporaryPath = $path.'.tmp';
        $this->files->put($temporaryPath, $json.PHP_EOL, true);
        $this->files->move($temporaryPath, $path);
    }
}
