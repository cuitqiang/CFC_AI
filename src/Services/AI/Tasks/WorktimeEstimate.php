<?php
declare(strict_types=1);

namespace Services\AI\Tasks;

/**
 * 工时估算任务
 * 根据项目描述自动估算工作量和工时
 */
class WorktimeEstimate extends BaseTask
{
    protected function initialize(): void
    {
        $this->name = 'worktime_estimate';
        $this->description = '根据项目需求估算工作量和所需工时';
    }

    /**
     * 执行工时估算
     *
     * @param array $input ['project_description' => '项目描述', 'team_size' => 团队规模]
     * @return array 估算结果
     */
    public function execute(array $input): array
    {
        try {
            $this->validateInput($input, ['project_description']);

            $description = $input['project_description'];
            $teamSize = $input['team_size'] ?? 1;
            $complexity = $input['complexity'] ?? 'medium';

            $this->log('info', 'Starting worktime estimation', [
                'team_size' => $teamSize,
                'complexity' => $complexity
            ]);

            // 构建估算提示
            $prompt = $this->buildEstimatePrompt($description, $teamSize, $complexity);

            // 调用 AI 进行估算
            $metadata = [
                'user_id' => $input['user_id'] ?? 'system',
                'model' => $this->config['model'] ?? 'deepseek-chat',
            ];

            $response = $this->callAI($prompt, $metadata);

            if (!$response['success']) {
                return $this->error($response['error'] ?? '估算失败');
            }

            // 解析估算结果
            $estimate = $this->parseEstimateResult($response['message']);

            $this->log('info', 'Worktime estimation completed', [
                'total_hours' => $estimate['total_hours'] ?? 0
            ]);

            return $this->success($estimate, '工时估算完成');

        } catch (\Throwable $e) {
            $this->log('error', 'Worktime estimation failed', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * 构建估算提示
     */
    private function buildEstimatePrompt(string $description, int $teamSize, string $complexity): string
    {
        $complexityInstructions = match ($complexity) {
            'low' => '这是一个相对简单的项目。',
            'medium' => '这是一个中等复杂度的项目。',
            'high' => '这是一个高复杂度的项目，需要仔细评估。',
            default => '',
        };

        return <<<PROMPT
你是一位经验丰富的项目经理。请根据以下项目描述，估算完成该项目所需的工时。

团队规模：{$teamSize} 人
复杂度：{$complexity}
{$complexityInstructions}

请按以下格式输出：

## 任务分解
1. [任务名称] - [预估工时]
2. [任务名称] - [预估工时]
...

## 总工时估算
- 开发工时：[X] 小时
- 测试工时：[Y] 小时
- 部署工时：[Z] 小时
- 总计：[Total] 小时

## 进度安排
- 预计开始时间：[日期]
- 预计完成时间：[日期]
- 总工期：[X] 天

## 风险因素
1. [风险描述]
2. [风险描述]
...

## 建议
[针对该项目的建议和注意事项]

---

项目描述：

{$description}
PROMPT;
    }

    /**
     * 解析估算结果
     */
    private function parseEstimateResult(string $aiResponse): array
    {
        $result = [
            'raw_response' => $aiResponse,
            'tasks' => [],
            'development_hours' => 0,
            'testing_hours' => 0,
            'deployment_hours' => 0,
            'total_hours' => 0,
            'duration_days' => 0,
            'risks' => [],
            'suggestions' => '',
        ];

        // 提取任务分解
        if (preg_match('/##\s*任务分解\s*\n(.*?)(?=##|$)/su', $aiResponse, $matches)) {
            $result['tasks'] = $this->extractTasks($matches[1]);
        }

        // 提取总工时
        if (preg_match('/开发工时[：:]\s*(\d+)/u', $aiResponse, $matches)) {
            $result['development_hours'] = (int) $matches[1];
        }

        if (preg_match('/测试工时[：:]\s*(\d+)/u', $aiResponse, $matches)) {
            $result['testing_hours'] = (int) $matches[1];
        }

        if (preg_match('/部署工时[：:]\s*(\d+)/u', $aiResponse, $matches)) {
            $result['deployment_hours'] = (int) $matches[1];
        }

        if (preg_match('/总计[：:]\s*(\d+)/u', $aiResponse, $matches)) {
            $result['total_hours'] = (int) $matches[1];
        }

        // 提取工期
        if (preg_match('/总工期[：:]\s*(\d+)/u', $aiResponse, $matches)) {
            $result['duration_days'] = (int) $matches[1];
        }

        // 提取风险因素
        if (preg_match('/##\s*风险因素\s*\n(.*?)(?=##|$)/su', $aiResponse, $matches)) {
            $result['risks'] = $this->extractListItems($matches[1]);
        }

        // 提取建议
        if (preg_match('/##\s*建议\s*\n(.*?)$/su', $aiResponse, $matches)) {
            $result['suggestions'] = trim($matches[1]);
        }

        return $result;
    }

    /**
     * 提取任务列表
     */
    private function extractTasks(string $text): array
    {
        $tasks = [];
        $lines = explode("\n", trim($text));

        foreach ($lines as $line) {
            $line = trim($line);

            if (preg_match('/^\d+\.\s*(.+?)\s*[-–]\s*(\d+)/u', $line, $matches)) {
                $tasks[] = [
                    'name' => trim($matches[1]),
                    'hours' => (int) $matches[2],
                ];
            }
        }

        return $tasks;
    }

    /**
     * 提取列表项
     */
    private function extractListItems(string $text): array
    {
        $items = [];
        $lines = explode("\n", trim($text));

        foreach ($lines as $line) {
            $line = trim($line);

            if (preg_match('/^\d+\.\s*(.+)$/u', $line, $matches)) {
                $items[] = trim($matches[1]);
            }
        }

        return $items;
    }
}
