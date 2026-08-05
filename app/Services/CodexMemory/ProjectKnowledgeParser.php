<?php

namespace App\Services\CodexMemory;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

class ProjectKnowledgeParser
{
    /**
     * @return array<int, array{
     *     scope_cwd: string,
     *     scope_key: string,
     *     discovered_name: string,
     *     registry_path: string,
     *     content_hash: string,
     *     metadata: array<string, mixed>,
     *     entries: array<int, array<string, mixed>>
     * }>
     */
    public function parse(string $knowledgeRoot): array
    {
        $resolvedRoot = realpath($knowledgeRoot);

        if ($resolvedRoot === false || ! is_dir($resolvedRoot)) {
            throw new RuntimeException("找不到项目记忆知识库：{$knowledgeRoot}");
        }

        $agentsFiles = $this->discoverAgentsFiles();
        $todoPaths = glob($resolvedRoot.'/*/Projects/*/TODO.md') ?: [];
        sort($todoPaths);
        $sources = [];

        foreach ($todoPaths as $todoPath) {
            $resolvedTodoPath = realpath($todoPath);

            if ($resolvedTodoPath === false || ! is_file($resolvedTodoPath)) {
                continue;
            }

            $projectMemoryPath = dirname($resolvedTodoPath);
            $knowledgePath = dirname($projectMemoryPath, 2);
            $agentsPath = $this->matchAgentsFile($knowledgePath, $agentsFiles);
            $engineeringPath = $agentsPath !== null ? dirname($agentsPath) : $knowledgePath;
            $scopeKey = hash('sha256', $knowledgePath);
            $referenceFiles = array_values(array_filter([
                $resolvedTodoPath,
                $this->existingFile($projectMemoryPath.'/DevLog.md'),
                $this->existingFile($projectMemoryPath.'/Review.md'),
                $agentsPath,
            ]));
            $entries = $this->parseTodoFile(
                todoPath: $resolvedTodoPath,
                scopeKey: $scopeKey,
                knowledgeRoot: $resolvedRoot,
                referenceFiles: $referenceFiles,
            );

            $sources[] = [
                'scope_cwd' => $engineeringPath,
                'scope_key' => $scopeKey,
                'discovered_name' => basename($projectMemoryPath),
                'registry_path' => $resolvedTodoPath,
                'content_hash' => hash_file('sha256', $resolvedTodoPath),
                'metadata' => [
                    'source_type' => 'project_knowledge',
                    'knowledge_path' => $knowledgePath,
                    'project_memory_path' => $projectMemoryPath,
                    'todo_path' => $resolvedTodoPath,
                    'agents_path' => $agentsPath,
                    'reference_files' => $referenceFiles,
                    'entry_count' => count($entries),
                ],
                'entries' => $entries,
            ];
        }

        return $sources;
    }

