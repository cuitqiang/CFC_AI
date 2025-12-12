<?php
declare(strict_types=1);

namespace Services\AI\Pipeline\Pipes;

use Services\AI\Pipeline\PipelineContext;

/**
 * 频率限制检查 Pipe
 * 防止用户请求过于频繁，保护系统资源
 */
class RateLimit
{
    private int $maxRequestsPerMinute;
    private int $maxRequestsPerHour;
    private array $requestLog = [];

    public function __construct(
        int $maxRequestsPerMinute = 10,
        int $maxRequestsPerHour = 100
    ) {
        $this->maxRequestsPerMinute = $maxRequestsPerMinute;
        $this->maxRequestsPerHour = $maxRequestsPerHour;
    }

    /**
     * 执行频率限制检查
     */
    public function __invoke(PipelineContext $context): void
    {
        $userId = $context->getMetadataValue('user_id', 'anonymous');
        $now = time();

        $this->cleanOldRequests($now);

        if (!isset($this->requestLog[$userId])) {
            $this->requestLog[$userId] = [];
        }

        $recentRequests = $this->getRecentRequests($userId, $now);

        // 检查每分钟限制
        $requestsInLastMinute = count(array_filter(
            $recentRequests,
            fn($timestamp) => $timestamp > ($now - 60)
        ));

        if ($requestsInLastMinute >= $this->maxRequestsPerMinute) {
            $context->stop('频率限制：每分钟最多 ' . $this->maxRequestsPerMinute . ' 次请求');
            $context->logExecution('rate_limit', 'Exceeded per-minute limit');
            return;
        }

        // 检查每小时限制
        $requestsInLastHour = count(array_filter(
            $recentRequests,
            fn($timestamp) => $timestamp > ($now - 3600)
        ));

        if ($requestsInLastHour >= $this->maxRequestsPerHour) {
            $context->stop('频率限制：每小时最多 ' . $this->maxRequestsPerHour . ' 次请求');
            $context->logExecution('rate_limit', 'Exceeded per-hour limit');
            return;
        }

        // 记录本次请求
        $this->requestLog[$userId][] = $now;
        $context->logExecution('rate_limit', 'Rate limit check passed');
    }

    private function getRecentRequests(string $userId, int $now): array
    {
        return array_filter(
            $this->requestLog[$userId] ?? [],
            fn($timestamp) => $timestamp > ($now - 3600)
        );
    }

    private function cleanOldRequests(int $now): void
    {
        foreach ($this->requestLog as $userId => $requests) {
            $this->requestLog[$userId] = array_filter(
                $requests,
                fn($timestamp) => $timestamp > ($now - 3600)
            );
        }
    }
}
