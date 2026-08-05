<?php

namespace App\Services\Todos;

use App\Models\Todo;
use App\Services\AI\AIProvider;
use Illuminate\Support\Facades\DB;

class TodoDecompositionService
{
    public function __construct(private readonly AIProvider $aiProvider) {}

    public function decompose(Todo $todo): Todo
    {
        return DB::transaction(function () use ($todo): Todo {
            $analysis = $this->aiProvider->analyzeTodo($todo);

            $todo->steps()->delete();
            $todo->forceFill([
                'ai_analysis' => $analysis,
                'status' => 'waiting_confirmation',
            ])->save();

            foreach ($analysis['steps'] ?? [] as $step) {
                $todo->steps()->create($step);
            }

            return $todo->fresh(['workspace', 'project', 'steps']);
        });
    }
}
