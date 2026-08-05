<?php

namespace App\Console\Commands;

use App\Services\CodexMemory\MemorySyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncCodexMemory extends Command
{
    protected $signature = 'memory:sync {--root= : 项目记忆知识库根目录}';

    protected $description = '从 Codex 项目记忆知识库增量同步已完成和待办';

    public function handle(MemorySyncService $syncService): int
    {
        try {
            $result = $syncService->sync($this->option('root') ?: null);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['发现项目', '记忆条目', '待关联', '新增任务'],
            [[
                $result['sources'],
                $result['entries'],
                $result['pending_sources'],
                $result['imported_todos'],
            ]],
        );

        return self::SUCCESS;
    }
}
