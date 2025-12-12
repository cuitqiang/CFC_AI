<?php
declare(strict_types=1);

namespace Services\AI\Core;

use Services\AI\Pipeline\Pipeline;
use Services\AI\Pipeline\PipelineContext;
use Services\AI\Pipeline\Pipes\RateLimit;
use Services\AI\Pipeline\Pipes\SafetyCheck;
use Services\AI\Pipeline\Pipes\LoadMemory;
use Services\AI\Pipeline\Pipes\PlanTools;
use Services\AI\Pipeline\Pipes\CallModel;
use Services\AI\Pipeline\Pipes\ExecuteTool;
use Services\AI\Pipeline\Pipes\SaveMemory;
use Services\AI\Pipeline\Pipes\FormatOutput;

/**
 * AI 管理器
 * 整个 AI Agent 系统的入口和协调器
 */
class AIManager
{
    private ?ModelRouter $modelRouter;
    private ?object $toolRegistry;
    private ?object $contextManager;
    private Pipeline $pipeline;
    private array $config;

    public function __construct(
        ?ModelRouter $modelRouter = null,
        ?object $toolRegistry = null,
        ?object $contextManager = null,
        array $config = []
    ) {
        $this->modelRouter = $modelRouter;
        $this->toolRegistry = $toolRegistry;
        $this->contextManager = $contextManager;
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->pipeline = new Pipeline();
    }

    /**
     * 处理用户请求
     *
     * @param string $userInput 用户输入
     * @param array $metadata 元数据（user_id, session_id等）
     * @return array 处理结果
     */
    public function process(string $userInput, array $metadata = []): array
    {
        // 创建上下文
        $context = new PipelineContext($userInput, $metadata);

        // 构建管道
        $this->buildPipeline();

        // 执行管道
        $result = $this->pipeline->process($context);

        // 返回格式化的输出
        return $this->extractResult($result);
    }

    /**
     * 流式处理用户请求
     *
     * @param string $userInput 用户输入
     * @param callable $callback 流式回调
     * @param array $metadata 元数据
     */
    public function processStream(string $userInput, callable $callback, array $metadata = []): void
    {
        // TODO: 实现流式处理逻辑
        // 需要修改 CallModel Pipe 支持流式输出
        $metadata['stream'] = true;
        $metadata['stream_callback'] = $callback;

        $this->process($userInput, $metadata);
    }

    /**
     * 构建管道
     */
    private function buildPipeline(): void
    {
        $this->pipeline->reset();

        // 按顺序添加所有 Pipe
        $pipes = [
            new RateLimit(
                $this->config['rate_limit']['per_minute'] ?? 10,
                $this->config['rate_limit']['per_hour'] ?? 100
            ),
            new SafetyCheck(
                $this->config['safety']['max_input_length'] ?? 10000
            ),
            new LoadMemory($this->contextManager),
            new PlanTools($this->toolRegistry),
            new CallModel($this->modelRouter),
            new ExecuteTool(
                $this->toolRegistry,
                null,
                $this->config['tools']['max_iterations'] ?? 5
            ),
            new SaveMemory($this->contextManager),
            new FormatOutput($this->config['output_format'] ?? 'json'),
        ];

        $this->pipeline->through($pipes);
    }

    /**
     * 从上下文中提取结果
     */
    private function extractResult(PipelineContext $context): array
    {
        $formattedOutput = $context->getMetadataValue('formatted_output');

        if ($formattedOutput !== null) {
            return is_string($formattedOutput)
                ? json_decode($formattedOutput, true)
                : $formattedOutput;
        }

        // 如果没有格式化输出，返回基本结果
        return [
            'success' => $context->shouldContinue(),
            'message' => $context->getMetadataValue('final_message', ''),
            'error' => $context->getErrorMessage(),
        ];
    }

    /**
     * 获取默认配置
     */
    private function getDefaultConfig(): array
    {
        return [
            'rate_limit' => [
                'per_minute' => 10,
                'per_hour' => 100,
            ],
            'safety' => [
                'max_input_length' => 10000,
            ],
            'tools' => [
                'max_iterations' => 5,
            ],
            'output_format' => 'json',
        ];
    }

    /**
     * 设置配置
     */
    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 获取配置
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * 设置模型路由器
     */
    public function setModelRouter(ModelRouter $router): void
    {
        $this->modelRouter = $router;
    }

    /**
     * 设置工具注册表
     */
    public function setToolRegistry(object $registry): void
    {
        $this->toolRegistry = $registry;
    }

    /**
     * 设置上下文管理器
     */
    public function setContextManager(object $manager): void
    {
        $this->contextManager = $manager;
    }
}
