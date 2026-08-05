<?php

namespace App\Http\Controllers;

use App\Models\Reminder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReminderController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Reminders/Index', [
            'reminders' => Reminder::with(['todo.project', 'step'])->orderBy('remind_at')->paginate(30),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Reminder::create($request->validate([
            'todo_id' => ['nullable', 'exists:todos,id'],
            'todo_step_id' => ['nullable', 'exists:todo_steps,id'],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string'],
            'remind_at' => ['required', 'date'],
            'channel' => ['required', 'in:system,mac,windows,email,browser'],
            'status' => ['required', 'in:pending,sent,completed,cancelled'],
        ]));

        return back()->with('success', '提醒已创建。');
    }

    public function update(Request $request, Reminder $reminder): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string'],
            'remind_at' => ['required', 'date'],
            'channel' => ['required', 'in:system,mac,windows,email,browser'],
            'status' => ['required', 'in:pending,sent,completed,cancelled'],
        ]);

        $data['completed_at'] = $data['status'] === 'completed' ? now() : null;
        $reminder->update($data);

        return back()->with('success', '提醒已更新。');
    }

    public function complete(Reminder $reminder): RedirectResponse
    {
        $reminder->update([
            'status' => 'completed',
            'read_at' => now(),
            'completed_at' => now(),
        ]);

        return back()->with('success', '提醒已完成。');
    }

    public function destroy(Reminder $reminder): RedirectResponse
    {
        $reminder->delete();

        return back()->with('success', '提醒已删除。');
    }
}
