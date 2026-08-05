<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Reminder;
use App\Models\Todo;
use App\Models\TodoStep;
use App\Services\CodexMemory\MemorySyncService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        MemorySyncService $memorySyncService,
    ): Response {
        try {
            $memorySyncService->syncIfDue();
        } catch (Throwable $exception) {
            report($exception);
        }

        $activeStatuses = ['pending', 'in_progress', 'waiting_confirmation'];
        $todayStart = today()->startOfDay();
        $todayEnd = today()->endOfDay();
        $projects = Project::query()
            ->with(['workspace', 'modules'])
            ->withCount([
                'todos',
                'todos as current_todos_count' => fn (Builder $query) => $query
                    ->where('planning_state', '!=', 'archive'),
                'todos as open_todos_count' => fn (Builder $query) => $query
                    ->where('planning_state', '!=', 'archive')
                    ->whereIn('status', $activeStatuses),
                'todos as completed_todos_count' => fn (Builder $query) => $query
                    ->where('planning_state', '!=', 'archive')
                    ->where('status', 'completed'),
                'todos as today_active_todos_count' => function (Builder $query) use (
                    $todayStart,
                    $todayEnd,
                ): void {
                    $this->applyActivityRange($query, $todayStart, $todayEnd);
                },
                'todos as today_completed_todos_count' => function (Builder $query) use (
                    $todayStart,
                    $todayEnd,
                ): void {
                    $query->where('status', 'completed');
                    $this->applyActivityRange($query, $todayStart, $todayEnd);
                },
            ])
            ->where('status', 'active')
            ->inDisplayOrder()
            ->get();
        $todayActiveProjects = $projects
            ->where('today_active_todos_count', '>', 0)
            ->values();
        $todayFocus = Todo::query()
            ->with(['workspace', 'project', 'module', 'memoryEntry'])
            ->where('planning_state', 'today')
            ->whereDate('scheduled_for', today())
            ->whereIn('status', $activeStatuses)
            ->orderByRaw('case when focus_rank is null then 4 else focus_rank end')
            ->orderByRaw("case priority when 'P0' then 0 when 'P1' then 1 when 'P2' then 2 else 3 end")
            ->limit(3)
            ->get();
        $todayWaiting = Todo::query()
            ->with(['project', 'module'])
            ->where(function (Builder $query): void {
                $query
                    ->where('planning_state', 'waiting')
                    ->orWhere('status', 'waiting_confirmation');
            })
            ->where('status', '!=', 'cancelled')
            ->orderByRaw("case priority when 'P0' then 0 when 'P1' then 1 when 'P2' then 2 else 3 end")
            ->latest('updated_at')
            ->limit(5)
            ->get();
        $todayFocusCompleted = Todo::query()
            ->where('planning_state', 'today')
            ->whereDate('scheduled_for', today())
            ->where('status', 'completed')
            ->count();
        $period = in_array($request->string('period')->toString(), [
            'today',
            '7d',
            '30d',
            'all',
        ], true)
            ? $request->string('period')->toString()
            : 'today';
        $status = in_array($request->string('status')->toString(), [
            'all',
            'todo',
            'completed',
        ], true)
            ? $request->string('status')->toString()
            : 'all';
        $requestedProjectId = $request->integer('project');
        $projectId = $requestedProjectId > 0 && $projects->contains('id', $requestedProjectId)
            ? $requestedProjectId
            : null;
        $projectTasks = Todo::query()
            ->with(['workspace', 'project', 'module', 'memoryEntry'])
            ->where('status', '!=', 'cancelled')
            ->where('planning_state', '!=', 'archive')
            ->when($projectId !== null, fn (Builder $query) => $query->where('project_id', $projectId))
            ->when(
                $status === 'todo',
                fn (Builder $query) => $query->whereIn('status', $activeStatuses),
            )
            ->when(
                $status === 'completed',
                fn (Builder $query) => $query->where('status', 'completed'),
            );
        $periodRange = $this->periodRange($period);

        if ($periodRange !== null) {
            $this->applyActivityRange($projectTasks, ...$periodRange);
        }

        $projectTasks = $projectTasks
            ->orderByRaw("case status when 'in_progress' then 0 when 'pending' then 1 when 'waiting_confirmation' then 2 when 'completed' then 3 else 4 end")
            ->orderByRaw("case priority when 'P0' then 0 when 'P1' then 1 when 'P2' then 2 else 3 end")
            ->orderByRaw('case when due_at is null then 1 else 0 end')
            ->orderBy('due_at')
            ->latest('updated_at')
            ->limit(30)
            ->get()
            ->each(function (Todo $todo): void {
                $todo->setAttribute(
                    'activity_at',
                    $todo->status === 'completed'
                        ? $todo->completed_at
                        : ($todo->memoryEntry?->source_updated_at ?? $todo->due_at ?? $todo->updated_at),
                );
            });
        $todayActiveTasks = Todo::query()->where('status', '!=', 'cancelled');
        $this->applyActivityRange($todayActiveTasks, $todayStart, $todayEnd);
        $todayCompletedTasks = Todo::query()->where('status', 'completed');
        $this->applyActivityRange($todayCompletedTasks, $todayStart, $todayEnd);

        return Inertia::render('Dashboard', [
            'stats' => [
                'today_projects' => $todayActiveProjects->count(),
                'today_tasks' => $todayActiveTasks->count(),
                'today_completed' => $todayCompletedTasks->count(),
                'today_focus' => $todayFocus->count(),
                'today_focus_completed' => $todayFocusCompleted,
                'today_waiting' => $todayWaiting->count(),
                'open_tasks' => Todo::where('planning_state', '!=', 'archive')
                    ->whereIn('status', $activeStatuses)
                    ->count(),
            ],
            'activeProjects' => $projects,
            'todayActiveProjects' => $todayActiveProjects,
            'todayFocus' => $todayFocus,
            'todayWaiting' => $todayWaiting,
            'projectTasks' => $projectTasks,
            'filters' => [
                'project_id' => $projectId,
                'period' => $period,
                'status' => $status,
            ],
            'projectProgress' => $projects->take(10)->values(),
            'aiExecutableSteps' => TodoStep::with('todo.project')
                ->where('execution_type', 'AI')
                ->where('status', 'pending')
                ->whereHas('todo', fn (Builder $query) => $query->where('planning_state', '!=', 'archive'))
                ->orderBy('sort_order')
                ->limit(8)
                ->get(),
            'humanDecisionSteps' => TodoStep::with('todo.project')
                ->where('requires_human_confirmation', true)
                ->whereIn('status', ['pending', 'waiting_confirmation'])
                ->whereHas('todo', fn (Builder $query) => $query->where('planning_state', '!=', 'archive'))
                ->limit(8)
                ->get(),
            'upcomingReminders' => Reminder::with(['todo.project', 'step'])
                ->where('status', 'pending')
                ->orderBy('remind_at')
                ->limit(8)
                ->get(),
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}|null
     */
    private function periodRange(string $period): ?array
    {
        $end = today()->endOfDay();

        return match ($period) {
            'today' => [today()->startOfDay(), $end],
            '7d' => [today()->subDays(6)->startOfDay(), $end],
            '30d' => [today()->subDays(29)->startOfDay(), $end],
            default => null,
        };
    }

    private function applyActivityRange(
        Builder $query,
        Carbon $start,
        Carbon $end,
    ): void {
        $query->where(function (Builder $activityQuery) use ($start, $end): void {
            $activityQuery
                ->whereBetween('due_at', [$start, $end])
                ->orWhereHas(
                    'memoryEntry',
                    fn (Builder $entryQuery) => $entryQuery
                        ->whereIn('evidence->activity_date_source', ['heading', 'title'])
                        ->whereBetween('source_updated_at', [$start, $end]),
                )
                ->orWhere(function (Builder $completedQuery) use ($start, $end): void {
                    $completedQuery
                        ->whereBetween('completed_at', [$start, $end])
                        ->where(function (Builder $sourceQuery): void {
                            $sourceQuery
                                ->whereDoesntHave('memoryEntry')
                                ->orWhereHas(
                                    'memoryEntry',
                                    fn (Builder $entryQuery) => $entryQuery
                                        ->where('outcome', '!=', 'completed'),
                                );
                        });
                })
                ->orWhere(function (Builder $manualQuery) use ($start, $end): void {
                    $manualQuery
                        ->whereDoesntHave('memoryEntry')
                        ->whereBetween('updated_at', [$start, $end]);
                });
        });
    }
}
