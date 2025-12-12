<?php
declare(strict_types=1);

namespace Services\AI\Analytics;

/**
 * 成本计算器
 * 计算 AI API 调用的成本
 */
class CostCalculator
{
    private array $pricing = [];

    public function __construct()
    {
        $this->initializePricing();
    }

    /**
     * 初始化定价信息
     */
    private function initializePricing(): void
    {
        // Deepseek 定价（每百万 tokens）
        $this->pricing['deepseek'] = [
            'deepseek-chat' => [
                'input' => 0.14,   // $0.14 per 1M tokens
                'output' => 0.28,  // $0.28 per 1M tokens
                'cache_write' => 0.14,
                'cache_read' => 0.014,
            ],
            'deepseek-coder' => [
                'input' => 0.14,
                'output' => 0.28,
            ],
            'deepseek-reasoner' => [
                'input' => 0.55,
                'output' => 2.19,
            ],
            'deepseek-v3' => [
                'input' => 0.27,   // $0.27 per 1M tokens (latest pricing)
                'output' => 1.10,  // $1.10 per 1M tokens
                'cache_write' => 0.27,
                'cache_read' => 0.027,
            ],
        ];

        // OpenAI 定价（每百万 tokens）
        $this->pricing['openai'] = [
            'gpt-4o' => [
                'input' => 2.50,
                'output' => 10.00,
            ],
            'gpt-4o-mini' => [
                'input' => 0.15,
                'output' => 0.60,
            ],
            'gpt-4-turbo' => [
                'input' => 10.00,
                'output' => 30.00,
            ],
            'gpt-3.5-turbo' => [
                'input' => 0.50,
                'output' => 1.50,
            ],
        ];

        // Embedding 定价
        $this->pricing['embedding'] = [
            'text-embedding-3-small' => [
                'input' => 0.02,
            ],
            'text-embedding-3-large' => [
                'input' => 0.13,
            ],
            'text-embedding-ada-002' => [
                'input' => 0.10,
            ],
        ];
    }

    /**
     * 计算单次调用的成本
     *
     * @param string $model 模型名称
     * @param int $inputTokens 输入 tokens
     * @param int $outputTokens 输出 tokens
     * @return float 成本（美元）
     */
    public function calculateCost(string $model, int $inputTokens, int $outputTokens = 0): float
    {
        $modelPricing = $this->getModelPricing($model);

        if ($modelPricing === null) {
            return 0.0;
        }

        $inputCost = ($inputTokens / 1_000_000) * ($modelPricing['input'] ?? 0);
        $outputCost = ($outputTokens / 1_000_000) * ($modelPricing['output'] ?? 0);

        return $inputCost + $outputCost;
    }

    /**
     * 计算缓存成本
     *
     * @param string $model 模型名称
     * @param int $cacheWriteTokens 缓存写入 tokens
     * @param int $cacheReadTokens 缓存读取 tokens
     * @return float 成本（美元）
     */
    public function calculateCacheCost(
        string $model,
        int $cacheWriteTokens,
        int $cacheReadTokens
    ): float {
        $modelPricing = $this->getModelPricing($model);

        if ($modelPricing === null) {
            return 0.0;
        }

        $writeCost = ($cacheWriteTokens / 1_000_000) * ($modelPricing['cache_write'] ?? 0);
        $readCost = ($cacheReadTokens / 1_000_000) * ($modelPricing['cache_read'] ?? 0);

        return $writeCost + $readCost;
    }

    /**
     * 根据使用量统计计算总成本
     *
     * @param array $usage 使用量统计
     * @return array 成本明细
     */
    public function calculateTotalCost(array $usage): array
    {
        $totalCost = 0.0;
        $breakdown = [];

        foreach ($usage as $model => $data) {
            $inputTokens = $data['input_tokens'] ?? 0;
            $outputTokens = $data['output_tokens'] ?? 0;

            $cost = $this->calculateCost($model, $inputTokens, $outputTokens);

            $breakdown[$model] = [
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost' => $cost,
            ];

            $totalCost += $cost;
        }

        return [
            'total_cost' => $totalCost,
            'breakdown' => $breakdown,
            'currency' => 'USD',
        ];
    }

    /**
     * 估算成本（根据输入长度）
     *
     * @param string $model 模型名称
     * @param int $inputTokens 输入 tokens
     * @param float $outputRatio 输出/输入比例
     * @return float 估算成本
     */
    public function estimateCost(string $model, int $inputTokens, float $outputRatio = 1.0): float
    {
        $outputTokens = (int) ($inputTokens * $outputRatio);

        return $this->calculateCost($model, $inputTokens, $outputTokens);
    }

    /**
     * 获取模型定价
     */
    private function getModelPricing(string $model): ?array
    {
        // 搜索所有提供者
        foreach ($this->pricing as $provider => $models) {
            if (isset($models[$model])) {
                return $models[$model];
            }
        }

        return null;
    }

    /**
     * 设置自定义定价
     *
     * @param string $model 模型名称
     * @param array $pricing 定价信息
     */
    public function setCustomPricing(string $model, array $pricing): void
    {
        $this->pricing['custom'][$model] = $pricing;
    }

    /**
     * 获取所有定价信息
     *
     * @return array 定价信息
     */
    public function getAllPricing(): array
    {
        return $this->pricing;
    }

    /**
     * 计算成本节省（对比两个模型）
     *
     * @param string $model1 模型1
     * @param string $model2 模型2
     * @param int $inputTokens 输入 tokens
     * @param int $outputTokens 输出 tokens
     * @return array 对比结果
     */
    public function compareCosts(
        string $model1,
        string $model2,
        int $inputTokens,
        int $outputTokens
    ): array {
        $cost1 = $this->calculateCost($model1, $inputTokens, $outputTokens);
        $cost2 = $this->calculateCost($model2, $inputTokens, $outputTokens);

        $savings = $cost1 - $cost2;
        $savingsPercent = $cost1 > 0 ? ($savings / $cost1) * 100 : 0;

        return [
            'model1' => [
                'name' => $model1,
                'cost' => $cost1,
            ],
            'model2' => [
                'name' => $model2,
                'cost' => $cost2,
            ],
            'savings' => $savings,
            'savings_percent' => $savingsPercent,
            'cheaper_model' => $cost1 < $cost2 ? $model1 : $model2,
        ];
    }

    /**
     * 预测月度成本
     *
     * @param string $model 模型名称
     * @param int $dailyRequests 每日请求数
     * @param int $avgInputTokens 平均输入 tokens
     * @param int $avgOutputTokens 平均输出 tokens
     * @return array 预测结果
     */
    public function predictMonthlyCost(
        string $model,
        int $dailyRequests,
        int $avgInputTokens,
        int $avgOutputTokens
    ): array {
        $costPerRequest = $this->calculateCost($model, $avgInputTokens, $avgOutputTokens);
        $dailyCost = $costPerRequest * $dailyRequests;
        $monthlyCost = $dailyCost * 30;

        return [
            'model' => $model,
            'daily_requests' => $dailyRequests,
            'cost_per_request' => $costPerRequest,
            'daily_cost' => $dailyCost,
            'monthly_cost' => $monthlyCost,
            'currency' => 'USD',
        ];
    }
}
