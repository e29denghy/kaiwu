<?php

namespace App\Console\Commands;

use App\Models\HarnessConnection;
use App\Services\Harness\HarnessSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncHarnesses extends Command
{
    protected $signature = 'harness:sync {connection? : Connection slug}';

    protected $description = 'Import normalized events from enabled Harness connections';

    public function handle(HarnessSyncService $syncService): int
    {
        $connections = HarnessConnection::query()
            ->where('status', 'active')
            ->when(
                $this->argument('connection'),
                fn ($query, $slug) => $query->where('slug', $slug),
            )
            ->get();

        foreach ($connections as $connection) {
            try {
                $result = $syncService->sync($connection);
                $this->info(
                    "{$connection->slug}: +{$result->created} ~{$result->updated} ={$result->skipped} errors=".count($result->errors),
                );

                foreach ($result->errors as $error) {
                    $this->warn($error);
                }
            } catch (Throwable $exception) {
                $this->error("{$connection->slug}: {$exception->getMessage()}");

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
