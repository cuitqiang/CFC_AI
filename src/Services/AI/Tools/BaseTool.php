<?php
declare(strict_types=1);

namespace Services\AI\Tools;

/**
 * 工具基类
 * 所有工具必须继承此类
 */
abstract class BaseTool
{
    protected string $name;
    protected string $description;
    protected array $parameters;

    /**
     * 获取工具名称
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 获取工具描述
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * 获取工具参数定义（JSON Schema 格式）
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * 获取完整的工具定义（用于 Function Calling）
     */
    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => $this->parameters,
            ]
        ];
    }

    /**
     * 执行工具（子类必须实现）
     *
     * @param array $arguments 工具参数
     * @return array 执行结果
     */
    abstract public function execute(array $arguments): array;

    /**
     * 验证参数
     *
     * @param array $arguments 输入参数
     * @throws \InvalidArgumentException 参数无效时抛出
     */
    protected function validateArguments(array $arguments): void
    {
        $required = $this->parameters['required'] ?? [];

        foreach ($required as $field) {
            if (!isset($arguments[$field])) {
                throw new \InvalidArgumentException(
                    "缺少必需参数: {$field}"
                );
            }
        }
    }

    /**
     * 构建成功响应
     */
    protected function success(mixed $data, string $message = ''): array
    {
        return [
            'success' => true,
            'data' => $data,
            'message' => $message,
        ];
    }

    /**
     * 构建失败响应
     */
    protected function error(string $message, mixed $data = null): array
    {
        return [
            'success' => false,
            'error' => $message,
            'data' => $data,
        ];
    }
}
