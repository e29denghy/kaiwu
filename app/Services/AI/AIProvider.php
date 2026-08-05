<?php

namespace App\Services\AI;

use App\Models\Todo;
use App\Models\TodoStep;

interface AIProvider
{
    public function analyzeTodo(Todo $todo): array;

    public function splitTodoIntoSteps(Todo $todo): array;

    public function classifyStep(array $step): string;

    public function generatePromptForStep(TodoStep|array $step): string;

    public function executeStep(TodoStep $step): array;
}
