<?php
declare(strict_types=1);

namespace Services\AI\Analytics;

/**
 * 使用量追踪器
 * 追踪和统计 AI 系统的使用情况
 */
class UsageTracker
{
    private array $usage = [];
    private array $stats = [];

    /**
     * 记录一次 API 调用
     *
     * @param string $model 模型名称
     * @param int $inputTokens 输入 tokens
     * @param int $outputTokens 输出 tokens
     * @param float $latency 响应延迟（秒）
     * @param bool $success 是否成功
     * @param array $metadata 元数据
     */
    public function track(
        string $model,
        int $inputTokens,
        int $outputTokens,
        float $latency,
        bool $success = true,
        array $metadata = []
    ): void {
        $timestamp = time();
        $date = date('Y-m-d');

        // 初始化模型统计
        if (!isset($this->usage[$model])) {
            $this->usage[$model] = [
                'total_requests' => 0,
                'successful_requests' => 0,
                'failed_requests' => 0,
                'total_input_tokens' => 0,
                'total_output_tokens' => 0,
                'total_latency' => 0.0,
                'daily' => [],
            ];
        }

        // 初始化每日统计
        if (!isset($this->usage[$model]['daily'][$date])) {
            $this->usage[$model]['daily'][$date] = [
                'requests' => 0,
                'input_tokens' => 0,
                'output_tokens' => 0,
                'latency' => 0.0,
            ];
        }

        // 更新总计
        $this->usage[$model]['total_requests']++;
        $this->usage[$model]['total_input_tokens'] += $inputTokens;
        $this->usage[$model]['total_output_tokens'] += $outputTokens;
        $this->usage[$model]['total_latency'] += $latency;

        if ($success) {
            $this->usage[$model]['successful_requests']++;
        } else {
            $this->usage[$model]['failed_requests']++;
        }

        // 更新每日统计
        $this->usage[$model]['daily'][$date]['requests']++;
        $this->usage[$model]['daily'][$date]['input_tokens'] += $inputTokens;
        $this->usage[$model]['daily'][$date]['output_tokens'] += $outputTokens;
        $this->usage[$model]['daily'][$date]['latency'] += $latency;

        // 记录详细日志（可选）
        if (!empty($metadata['log_detail'])) {
            $this->logDetail($model, $inputTokens, $outputTokens, $latency, $success, $metadata);
        }
    }

    /**
     * 获取使用统计
     *
     * @param string|null $model 模型名称（null = 所有模型）
     * @return array 统计信息
     */
    public function getStats(?string $model = null): array
    {
        if ($model !== null) {
            return $this->getModelStats($model);
        }

        // 返回所有模型的统计
        $allStats = [];

        foreach ($this->usage as $modelName => $data) {
            $allStats[$modelName] = $this->getModelStats($modelName);
        }

        return $allStats;
    }

    /**
     * 获取单个模型的统计
     */
    private function getModelStats(string $model): array
    {
        if (!isset($this->usage[$model])) {
            return [];
        }

        $data = $this->usage[$model];
        $totalRequests = $data['total_requests'];

        return [
            'model' => $model,
            'total_requests' => $totalRequests,
            'successful_requests' => $data['successful_requests'],
            'failed_requests' => $data['failed_requests'],
            'success_rate' => $totalRequests > 0
                ? ($data['successful_requests'] / $totalRequests) * 100
                : 0,
            'total_tokens' => $data['total_input_tokens'] + $data['total_output_tokens'],
            'total_input_tokens' => $data['total_input_tokens'],
            'total_output_tokens' => $data['total_output_tokens'],
            'avg_input_tokens' => $totalRequests > 0
                ? $data['total_input_tokens'] / $totalRequests
                : 0,
            'avg_output_tokens' => $totalRequests > 0
                ? $data['total_output_tokens'] / $totalRequests
                : 0,
            'avg_latency' => $totalRequests > 0
                ? $data['total_latency'] / $totalRequests
                : 0,
        ];
    }

