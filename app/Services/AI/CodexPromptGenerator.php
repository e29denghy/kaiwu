<?php

namespace App\Services\AI;

use App\Models\Todo;
use App\Models\TodoStep;

class CodexPromptGenerator
{
    public function forStep(TodoStep|array $step, ?Todo $todo = null): string
    {
        $title = data_get($step, 'title');
        $description = data_get($step, 'description');
        $todoTitle = $todo?->title ?? data_get($step, 'todo.title');
        $todoDescription = $todo?->description ?? data_get($step, 'todo.description');

        return trim(<<<PROMPT
You are an execution agent receiving a human-approved Quest from KAIWU.

Goal:
{$todoTitle}

Context:
{$todoDescription}

Task step:
{$title}

Step details:
{$description}

Please inspect the repository first, make the smallest correct implementation, run relevant checks, and report changed files plus verification results.
PROMPT);
    }
}
