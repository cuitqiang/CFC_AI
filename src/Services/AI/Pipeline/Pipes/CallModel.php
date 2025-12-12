<?php
declare(strict_types=1);

namespace Services\AI\Pipeline\Pipes;

use Services\AI\Pipeline\PipelineContext;

/**
 * 调用模型 Pipe
 * 调用 AI 模型（如 Deepseek、OpenAI）获取响应
 */
class CallModel
{
    private ?object $modelRouter;

    public function __construct(?object $modelRouter = null)
    {
        $this->modelRouter = $modelRouter;
    }

    /**
     * 调用 AI 模型
     */
    public function __invoke(PipelineContext $context): void
    {
        if ($this->modelRouter === null) {
            $context->stop('模型路由器未配置');
            $context->logExecution('call_model', 'ModelRouter not available');
            return;
        }

        try {
            $messages = $context->getMessages();
            $tools = $context->getTools();
            $modelName = $context->getMetadataValue('model', 'deepseek-chat');

            $requestPayload = [
                'model' => $modelName,
                'messages' => $messages,
            ];

            // 如果有工具，添加到请求中
            if (!empty($tools)) {
                $requestPayload['tools'] = $tools;
                $requestPayload['tool_choice'] = 'auto';
            }

            $context->logExecution('call_model', 'Calling model: ' . $modelName);

            // 调用模型
            $response = $this->callModel($requestPayload);

            $context->setModelResponse($response);

            // 检查是否有工具调用
            $hasToolCalls = isset($response['choices'][0]['message']['tool_calls']);
            $context->setMetadata('has_tool_calls', $hasToolCalls);

            if ($hasToolCalls) {
                $toolCalls = $response['choices'][0]['message']['tool_calls'];
                $context->setMetadata('tool_calls', $toolCalls);
                $context->logExecution('call_model', 'Model requested ' . count($toolCalls) . ' tool calls');
            } else {
                $content = $response['choices'][0]['message']['content'] ?? '';
                $context->addMessage('assistant', $content);
                $context->logExecution('call_model', 'Model response received');
            }

        } catch (\Throwable $e) {
            $context->stop('调用模型失败：' . $e->getMessage());
            $context->logExecution('call_model', 'Model call failed: ' . $e->getMessage());
        }
    }

    private function callModel(array $payload): array
    {
        if ($this->modelRouter === null) {
            throw new \RuntimeException('ModelRouter is not configured');
        }

        $model = $payload['model'];
        $messages = $payload['messages'];

        // 提取其他选项
        $options = [];
        foreach ($payload as $key => $value) {
            if ($key !== 'model' && $key !== 'messages') {
                $options[$key] = $value;
            }
        }

        return $this->modelRouter->chat($model, $messages, $options);
    }
}
