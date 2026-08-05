<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectMemorySource;
use App\Models\Workspace;
use App\Services\CodexMemory\MemorySyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class MemorySourceController extends Controller
{
    public function index(MemorySyncService $syncService): Response
    {
        try {
            $syncService->syncIfDue();
        } catch (Throwable $exception) {
            report($exception);
        }

        $sources = ProjectMemorySource::query()
            ->with('project.workspace')
            ->withCount([
                'entries as current_entries_count' => fn ($query) => $query->where('is_current', true),
                'entries as completed_entries_count' => fn ($query) => $query
                    ->where('is_current', true)
                    ->where('outcome', 'completed'),
                'entries as todo_entries_count' => fn ($query) => $query
                    ->where('is_current', true)
                    ->whereIn('outcome', ['todo', 'doing'])
                    ->where('evidence->scope', 'current_summary'),
                'entries as history_entries_count' => fn ($query) => $query
                    ->where('is_current', true)
                    ->where('evidence->scope', 'historical_snapshot'),
            ])
            ->orderByRaw("case status when 'pending' then 0 when 'linked' then 1 else 2 end")
            ->orderBy('discovered_name')
            ->get();
        $memoryRoot = rtrim((string) config('codex-memory.root'), DIRECTORY_SEPARATOR);

        return Inertia::render('MemorySources/Index', [
            'sources' => $sources,
            'projects' => Project::query()->with('workspace')->inDisplayOrder()->get(),
            'workspaces' => Workspace::orderBy('sort_order')->get(),
            'memoryConfig' => [
                'root' => $memoryRoot,
                'root_exists' => is_dir($memoryRoot),
                'engineering_roots' => config('codex-memory.engineering_roots', []),
            ],
            'stats' => [
                'pending_sources' => $sources->where('status', 'pending')->count(),
                'linked_sources' => $sources->where('status', 'linked')->count(),
                'current_entries' => $sources->sum('current_entries_count'),
                'todo_entries' => $sources->sum('todo_entries_count'),
                'history_entries' => $sources->sum('history_entries_count'),
            ],
        ]);
    }

    public function sync(MemorySyncService $syncService): RedirectResponse
    {
        try {
            $result = $syncService->sync();
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', $exception->getMessage());
        }

        return back()->with(
            'success',
            "已发现 {$result['sources']} 个项目知识库、{$result['entries']} 条任务记忆；"
            ."{$result['pending_sources']} 个路径等待关联，新增 {$result['imported_todos']} 项任务。",
        );
    }

    public function update(
        Request $request,
        ProjectMemorySource $memorySource,
        MemorySyncService $syncService,
    ): RedirectResponse {
        $data = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
        ]);
        $project = Project::findOrFail($data['project_id']);
        $created = $syncService->link($memorySource, $project);

        return back()->with(
            'success',
            "“{$memorySource->scope_cwd}”已关联到“{$project->name}”，新增 {$created} 项任务。",
        );
    }

    public function storeProject(
        Request $request,
        ProjectMemorySource $memorySource,
        MemorySyncService $syncService,
    ): RedirectResponse {
        $data = $request->validate([
            'workspace_id' => ['required', 'exists:workspaces,id'],
            'name' => ['required', 'string', 'max:160'],
            'priority' => ['required', 'in:P0,P1,P2,P3'],
        ]);
        [$project, $created] = DB::transaction(function () use (
            $data,
            $memorySource,
            $syncService,
        ): array {
            $slug = $this->uniqueProjectSlug((int) $data['workspace_id'], $data['name']);
            $project = Project::create([
                'workspace_id' => $data['workspace_id'],
                'name' => $data['name'],
                'slug' => $slug,
                'description' => "由项目记忆知识库创建：{$memorySource->registry_path}",
                'priority' => $data['priority'],
                'sort_order' => (int) Project::max('sort_order') + 10,
                'status' => 'active',
            ]);

            return [$project, $syncService->link($memorySource, $project)];
        });

        return back()->with(
            'success',
            "已创建项目“{$project->name}”并关联记忆路径，新增 {$created} 项任务。",
        );
    }

    public function ignore(
        ProjectMemorySource $memorySource,
        MemorySyncService $syncService,
    ): RedirectResponse {
        $syncService->ignore($memorySource);

        return back()->with('success', "已忽略“{$memorySource->scope_cwd}”。");
    }

    private function uniqueProjectSlug(int $workspaceId, string $name): string
    {
        $base = Str::slug($name) ?: 'project';
        $slug = $base;
        $suffix = 2;

        while (Project::where('workspace_id', $workspaceId)->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
