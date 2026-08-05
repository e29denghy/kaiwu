<?php

namespace App\Services\Harness\Adapters;

use App\Models\HarnessConnection;
use App\Models\HarnessEvent;
use App\Models\Project;
use App\Services\Harness\Contracts\HarnessAdapter;
use App\Services\Harness\HarnessSyncResult;
use Illuminate\Support\Carbon;
use RuntimeException;
use SplFileObject;
use Throwable;

class JsonlFileHarnessAdapter implements HarnessAdapter
{
    public function sync(HarnessConnection $connection): HarnessSyncResult
    {
        $path = trim((string) ($connection->configuration['inbox_path'] ?? ''));

        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("Harness inbox 不可读：{$path}");
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $file = new SplFileObject($path, 'r');

        foreach ($file as $lineNumber => $line) {
            $line = trim((string) $line);

            if ($line === '') {
                continue;
            }

            try {
                $payload = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                $this->validatePayload($payload);
                $projectSlug = trim((string) ($payload['project_slug'] ?? ''));
                $projectId = $projectSlug === ''
                    ? null
                    : Project::query()->where('slug', $projectSlug)->value('id');
                $event = HarnessEvent::firstOrNew([
                    'harness_connection_id' => $connection->id,
                    'external_id' => (string) $payload['id'],
                ]);
                $isNew = ! $event->exists;
                $event->fill([
                    'project_id' => $projectId,
                    'event_type' => (string) $payload['type'],
                    'status' => isset($payload['status']) ? (string) $payload['status'] : null,
                    'title' => (string) $payload['title'],
                    'summary' => isset($payload['summary']) ? (string) $payload['summary'] : null,
                    'payload' => $payload,
                    'occurred_at' => Carbon::parse((string) $payload['occurred_at']),
                ]);

                if (! $isNew && ! $event->isDirty()) {
                    $skipped++;

                    continue;
                }

                $event->save();
                $isNew ? $created++ : $updated++;
            } catch (Throwable $exception) {
                $errors[] = 'line '.($lineNumber + 1).': '.$exception->getMessage();
            }
        }

        return new HarnessSyncResult($created, $updated, $skipped, $errors);
    }

    private function validatePayload(mixed $payload): void
    {
        if (! is_array($payload)) {
            throw new RuntimeException('event 必须是 JSON object');
        }

        foreach (['id', 'type', 'title', 'occurred_at'] as $field) {
            if (! isset($payload[$field]) || trim((string) $payload[$field]) === '') {
                throw new RuntimeException("缺少必填字段 {$field}");
            }
        }
    }
}
