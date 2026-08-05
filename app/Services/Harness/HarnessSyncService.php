<?php

namespace App\Services\Harness;

use App\Models\HarnessConnection;

class HarnessSyncService
{
    public function __construct(private readonly HarnessManager $manager) {}

    public function sync(HarnessConnection $connection): HarnessSyncResult
    {
        $result = $this->manager->adapterFor($connection)->sync($connection);
        $connection->update(['last_synced_at' => now()]);

        return $result;
    }
}