    /**
     * @param  array<int, string>  $referenceFiles
     * @return array<int, array<string, mixed>>
     */
    private function parseTodoFile(
        string $todoPath,
        string $scopeKey,
        string $knowledgeRoot,
        array $referenceFiles,
    ): array {
        $content = file_get_contents($todoPath);

        if ($content === false) {
            throw new RuntimeException("无法读取项目任务记忆：{$todoPath}");
        }

        $lines = preg_split('/\R/u', $content) ?: [];
        $headings = [];
        $statuses = [];
        $occurrences = [];
        $entries = [];
        $fallbackUpdatedAt = Carbon::createFromTimestamp(
            filemtime($todoPath) ?: time(),
        )->toIso8601String();

        foreach ($lines as $index => $line) {
            if (preg_match('/^(#{2,6})\s+(.+?)\s*$/u', $line, $headingMatch)) {
                $level = strlen($headingMatch[1]);
                $heading = trim($headingMatch[2]);

                foreach (array_keys($headings) as $existingLevel) {
                    if ($existingLevel >= $level) {
                        unset($headings[$existingLevel], $statuses[$existingLevel]);
                    }
                }

                $headings[$level] = $heading;
                $headingStatus = $this->statusFromHeading($heading);

                if ($headingStatus !== null) {
                    $statuses[$level] = $headingStatus;
                }

                continue;
            }

            if (! preg_match('/^-\s+(.+?)\s*$/u', $line, $bulletMatch)) {
                continue;
            }

            $rawTitle = trim($bulletMatch[1]);
            $checkboxOutcome = null;

            if (preg_match('/^\[([xX ])\]\s*(.+)$/u', $rawTitle, $checkboxMatch)) {
                $checkboxOutcome = strtolower($checkboxMatch[1]) === 'x' ? 'completed' : 'todo';
                $rawTitle = trim($checkboxMatch[2]);
            }

            $sectionOutcome = $statuses === [] ? null : end($statuses);
            $outcome = $checkboxOutcome ?? $sectionOutcome;

            if ($checkboxOutcome === 'todo' && $sectionOutcome === 'doing') {
                $outcome = 'doing';
            }

            if ($outcome === null || $this->isEmptyPlaceholder($rawTitle)) {
                continue;
            }

            [$title, $priority] = $this->extractPriority($rawTitle);
            [$sourceUpdatedAt, $activityDateSource] = $this->extractActivityDate(
                headings: $headings,
                title: $rawTitle,
                fallback: $fallbackUpdatedAt,
            );
            $historicalSnapshot = $this->isHistoricalSnapshot($headings);
            $moduleHint = $this->extractModuleHint($title);
            $identity = Str::lower(preg_replace('/\s+/u', ' ', $title) ?: $title);
            $occurrences[$identity] = ($occurrences[$identity] ?? 0) + 1;
            $sourceHeading = implode(' / ', array_values($headings));
            $sourceKey = hash(
                'sha256',
                implode('|', [$scopeKey, $identity, (string) $occurrences[$identity]]),
            );
            $displayTitle = Str::limit($title, 200, '…');
            $sourceFile = Str::after($todoPath, rtrim($knowledgeRoot, '/').'/');
            $summary = trim(implode("\n", array_filter([
                $displayTitle !== $title ? $title : null,
                "项目知识库：{$sourceFile}",
                $referenceFiles !== [] ? '参考：'.implode('、', array_map('basename', array_slice($referenceFiles, 1))) : null,
            ])));
            $entry = [
                'source_key' => $sourceKey,
                'source_file' => $sourceFile,
                'source_heading' => $sourceHeading !== '' ? $sourceHeading : 'TODO',
                'source_updated_at' => $sourceUpdatedAt,
                'outcome' => $outcome,
                'title' => $displayTitle,
                'summary' => $summary,
                'evidence' => [
                    'source_kind' => 'project_todo',
                    'line' => $index + 1,
                    'priority' => $priority,
                    'section' => $sourceHeading,
                    'activity_date_source' => $activityDateSource,
                    'scope' => $historicalSnapshot ? 'historical_snapshot' : 'current_summary',
                    'module_hint' => $moduleHint,
                    'reference_files' => $referenceFiles,
                ],
            ];
            $entry['content_hash'] = hash(
                'sha256',
                json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            );
            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function extractPriority(string $title): array
    {
        $priority = 'P2';

        if (preg_match('/^\[(P[0-3])\]\s*(.+)$/iu', $title, $match)) {
            $priority = strtoupper($match[1]);
            $title = trim($match[2]);
        }

        return [$title, $priority];
    }

    /**
     * @param  array<int, string>  $headings
     */
    /**
     * @return array{0: string, 1: string}
     */
    private function extractActivityDate(
        array $headings,
        string $title,
        string $fallback,
    ): array {
        $candidates = array_map(
            fn (string $heading): array => [$heading, 'heading'],
            array_reverse(array_values($headings)),
        );
        $candidates[] = [$title, 'title'];

        foreach ($candidates as [$candidate, $source]) {
            preg_match_all(
                '/(?:(20\d{2})-(\d{1,2})-(\d{1,2})|(20\d{2})年(\d{1,2})月(\d{1,2})日)/u',
                $candidate,
                $matches,
                PREG_SET_ORDER,
            );

            if ($matches === []) {
                continue;
            }

            $match = end($matches);
            $year = (int) ($match[1] !== '' ? $match[1] : $match[4]);
            $month = (int) ($match[2] !== '' ? $match[2] : $match[5]);
            $day = (int) ($match[3] !== '' ? $match[3] : $match[6]);

            if (! checkdate($month, $day, $year)) {
                continue;
            }

            return [Carbon::create(
                year: $year,
                month: $month,
                day: $day,
                hour: 12,
                timezone: config('app.timezone'),
            )->toIso8601String(), $source];
        }

        return [$fallback, 'file'];
    }

    private function statusFromHeading(string $heading): ?string
    {
        $normalized = Str::lower(trim($heading));

        if (in_array($normalized, ['todo', 'todos', '待办', '待处理'], true)) {
            return 'todo';
        }

        if (in_array($normalized, ['doing', 'in progress', '进行中'], true)) {
            return 'doing';
        }

        if (in_array($normalized, ['done', 'completed', '已完成'], true)) {
            return 'completed';
        }

        return null;
    }

    /**
     * Dated sections are useful evidence of what happened, but are not a new
     * current backlog. They remain importable and searchable as history.
     *
     * @param  array<int, string>  $headings
     */
    private function isHistoricalSnapshot(array $headings): bool
    {
        foreach ($headings as $heading) {
            if (preg_match('/(?:20\d{2}-\d{1,2}-\d{1,2}|20\d{2}年\d{1,2}月\d{1,2}日)/u', $heading)) {
                return true;
            }
        }

        return false;
    }

    private function extractModuleHint(string $title): ?string
    {
        if (preg_match('/^\[([^\]]+)\]/u', trim($title), $match)) {
            return trim($match[1]);
        }

        return null;
    }

    private function isEmptyPlaceholder(string $title): bool
    {
        return in_array(Str::lower(trim($title, " \t\n\r\0\x0B。.")), [
            '无',
            '暂无',
            'none',
            'nothing',
        ], true);
    }

    /**
     * @return array<int, string>
     */
    private function discoverAgentsFiles(): array
    {
        $files = [];

        foreach ((array) config('codex-memory.engineering_roots', []) as $root) {
            $resolvedRoot = realpath((string) $root);

            if ($resolvedRoot === false || ! is_dir($resolvedRoot)) {
                continue;
            }

            foreach ([
                $resolvedRoot.'/*/AGENTS.md',
                $resolvedRoot.'/*/agents.md',
                $resolvedRoot.'/*/*/AGENTS.md',
                $resolvedRoot.'/*/*/agents.md',
                $resolvedRoot.'/*/*/*/AGENTS.md',
                $resolvedRoot.'/*/*/*/agents.md',
            ] as $pattern) {
                foreach (glob($pattern) ?: [] as $path) {
                    $resolvedPath = realpath($path);

                    if ($resolvedPath !== false) {
                        $files[$resolvedPath] = $resolvedPath;
                    }
                }
            }
        }

        return array_values($files);
    }

    /**
     * @param  array<int, string>  $agentsFiles
     */
    private function matchAgentsFile(string $knowledgePath, array $agentsFiles): ?string
    {
        $knowledgeSlug = Str::slug(basename($knowledgePath));
        $bestPath = null;
        $bestScore = 0;

        foreach ($agentsFiles as $agentsPath) {
            $content = file_get_contents($agentsPath) ?: '';
            $score = Str::slug(basename(dirname($agentsPath))) === $knowledgeSlug ? 100 : 0;

            if (str_contains($content, $knowledgePath)) {
                $score += 20;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestPath = $agentsPath;
            }
        }

        return $bestPath;
    }

    private function existingFile(string $path): ?string
    {
        $resolvedPath = realpath($path);

        return $resolvedPath !== false && is_file($resolvedPath) ? $resolvedPath : null;
    }
}
