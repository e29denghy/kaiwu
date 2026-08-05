<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Todo;
use App\Models\Workspace;
use App\Services\CodexMemory\MemorySyncService;
use App\Services\Reminders\ReminderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class TodoController extends Controller
{
    public function index(Request $request, MemorySyncService $memorySyncService): Response
    {
        try {
            $memorySyncService->syncIfDue();
        } catch (Throwable $exception) {
            report($exception);
        }

        $view = in_array($request->string('view')->toString(), [
            'todo',
            'completed',
            'confirmation',
            'history',
            'all',
        ], true)
            ? $request->string('view')->toString()
            : 'todo';
        $activeStatuses = ['pending', 'in_progress', 'waiting_confirmation'];
        $todos = Todo::with(['workspace', 'project', 'module', 'steps', 'memoryEntry']);

        if ($view === 'history') {
            $todos->where('planning_state', 'archive');
        } elseif ($view !== 'all') {
            $todos->where('planning_state', '!=', 'archive');
        }

        match ($view) {
            'completed' => $todos
                ->where('status', 'completed')
                ->orderByDesc('completed_at'),
            'confirmation' => $todos
                ->where('status', 'waiting_confirmation')
                ->latest(),
            'history' => $todos
                ->orderByRaw("case status when 'in_progress' then 0 when 'pending' then 1 when 'waiting_confirmation' then 2 when 'completed' then 3 else 4 end")
                ->latest(),
            'all' => $todos
                ->orderByRaw("case status when 'pending' then 0 when 'in_progress' then 1 when 'waiting_confirmation' then 2 when 'completed' then 3 else 4 end")
                ->latest(),
            default => $todos
                ->whereIn('status', $activeStatuses)
                ->orderByRaw("case priority when 'P0' then 0 when 'P1' then 1 when 'P2' then 2 else 3 end")
                ->orderBy('due_at')
                ->latest(),
        };

        return Inertia::render('Todos/Index', [
            'todos' => $todos->paginate(20)->withQueryString(),
            'view' => $view,
            'counts' => [
                'todo' => Todo::where('planning_state', '!=', 'archive')->whereIn('status', $activeStatuses)->count(),
                'completed' => Todo::where('planning_state', '!=', 'archive')->where('status', 'completed')->count(),
                'confirmation' => Todo::where('planning_state', '!=', 'archive')->where('status', 'waiting_confirmation')->count(),
                'history' => Todo::where('planning_state', 'archive')->count(),
                'all' => Todo::count(),
            ],
            'workspaces' => Workspace::orderBy('sort_order')->get(),
            'projects' => Project::query()->with(['workspace', 'modules'])->inDisplayOrder()->get(),
        ]);
    }

    public function store(Request $request, ReminderService $reminderService): RedirectResponse
    {
        $data = $request->validate([
            'workspace_id' => ['required', 'exists:workspaces,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', 'in:P0,P1,P2,P3'],
            'status' => ['required', 'in:pending,in_progress,waiting_confirmation,completed,cancelled'],
            'planning_state' => ['sometimes', 'in:backlog,next,today,waiting,archive'],
            'due_at' => ['nullable', 'date'],
            'scheduled_for' => ['nullable', 'date'],
            'focus_rank' => ['nullable', 'integer', 'min:1', 'max:3'],
            'project_module_id' => ['nullable', 'exists:project_modules,id'],
        ]);

        $data['planning_state'] ??= 'backlog';
        $data = $this->normalizePlanningData($data);
        $this->ensureDailyFocusCapacity($data);

        $todo = Todo::create([
            ...$data,
            'completed_at' => $data['status'] === 'completed' ? now() : null,
        ]);
        $reminderService->createForTodo($todo);

        return back()->with('success', '任务已创建。');
    }

    public function show(Todo $todo): Response
    {
        return Inertia::render('Todos/Show', [
            'todo' => $todo->load(['workspace', 'project', 'module', 'steps.reminders', 'reminders']),
            'workspaces' => Workspace::orderBy('sort_order')->get(),
            'projects' => Project::query()->with(['workspace', 'modules'])->inDisplayOrder()->get(),
        ]);
    }

    public function update(Request $request, Todo $todo, ReminderService $reminderService): RedirectResponse
    {
        $data = $request->validate([
            'workspace_id' => ['required', 'exists:workspaces,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', 'in:P0,P1,P2,P3'],
            'status' => ['required', 'in:pending,in_progress,waiting_confirmation,completed,cancelled'],
            'planning_state' => ['sometimes', 'in:backlog,next,today,waiting,archive'],
            'due_at' => ['nullable', 'date'],
            'scheduled_for' => ['nullable', 'date'],
            'focus_rank' => ['nullable', 'integer', 'min:1', 'max:3'],
            'project_module_id' => ['nullable', 'exists:project_modules,id'],
        ]);

        $data = $this->normalizePlanningData($data);
        $this->ensureDailyFocusCapacity($data, $todo->id);
        $data['completed_at'] = $data['status'] === 'completed' ? now() : null;
        $todo->update($data);
        $reminderService->createForTodo($todo);

        return back()->with('success', '任务已更新。');
    }

    public function updateStatus(Request $request, Todo $todo, ReminderService $reminderService): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,in_progress,waiting_confirmation,completed,cancelled'],
        ]);

        $updates = [
            'status' => $data['status'],
            'completed_at' => $data['status'] === 'completed' ? now() : null,
        ];

        if ($data['status'] === 'waiting_confirmation' && $todo->planning_state === 'backlog') {
            $updates['planning_state'] = 'waiting';
        }

        $todo->update($updates);

        if (in_array($data['status'], ['completed', 'cancelled'], true)) {
            $todo->reminders()->where('status', 'pending')->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        } else {
            $reminderService->createForTodo($todo->fresh());
        }

        return back()->with('success', '任务状态已更新。');
    }

    public function updatePlan(Request $request, Todo $todo): RedirectResponse
    {
        $data = $request->validate([
            'planning_state' => ['required', 'in:backlog,next,today,waiting,archive'],
            'scheduled_for' => ['nullable', 'date'],
            'focus_rank' => ['nullable', 'integer', 'min:1', 'max:3'],
            'project_module_id' => ['nullable', 'exists:project_modules,id'],
        ]);

        $data = $this->normalizePlanningData($data);
        $this->ensureDailyFocusCapacity($data, $todo->id);
        $todo->update($data);

        return back()->with('success', '任务计划已更新。');
    }

    public function destroy(Todo $todo): RedirectResponse
    {
        $todo->delete();

        return redirect()->route('todos.index')->with('success', '任务已删除。');
    }

    /** @param  array<string, mixed>  $data */
    private function normalizePlanningData(array $data): array
    {
        if (! array_key_exists('planning_state', $data)) {
            return $data;
        }

        $state = $data['planning_state'];

        if ($state === 'today' && empty($data['scheduled_for'])) {
            $data['scheduled_for'] = today()->toDateString();
        }

        if ($state !== 'today') {
            $data['focus_rank'] = null;
        }

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    private function ensureDailyFocusCapacity(array $data, ?int $exceptTodoId = null): void
    {
        if (($data['planning_state'] ?? null) !== 'today') {
            return;
        }

        $query = Todo::query()
            ->where('planning_state', 'today')
            ->whereDate('scheduled_for', $data['scheduled_for'] ?? today()->toDateString())
            ->whereIn('status', ['pending', 'in_progress', 'waiting_confirmation']);

        if ($exceptTodoId !== null) {
            $query->where('id', '!=', $exceptTodoId);
        }

        if ($query->count() >= 3) {
            throw ValidationException::withMessages([
                'planning_state' => '每天最多安排 3 项今日聚焦，请先完成或移出一项。',
            ]);
        }
    }
}
