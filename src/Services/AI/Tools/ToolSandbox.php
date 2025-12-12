<?php
declare(strict_types=1);

namespace Services\AI\Tools;

/**
 * 工具沙箱
 * 在隔离环境中安全执行工具，防止危险操作
 */
class ToolSandbox
{
    private array $allowedFunctions = [];
    private int $maxExecutionTime;
    private int $maxMemoryUsage;
    private bool $enableLogging;

    public function __construct(
        array $allowedFunctions = [],
        int $maxExecutionTime = 5,
        int $maxMemoryUsage = 50 * 1024 * 1024,
        bool $enableLogging = true
    ) {
        $this->allowedFunctions = $allowedFunctions;
        $this->maxExecutionTime = $maxExecutionTime;
        $this->maxMemoryUsage = $maxMemoryUsage;
        $this->enableLogging = $enableLogging;
    }

    /**
     * 在沙箱中执行工具
     *
     * @param BaseTool $tool 工具实例
     * @param array $arguments 参数
     * @return array 执行结果
     */
    public function execute(BaseTool $tool, array $arguments): array
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        if ($this->enableLogging) {
            $this->log('sandbox_start', [
                'tool' => $tool->getName(),
                'arguments' => $arguments,
            ]);
        }

        try {
            // 设置执行时间限制
            set_time_limit($this->maxExecutionTime);

            // 检查内存使用
            $this->checkMemoryUsage();

            // 执行工具
            $result = $tool->execute($arguments);

            // 计算资源使用
            $executionTime = microtime(true) - $startTime;
            $memoryUsed = memory_get_usage() - $startMemory;

            if ($this->enableLogging) {
                $this->log('sandbox_success', [
                    'tool' => $tool->getName(),
                    'execution_time' => $executionTime,
                    'memory_used' => $memoryUsed,
                ]);
            }

            // 添加资源使用信息到结果
            $result['_meta'] = [
                'execution_time' => $executionTime,
                'memory_used' => $memoryUsed,
            ];

            return $result;

        } catch (\Throwable $e) {
            $executionTime = microtime(true) - $startTime;

            if ($this->enableLogging) {
                $this->log('sandbox_error', [
                    'tool' => $tool->getName(),
                    'error' => $e->getMessage(),
                    'execution_time' => $executionTime,
                ]);
            }

            return [
                'success' => false,
                'error' => '沙箱执行失败: ' . $e->getMessage(),
                '_meta' => [
                    'execution_time' => $executionTime,
                ],
            ];
        }
    }

    /**
     * 检查内存使用
     *
     * @throws \RuntimeException 内存超限时抛出
     */
    private function checkMemoryUsage(): void
    {
        $currentMemory = memory_get_usage();

        if ($currentMemory > $this->maxMemoryUsage) {
            throw new \RuntimeException(
                "内存使用超限: {$currentMemory} / {$this->maxMemoryUsage}"
            );
        }
    }

    /**
     * 记录日志
     */
    private function log(string $event, array $data): void
    {
        // TODO: 在实际项目中，应该集成日志系统
        error_log(json_encode([
            'event' => $event,
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => $data,
        ]));
    }

    /**
     * 验证工具是否允许执行
     *
     * @param BaseTool $tool 工具实例
     * @return bool 是否允许
     */
    public function isAllowed(BaseTool $tool): bool
    {
        // 如果没有配置允许列表，默认全部允许
        if (empty($this->allowedFunctions)) {
            return true;
        }

        return in_array($tool->getName(), $this->allowedFunctions, true);
    }

    /**
     * 添加允许的工具
     */
    public function allow(string $toolName): void
    {
        if (!in_array($toolName, $this->allowedFunctions, true)) {
            $this->allowedFunctions[] = $toolName;
        }
    }

    /**
     * 批量添加允许的工具
     */
    public function allowMany(array $toolNames): void
    {
        foreach ($toolNames as $name) {
            $this->allow($name);
        }
    }
}
