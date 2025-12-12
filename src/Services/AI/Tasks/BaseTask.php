<?php
declare(strict_types=1);

namespace Services\AI\Tasks;

use Services\AI\Core\AIManager;

/**
 * 任务基类
 * 所有 AI 任务必须继承此类
 */
abstract class BaseTask
{
    protected AIManager $aiManager;
    protected string $name;
    protected string $description;
    protected array $config;

    public function __construct(AIManager $aiManager, array $config = [])
    {
        $this->aiManager = $aiManager;
        $this->config = $config;
        $this->initialize();
    }

    /**
     * 初始化任务（子类实现）
     */
    abstract protected function initialize(): void;

    /**
     * 执行任务（子类实现）
     *
     * @param array $input 输入参数
     * @return array 执行结果
     */
    abstract public function execute(array $input): array;

    /**
     * 获取任务名称
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 获取任务描述
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * 验证输入参数
     *
     * @param array $input 输入参数
     * @param array $required 必需字段
     * @throws \InvalidArgumentException 参数无效时抛出
     */
    protected function validateInput(array $input, array $required): void
    {
        foreach ($required as $field) {
            if (!isset($input[$field])) {
                throw new \InvalidArgumentException("缺少必需参数: {$field}");
            }
        }
    }

    /**
     * 构建系统提示
     *
     * @param string $prompt 提示内容
     * @return array 消息数组
     */
    protected function buildSystemPrompt(string $prompt): array
    {
        return [
            'role' => 'system',
            'content' => $prompt,
        ];
    }

    /**
     * 构建用户消息
     *
     * @param string $content 消息内容
     * @return array 消息数组
     */
    protected function buildUserMessage(string $content): array
    {
        return [
            'role' => 'user',
            'content' => $content,
        ];
    }

    /**
     * 调用 AI 处理
     *
     * @param string $userInput 用户输入
     * @param array $metadata 元数据
     * @return array AI 响应
     */
    protected function callAI(string $userInput, array $metadata = []): array
    {
        return $this->aiManager->process($userInput, $metadata);
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
            'task' => $this->name,
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
            'task' => $this->name,
        ];
    }

    /**
     * 记录任务日志
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        // TODO: 集成日志系统
        error_log("[{$level}] {$this->name}: {$message} " . json_encode($context));
    }
}
