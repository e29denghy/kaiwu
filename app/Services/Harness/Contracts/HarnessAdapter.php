<?php

namespace App\Services\Harness\Contracts;

use App\Models\HarnessConnection;
use App\Services\Harness\HarnessSyncResult;

interface HarnessAdapter
{
    public function sync(HarnessConnection $connection): HarnessSyncResult;
}