    /**
     * 获取每日统计
     *
     * @param string $model 模型名称
     * @param int $days 天数
     * @return array 每日统计
     */
    public function getDailyStats(string $model, int $days = 7): array
    {
        if (!isset($this->usage[$model]['daily'])) {
            return [];
        }

        $dailyData = $this->usage[$model]['daily'];

        // 获取最近 N 天
        $dates = array_keys($dailyData);
        rsort($dates);
        $recentDates = array_slice($dates, 0, $days);

        $stats = [];

        foreach ($recentDates as $date) {
            $data = $dailyData[$date];
            $requests = $data['requests'];

            $stats[$date] = [
                'requests' => $requests,
                'input_tokens' => $data['input_tokens'],
                'output_tokens' => $data['output_tokens'],
                'avg_latency' => $requests > 0 ? $data['latency'] / $requests : 0,
            ];
        }

        return $stats;
    }

    /**
     * 获取总体统计
     *
     * @return array 总体统计
     */
    public function getOverallStats(): array
    {
        $totalRequests = 0;
        $totalTokens = 0;
        $modelCount = count($this->usage);

        foreach ($this->usage as $data) {
            $totalRequests += $data['total_requests'];
            $totalTokens += $data['total_input_tokens'] + $data['total_output_tokens'];
        }

        return [
            'total_models' => $modelCount,
            'total_requests' => $totalRequests,
            'total_tokens' => $totalTokens,
        ];
    }

    /**
     * 获取趋势分析
     *
     * @param string $model 模型名称
     * @param int $days 天数
     * @return array 趋势数据
     */
    public function getTrend(string $model, int $days = 7): array
    {
        $dailyStats = $this->getDailyStats($model, $days);

        if (empty($dailyStats)) {
            return [
                'trend' => 'stable',
                'change_percent' => 0,
            ];
        }

        $dates = array_keys($dailyStats);
        $firstDay = end($dates);
        $lastDay = reset($dates);

        $firstDayRequests = $dailyStats[$firstDay]['requests'];
        $lastDayRequests = $dailyStats[$lastDay]['requests'];

        $change = $lastDayRequests - $firstDayRequests;
        $changePercent = $firstDayRequests > 0
            ? ($change / $firstDayRequests) * 100
            : 0;

        $trend = match (true) {
            $changePercent > 10 => 'increasing',
            $changePercent < -10 => 'decreasing',
            default => 'stable',
        };

        return [
            'trend' => $trend,
            'change_percent' => $changePercent,
            'first_day' => $firstDay,
            'last_day' => $lastDay,
            'first_day_requests' => $firstDayRequests,
            'last_day_requests' => $lastDayRequests,
        ];
    }

    /**
     * 导出统计数据
     *
     * @param string $format 格式（json/csv）
     * @return string 导出数据
     */
    public function export(string $format = 'json'): string
    {
        $stats = $this->getStats();

        return match ($format) {
            'json' => json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'csv' => $this->exportCsv($stats),
            default => json_encode($stats),
        };
    }

    /**
     * 导出为 CSV
     */
    private function exportCsv(array $stats): string
    {
        $csv = "Model,Total Requests,Success Rate,Total Tokens,Avg Latency\n";

        foreach ($stats as $model => $data) {
            $csv .= sprintf(
                "%s,%d,%.2f%%,%d,%.3f\n",
                $model,
                $data['total_requests'],
                $data['success_rate'],
                $data['total_tokens'],
                $data['avg_latency']
            );
        }

        return $csv;
    }

    /**
     * 记录详细日志
     */
    private function logDetail(
        string $model,
        int $inputTokens,
        int $outputTokens,
        float $latency,
        bool $success,
        array $metadata
    ): void {
        // TODO: 实际项目中应写入数据库或日志文件
        error_log(sprintf(
            "[UsageTracker] Model: %s, Input: %d, Output: %d, Latency: %.3fs, Success: %s",
            $model,
            $inputTokens,
            $outputTokens,
            $latency,
            $success ? 'Yes' : 'No'
        ));
    }

    /**
     * 重置统计
     *
     * @param string|null $model 模型名称（null = 重置所有）
     */
    public function reset(?string $model = null): void
    {
        if ($model !== null) {
            unset($this->usage[$model]);
        } else {
            $this->usage = [];
        }
    }
}
