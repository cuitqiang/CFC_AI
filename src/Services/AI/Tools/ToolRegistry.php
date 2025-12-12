<?php
declare(strict_types=1);

namespace Services\AI\Tools;

/**
 * 工具注册表
 * 管理所有可用工具的注册和调用
 */
class ToolRegistry
{
    private array $tools = [];

    /**
     * 注册一个工具
     *
     * @param BaseTool $tool 工具实例
     */
    public function register(BaseTool $tool): void
    {
        $this->tools[$tool->getName()] = $tool;
    }

    /**
     * 批量注册工具
     *
     * @param array $tools 工具数组
     */
    public function registerMany(array $tools): void
    {
        foreach ($tools as $tool) {
            if ($tool instanceof BaseTool) {
                $this->register($tool);
            }
        }
    }

    /**
     * 获取工具
     *
     * @param string $name 工具名称
     * @return BaseTool|null 工具实例
     */
    public function get(string $name): ?BaseTool
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * 检查工具是否存在
     *
     * @param string $name 工具名称
     */
    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * 获取所有工具
     *
     * @return array 工具数组
     */
    public function all(): array
    {
        return $this->tools;
    }

    /**
     * 获取所有工具的定义（用于 Function Calling）
     *
     * @return array 工具定义数组
     */
    public function getAllDefinitions(): array
    {
        $definitions = [];

        foreach ($this->tools as $tool) {
            $definitions[] = $tool->getDefinition();
        }

        return $definitions;
    }

    /**
     * 根据名称列表获取工具定义
     *
     * @param array $names 工具名称列表
     * @return array 工具定义数组
     */
    public function getDefinitionsByNames(array $names): array
    {
        $definitions = [];

        foreach ($names as $name) {
            if ($this->has($name)) {
                $definitions[] = $this->tools[$name]->getDefinition();
            }
        }

        return $definitions;
    }

    /**
     * 执行工具
     *
     * @param string $name 工具名称
     * @param array $arguments 参数
     * @return array 执行结果
     */
    public function execute(string $name, array $arguments): array
    {
        $tool = $this->get($name);

        if ($tool === null) {
            return [
                'success' => false,
                'error' => "工具不存在: {$name}",
            ];
        }

        try {
            return $tool->execute($arguments);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => "工具执行失败: " . $e->getMessage(),
            ];
        }
    }

    /**
     * 移除工具
     *
     * @param string $name 工具名称
     */
    public function unregister(string $name): void
    {
        unset($this->tools[$name]);
    }

    /**
     * 清空所有工具
     */
    public function clear(): void
    {
        $this->tools = [];
    }

    /**
     * 获取工具数量
     */
    public function count(): int
    {
        return count($this->tools);
    }
}
