<?php

namespace App\Http\Controllers;

use App\Models\HarnessConnection;
use App\Models\Project;
use App\Models\Quest;
use App\Services\Quests\QuestWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class QuestController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Quests/Index', [
            'quests' => Quest::query()
                ->with(['project', 'executions.connection'])
                ->latest()
                ->get(),
            'projects' => Project::query()->inDisplayOrder()->get(),
            'connections' => HarnessConnection::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'project_id' => ['nullable', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:180'],
            'goal' => ['required', 'string', 'max:5000'],
            'acceptance_criteria' => ['required', 'array', 'min:1'],
            'acceptance_criteria.*' => ['required', 'string', 'max:1000'],
            'constraints' => ['nullable', 'array'],
            'constraints.*' => ['required', 'string', 'max:1000'],
            'verification' => ['nullable', 'array'],
            'verification.*' => ['required', 'string', 'max:1000'],
            'risk_level' => ['required', 'in:low,medium,high'],
            'requires_write' => ['required', 'boolean'],
            'execution_mode' => ['required', 'in:immediate,scheduled'],
            'scheduled_for' => ['nullable', 'date'],
        ]);

        Quest::create([
            ...$data,
            'approval_status' => 'pending',
            'status' => 'awaiting_approval',
        ]);

        return back()->with('success', 'Quest 已创建，等待人工批准。');
    }

    public function approve(Quest $quest, QuestWorkflow $workflow): RedirectResponse
    {
        try {
            $workflow->approve($quest);
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', "Quest“{$quest->title}”已批准。");
    }

    public function dispatch(
        Request $request,
        Quest $quest,
        QuestWorkflow $workflow,
    ): RedirectResponse {
        $data = $request->validate([
            'harness_connection_id' => ['required', 'exists:harness_connections,id'],
        ]);
        $connection = HarnessConnection::findOrFail($data['harness_connection_id']);

        try {
            $execution = $workflow->dispatch($quest, $connection);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', "Quest 已派发：{$execution->dispatch_id}");
    }

    public function cancel(Quest $quest, QuestWorkflow $workflow): RedirectResponse
    {
        try {
            $workflow->cancel($quest);
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', "Quest“{$quest->title}”已取消。");
    }
}
