<?php

namespace App\Services\CodexMemory;

use App\Models\MemoryEntry;
use App\Models\Project;
use App\Models\ProjectMemorySource;
use App\Models\Todo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MemorySyncService
{
    public function __construct(
        private readonly ProjectKnowledgeParser $parser,
    ) {}

    /**
     * @return array{sources: int, entries: int, pending_sources: int, imported_todos: int}
     */
    public function sync(?string $knowledgeRoot = null): array
    {
        $knowledgeRoot ??= $this->defaultKnowledgeRoot();
        $parsedSources = $this->parser->parse($knowledgeRoot);
        $entryCount = 0;
        $importedTodoCount = 0;

        DB::transaction(function () use (
            $parsedSources,
            &$entryCount,
            &$importedTodoCount,
        ): void {
            foreach ($parsedSources as $parsedSource) {
                $source = ProjectMemorySource::firstOrNew([
                    'scope_key' => $parsedSource['scope_key'],
                ]);

                if (! $source->exists) {
                    $source->status = 'pending';
                }

                $source->fill([
                    'scope_cwd' => $parsedSource['scope_cwd'],
                    'discovered_name' => $parsedSource['discovered_name'],
                    'registry_path' => $parsedSource['registry_path'],
                    'content_hash' => $parsedSource['content_hash'],
                    'metadata' => $parsedSource['metadata'],
                    'last_synced_at' => now(),
                ])->save();

                $seenEntryKeys = [];

                foreach ($parsedSource['entries'] as $parsedEntry) {
                    $seenEntryKeys[] = $parsedEntry['source_key'];
                    $entry = MemoryEntry::firstOrNew([
                        'source_key' => $parsedEntry['source_key'],
                    ]);
                    $previousOutcome = $entry->exists ? $entry->outcome : null;

                    $entry->fill([
                        'project_memory_source_id' => $source->id,
                        ...$parsedEntry,
                        'is_current' => true,
                        'last_seen_at' => now(),
                    ])->save();

                    $entryCount++;

                    if ($source->status === 'linked' && $source->project_id !== null) {
                        $created = $this->materializeEntry($entry, $previousOutcome);
                        $importedTodoCount += $created ? 1 : 0;
                    }
                }

                $staleEntries = $source->entries()->where('is_current', true);

                if ($seenEntryKeys !== []) {
                    $staleEntries->whereNotIn('source_key', $seenEntryKeys);
                }

                $staleEntries->get()->each(function (MemoryEntry $entry): void {
                    if ($entry->todo !== null && $entry->todo->status !== 'completed') {
                        $entry->todo->update([
                            'status' => 'cancelled',
                            'completed_at' => null,
                        ]);
                    }

                    $entry->update(['is_current' => false]);
                });
            }
        });

        return [
            'sources' => count($parsedSources),
            'entries' => $entryCount,
            'pending_sources' => ProjectMemorySource::where('status', 'pending')->count(),
            'imported_todos' => $importedTodoCount,
        ];
    }

    /**
     * @return array{sources: int, entries: int, pending_sources: int, imported_todos: int}|null
     */
    public function syncIfDue(): ?array
    {
        $lastSyncedAt = ProjectMemorySource::query()->max('last_synced_at');
        $intervalMinutes = (int) config('codex-memory.auto_sync_interval_minutes', 10);

        if (
            $lastSyncedAt !== null
            && Carbon::parse($lastSyncedAt)->isAfter(now()->subMinutes($intervalMinutes))
        ) {
            return null;
        }

        return $this->sync();
    }

    public function link(ProjectMemorySource $source, Project $project): int
    {
        return DB::transaction(function () use ($source, $project): int {
            $source->update([
                'project_id' => $project->id,
                'status' => 'linked',
            ]);

            $created = 0;

            $source->entries()
                ->where('is_current', true)
                ->each(function (MemoryEntry $entry) use (&$created): void {
                    $created += $this->materializeEntry($entry, null) ? 1 : 0;
                });

            return $created;
        });
    }

    public function ignore(ProjectMemorySource $source): void
    {
        $source->update([
            'project_id' => null,
            'status' => 'ignored',
        ]);
    }

    private function materializeEntry(MemoryEntry $entry, ?string $previousOutcome): bool
    {
        $source = $entry->source()->with('project')->first();
        $project = $source?->project;

        if ($source === null || $project === null || ! $entry->is_current) {
            return false;
        }

        $targetStatus = match ($entry->outcome) {
            'completed' => 'completed',
            'doing' => 'in_progress',
            'todo' => 'pending',
            default => 'waiting_confirmation',
        };
        $description = trim(implode("\n\n", array_filter([
            $entry->summary,
            "记忆来源：{$entry->source_file} · {$entry->source_heading}",
        ])));
        $historicalSnapshot = ($entry->evidence['scope'] ?? null) === 'historical_snapshot';
        $planningState = $historicalSnapshot
            ? 'archive'
            : ($targetStatus === 'waiting_confirmation' ? 'waiting' : 'backlog');
        $todo = $entry->todo;
        $created = $todo === null;

        if ($todo === null) {
            $todo = Todo::create([
                'workspace_id' => $project->workspace_id,
                'project_id' => $project->id,
                'title' => $entry->title,
                'description' => $description,
                'priority' => $entry->evidence['priority'] ?? 'P2',
                'status' => $targetStatus,
                'planning_state' => $planningState,
                'completed_at' => $targetStatus === 'completed'
                    ? $this->completionTime($entry)
                    : null,
            ]);
            $entry->update(['todo_id' => $todo->id]);

            return true;
        }

        $updates = [
            'workspace_id' => $project->workspace_id,
            'project_id' => $project->id,
            'title' => $entry->title,
            'description' => $description,
            'priority' => $entry->evidence['priority'] ?? 'P2',
        ];

        if ($historicalSnapshot && $todo->planning_state === 'backlog') {
            $updates['planning_state'] = 'archive';
        } elseif (! $historicalSnapshot && $targetStatus === 'waiting_confirmation' && $todo->planning_state === 'backlog') {
            $updates['planning_state'] = 'waiting';
        }

        if ($previousOutcome !== null && $previousOutcome !== $entry->outcome) {
            $updates['status'] = $targetStatus;
            $updates['completed_at'] = $targetStatus === 'completed'
                ? $this->completionTime($entry)
                : null;
        } elseif ($todo->status === 'completed' && $entry->outcome === 'completed') {
            $updates['completed_at'] = $this->completionTime($entry);
        }

        $todo->fill($updates);

        if ($todo->isDirty()) {
            $todo->save();
        }

        return $created;
    }

    private function completionTime(MemoryEntry $entry): Carbon
    {
        return $entry->source_updated_at ?? now();
    }

    private function defaultKnowledgeRoot(): string
    {
        $root = rtrim((string) config('codex-memory.root'), DIRECTORY_SEPARATOR);

        if ($root === '') {
            throw new RuntimeException('尚未配置 PROJECT_MEMORY_ROOT。');
        }

        return $root;
    }
}
