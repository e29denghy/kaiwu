<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Workspaces/Index', [
            'workspaces' => Workspace::withCount(['projects', 'todos'])->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        Workspace::create([
            ...$data,
            'slug' => Str::slug($data['name']),
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return back()->with('success', '空间已创建。');
    }

    public function show(Workspace $workspace): Response
    {
        return Inertia::render('Workspaces/Show', [
            'workspace' => $workspace->load(['projects.todos', 'todos.steps']),
        ]);
    }

    public function update(Request $request, Workspace $workspace): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $workspace->update([
            ...$data,
            'slug' => Str::slug($data['name']),
            'sort_order' => $data['sort_order'] ?? $workspace->sort_order,
        ]);

        return back()->with('success', '空间已更新。');
    }

    public function destroy(Workspace $workspace): RedirectResponse
    {
        $workspace->delete();

        return redirect()->route('workspaces.index')->with('success', '空间已删除。');
    }
}
