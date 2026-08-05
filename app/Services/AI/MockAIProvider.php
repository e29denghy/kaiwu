<?php

namespace App\Services\AI;

use App\Models\Todo;
use App\Models\TodoStep;
use Illuminate\Support\Str;

class MockAIProvider implements AIProvider
{
    public function __construct(private readonly CodexPromptGenerator $codexPromptGenerator) {}

    public function analyzeTodo(Todo $todo): array
    {
        $steps = $this->splitTodoIntoSteps($todo);

        return [
            'provider' => 'mock',
            'summary' => 'Mock analysis generated deterministic first-stage workflow steps.',
            'recommended_next_action' => $steps[0]['title'] ?? 'Review this todo manually.',
            'steps' => $steps,
        ];
    }

    public function splitTodoIntoSteps(Todo $todo): array
    {
        $isDevelopment = Str::of($todo->title.' '.$todo->description)
            ->lower()
            ->contains(['code', '开发', 'bug', 'sdk', 'api', 'laravel', 'vue', 'codex']);

        $steps = [
            [
                'title' => '澄清目标和完成标准',
                'description' => '确认任务边界、验收标准、截止时间和是否阻塞 P0 项目。',
                'execution_type' => 'Human',
                'requires_human_confirmation' => true,
            ],
            [
                'title' => $isDevelopment ? '生成 Codex 执行方案' : '整理 AI 可辅助材料',
                'description' => $isDevelopment
                    ? '把开发任务转成 Codex 可以直接执行的代码修改提示词。'
                    : '汇总背景、约束、可选方案和需要人工判断的问题。',
                'execution_type' => $isDevelopment ? 'AI' : 'Hybrid',
                'requires_human_confirmation' => ! $isDevelopment,
            ],
            [
                'title' => '人工确认结果并推进下一步',
                'description' => '检查 AI 产物或决策材料，决定继续执行、调整范围或标记完成。',
                'execution_type' => 'Human',
                'requires_human_confirmation' => true,
            ],
        ];

        return collect($steps)->map(function (array $step, int $index) use ($todo): array {
            $step['sort_order'] = $index + 1;
            $step['status'] = 'pending';
            $step['ai_prompt'] = in_array($step['execution_type'], ['AI', 'Hybrid'], true)
                ? $this->codexPromptGenerator->forStep($step, $todo)
                : null;

            return $step;
        })->all();
    }

    public function classifyStep(array $step): string
    {
        if (Str::of(($step['title'] ?? '').' '.($step['description'] ?? ''))->lower()->contains(['确认', '决策', 'review'])) {
            return 'Human';
        }

        if (Str::of(($step['title'] ?? '').' '.($step['description'] ?? ''))->lower()->contains(['codex', '代码', '生成', '整理'])) {
            return 'AI';
        }

        return 'Hybrid';
    }

    public function generatePromptForStep(TodoStep|array $step): string
    {
        return $this->codexPromptGenerator->forStep($step, $step instanceof TodoStep ? $step->todo : null);
    }

    public function executeStep(TodoStep $step): array
    {
        return [
            'provider' => 'mock',
            'status' => 'skipped',
            'result' => 'First stage only prepares prompts and execution records. Real provider execution is reserved.',
            'step_id' => $step->id,
        ];
    }
}
