<?php

namespace App\Http\Controllers;

use App\Models\HarnessConnection;
use App\Models\HarnessEvent;
use App\Services\Harness\HarnessSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class HarnessConnectionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Harnesses/Index', [
            'connections' => HarnessConnection::query()
                ->withCount('events')
                ->orderBy('name')
                ->get(),
            'events' => HarnessEvent::query()
                ->with(['connection', 'project'])
                ->latest('occurred_at')
                ->limit(50)
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'driver' => ['required', 'in:jsonl'],
            'inbox_path' => ['required', 'string', 'max:2048'],
            'outbox_path' => ['required', 'string', 'max:2048'],
        ]);
        $baseSlug = Str::slug($data['name']) ?: 'harness';
        $slug = $baseSlug;
        $suffix = 2;

        while (HarnessConnection::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        HarnessConnection::create([
            'name' => $data['name'],
            'slug' => $slug,
            'driver' => $data['driver'],
            'status' => 'active',
            'configuration' => [
                'inbox_path' => $data['inbox_path'],
                'outbox_path' => $data['outbox_path'],
            ],
        ]);

        return back()->with('success', "Harness 连接“{$data['name']}”已创建。");
    }

    public function sync(
        HarnessConnection $harnessConnection,
        HarnessSyncService $syncService,
    ): RedirectResponse {
        try {
            $result = $syncService->sync($harnessConnection);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', $exception->getMessage());
        }

        return back()->with(
            'success',
            "同步完成：新增 {$result->created}，更新 {$result->updated}，跳过 {$result->skipped}，错误 ".count($result->errors).'。',
        );
    }
}
