<?php

namespace App\Console\Commands;

use App\Models\HarnessConnection;
use App\Models\Quest;
use App\Services\Quests\QuestWorkflow;
use Illuminate\Console\Command;

class DispatchQuest extends Command
{
    protected $signature = 'quest:dispatch {quest : Quest ID} {connection : Harness connection slug}';

    protected $description = 'Write an approved Quest into a Harness outbox';

    public function handle(QuestWorkflow $workflow): int
    {
        $quest = Quest::findOrFail($this->argument('quest'));
        $connection = HarnessConnection::where('slug', $this->argument('connection'))->firstOrFail();
        $execution = $workflow->dispatch($quest, $connection);
        $this->info("Dispatched {$execution->dispatch_id} to {$execution->outbox_path}");

        return self::SUCCESS;
    }
}
