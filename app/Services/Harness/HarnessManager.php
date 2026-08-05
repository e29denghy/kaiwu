<?php

namespace App\Services\Harness;

use App\Models\HarnessConnection;
use App\Services\Harness\Adapters\JsonlFileHarnessAdapter;
use App\Services\Harness\Contracts\HarnessAdapter;
use InvalidArgumentException;

class HarnessManager
{
    public function adapterFor(HarnessConnection $connection): HarnessAdapter
    {
        return match ($connection->driver) {
            'jsonl' => app(JsonlFileHarnessAdapter::class),
            default => throw new InvalidArgumentException(
                "不支持 Harness driver：{$connection->driver}",
            ),
        };
    }
}
