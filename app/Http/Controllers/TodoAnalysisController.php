<?php

namespace App\Http\Controllers;

use App\Models\Todo;
use App\Services\Reminders\ReminderService;
use App\Services\Todos\TodoDecompositionService;
use Illuminate\Http\RedirectResponse;

class TodoAnalysisController extends Controller
{
    public function store(
        Todo $todo,
        TodoDecompositionService $decompositionService,
        ReminderService $reminderService
    ): RedirectResponse {
        $todo = $decompositionService->decompose($todo);

        foreach ($todo->steps as $step) {
            if ($step->execution_type !== 'AI' || $step->requires_human_confirmation) {
                $reminderService->createForHumanStep($step);
            }
        }

        return back()->with('success', '任务已拆解为 AI、人工和协作步骤。');
    }
}
