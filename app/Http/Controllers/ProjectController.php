<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Projects/Index', [
            'projects' => Project::query()
                ->with(['workspace', 'modules'])
                ->withCount('todos')
                ->inDisplayOrder()
                ->get(),
            'workspaces' => Workspace::orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'workspace_id' => ['required', 'exists:workspaces,id'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', 'in:P0,P1,P2,P3'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'status' => ['required', 'in:active,paused,completed,cancelled'],
            'due_at' => ['nullable', 'date'],
        ]);

        Project::create([
            ...$data,
            'slug' => Str::slug($data['name']),
            'sort_order' => $data['sort_order'] ?? ((int) Project::max('sort_order') + 10),
        ]);

        return back()->with('success', '项目已创建。');
    }

    public function show(Project $project): Response
    {
        return Inertia::render('Projects/Show', [
            'project' => $project->load(['workspace', 'modules', 'todos.module', 'todos.steps']),
            'workspaces' => Workspace::orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'workspace_id' => ['required', 'exists:workspaces,id'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', 'in:P0,P1,P2,P3'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'status' => ['required', 'in:active,paused,completed,cancelled'],
            'due_at' => ['nullable', 'date'],
        ]);

        $project->update([
            ...$data,
            'slug' => Str::slug($data['name']),
        ]);

        return back()->with('success', '项目已更新。');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $project->delete();

        return redirect()->route('projects.index')->with('success', '项目已删除。');
    }
}
