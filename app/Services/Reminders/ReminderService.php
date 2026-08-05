<?php

namespace App\Services\Reminders;

use App\Models\Reminder;
use App\Models\Todo;
use App\Models\TodoStep;
use Illuminate\Support\Carbon;

class ReminderService
{
    public function createForTodo(Todo $todo, ?Carbon $remindAt = null): ?Reminder
    {
        if (! $todo->due_at && ! $remindAt) {
            Reminder::query()
                ->where('todo_id', $todo->id)
                ->whereNull('todo_step_id')
                ->where('channel', 'system')
                ->where('status', 'pending')
                ->delete();

            return null;
        }

        $reminders = Reminder::query()
            ->where('todo_id', $todo->id)
            ->whereNull('todo_step_id')
            ->where('channel', 'system')
            ->where('status', 'pending')
            ->get();

        $reminder = $reminders->shift() ?? new Reminder([
            'todo_id' => $todo->id,
            'channel' => 'system',
            'status' => 'pending',
        ]);

        $reminders->each->delete();

        $reminder->fill([
            'title' => '处理任务：'.$todo->title,
            'body' => $todo->description,
            'remind_at' => $remindAt ?? $todo->due_at->copy()->subHours(2),
        ])->save();

        return $reminder;
    }

    public function createForHumanStep(TodoStep $step, ?Carbon $remindAt = null): Reminder
    {
        return Reminder::query()->firstOrCreate([
            'todo_step_id' => $step->id,
            'channel' => 'system',
            'status' => 'pending',
        ], [
            'todo_id' => $step->todo_id,
            'title' => '需要人工处理：'.$step->title,
            'body' => $step->description,
            'remind_at' => $remindAt ?? now(),
        ]);
    }
}
