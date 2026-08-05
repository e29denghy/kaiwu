<?php

namespace App\Http\Controllers;

use App\Models\TodoStep;
use App\Services\AI\AIProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TodoStepController extends Controller
{
    public function store(Request $request, AIProvider $aiProvider): RedirectResponse
    {
        $data = $request->validate([
            'todo_id' => ['required', 'exists:todos,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'execution_type' => ['required', 'in:AI,Human,Hybrid'],
            'status' => ['required', 'in:pending,in_progress,waiting_confirmation,completed,cancelled'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'ai_prompt' => ['nullable', 'string'],
            'ai_result' => ['nullable', 'string'],
            'requires_human_confirmation' => ['boolean'],
        ]);

        $step = TodoStep::create($data);

        if ($step->execution_type !== 'Human' && ! $step->ai_prompt) {
            $step->update(['ai_prompt' => $aiProvider->generatePromptForStep($step->load('todo'))]);
        }

        return back()->with('success', '步骤已创建。');
    }

    public function update(Request $request, TodoStep $todoStep): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'execution_type' => ['required', 'in:AI,Human,Hybrid'],
            'status' => ['required', 'in:pending,in_progress,waiting_confirmation,completed,cancelled'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'ai_prompt' => ['nullable', 'string'],
            'ai_result' => ['nullable', 'string'],
            'requires_human_confirmation' => ['boolean'],
        ]);

        $data['completed_at'] = $data['status'] === 'completed' ? now() : null;
        $todoStep->update($data);

        return back()->with('success', '步骤已更新。');
    }

    public function updateStatus(Request $request, TodoStep $todoStep): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,in_progress,waiting_confirmation,completed,cancelled'],
        ]);

        $todoStep->update([
            'status' => $data['status'],
            'completed_at' => $data['status'] === 'completed' ? now() : null,
        ]);

        if (in_array($data['status'], ['completed', 'cancelled'], true)) {
            $todoStep->reminders()->where('status', 'pending')->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }

        $todo = $todoStep->todo;

        if ($todo->status !== 'completed' && ! $todo->steps()->whereNotIn('status', ['completed', 'cancelled'])->exists()) {
            $todo->update(['status' => 'waiting_confirmation']);
        }

        return back()->with('success', '步骤状态已更新。');
    }

    public function destroy(TodoStep $todoStep): RedirectResponse
    {
        $todoStep->delete();

        return back()->with('success', '步骤已删除。');
    }
}
