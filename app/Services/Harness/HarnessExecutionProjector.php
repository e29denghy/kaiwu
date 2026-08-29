<?php

namespace App\Services\Harness;

use App\Models\HarnessConnection;
use App\Models\QuestExecution;
use Illuminate\Support\Carbon;

class HarnessExecutionProjector
{
    /**
     * @param  array<string, mixed>  $event
     */
    public function project(HarnessConnection $connection, array $event): void
    {
        $dispatchId = trim((string) data_get($event, 'payload.dispatch_id', ''));

        if ($dispatchId === '') {
            return;
        }

        $execution = QuestExecution::query()
            ->where('harness_connection_id', $connection->id)
            ->where('dispatch_id', $dispatchId)
            ->first();

        if ($execution === null) {
            return;
        }

        $occurredAt = Carbon::parse((string) $event['occurred_at']);

        match ((string) $event['type']) {
            'execution.started' => $this->started($execution, $occurredAt),
            'execution.completed' => $this->completed($execution, $event, $occurredAt),
            'execution.failed' => $this->failed($execution, $event, $occurredAt),
            'execution.cancelled' => $this->cancelled($execution, $occurredAt),
            default => null,
        };
    }

    private function started(QuestExecution $execution, Carbon $occurredAt): void
    {
        if ($execution->status !== 'queued') {
            return;
        }

        $execution->update([
            'status' => 'running',
            'started_at' => $execution->started_at ?? $occurredAt,
        ]);
        $execution->quest()->update(['status' => 'running']);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function completed(QuestExecution $execution, array $event, Carbon $occurredAt): void
    {
        if (in_array($execution->status, ['completed', 'cancelled'], true)) {
            return;
        }

        $execution->update([
            'status' => 'completed',
            'result' => (string) data_get($event, 'payload.result', $event['summary'] ?? ''),
            'started_at' => $execution->started_at ?? $occurredAt,
            'finished_at' => $occurredAt,
        ]);
        $execution->quest()->update(['status' => 'completed']);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function failed(QuestExecution $execution, array $event, Carbon $occurredAt): void
    {
        if (in_array($execution->status, ['completed', 'cancelled'], true)) {
            return;
        }

        $execution->update([
            'status' => 'failed',
            'error' => (string) data_get($event, 'payload.error', $event['summary'] ?? 'Harness execution failed.'),
            'started_at' => $execution->started_at ?? $occurredAt,
            'finished_at' => $occurredAt,
        ]);
        $execution->quest()->update(['status' => 'failed']);
    }

    private function cancelled(QuestExecution $execution, Carbon $occurredAt): void
    {
        if ($execution->status === 'completed') {
            return;
        }

        $execution->update([
            'status' => 'cancelled',
            'finished_at' => $occurredAt,
        ]);
        $execution->quest()->update(['status' => 'cancelled']);
    }
}
