<?php
declare(strict_types=1);

namespace Services\AI\Pipeline\Pipes;

use Services\AI\Pipeline\PipelineContext;

/**
 * 格式化输出 Pipe
 * 格式化最终输出给用户的响应
 */
class FormatOutput
{
    private string $outputFormat;

    public function __construct(string $outputFormat = 'json')
    {
        $this->outputFormat = $outputFormat;
    }

    /**
     * 格式化输出
     */
    public function __invoke(PipelineContext $context): void
    {
        try {
            $modelResponse = $context->getModelResponse();
            $messages = $context->getMessages();

            // 提取最终的 assistant 回复
            $finalMessage = $this->extractFinalMessage($messages, $modelResponse);

            // 构建输出结构
            $output = $this->buildOutput($context, $finalMessage);

            // 根据格式类型格式化
            $formatted = $this->format($output);

            $context->setMetadata('formatted_output', $formatted);
            $context->setMetadata('final_message', $finalMessage);

            $context->logExecution('format_output', 'Output formatted successfully');

        } catch (\Throwable $e) {
            $context->logExecution('format_output', 'Failed to format output: ' . $e->getMessage());
        }
    }

    private function extractFinalMessage(array $messages, ?array $modelResponse): string
    {
        // 从消息历史中提取最后的 assistant 消息
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if ($messages[$i]['role'] === 'assistant') {
                return $messages[$i]['content'] ?? '';
            }
        }

        // 如果消息历史中没有，从模型响应中提取
        if ($modelResponse !== null) {
            return $modelResponse['choices'][0]['message']['content'] ?? '';
        }

        return '';
    }

    private function buildOutput(PipelineContext $context, string $message): array
    {
        $output = [
            'message' => $message,
            'success' => $context->shouldContinue(),
        ];

        // 如果有错误，添加错误信息
        if (!$context->shouldContinue()) {
            $output['error'] = $context->getErrorMessage();
        }

        // 添加元数据（可选）
        $includeMetadata = $context->getMetadataValue('include_metadata', false);
        if ($includeMetadata) {
            $output['metadata'] = [
                'execution_log' => $context->getExecutionLog(),
                'tool_iteration' => $context->getMetadataValue('tool_iteration', 0),
            ];
        }

        // 添加使用统计（如果有）
        $modelResponse = $context->getModelResponse();
        if ($modelResponse && isset($modelResponse['usage'])) {
            $output['usage'] = $modelResponse['usage'];
        }

        return $output;
    }

    private function format(array $output): string
    {
        return match ($this->outputFormat) {
            'json' => json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'text' => $output['message'],
            'html' => $this->formatAsHtml($output),
            default => json_encode($output, JSON_UNESCAPED_UNICODE),
        };
    }

    private function formatAsHtml(array $output): string
    {
        $message = htmlspecialchars($output['message']);
        $html = "<div class=\"ai-response\">";

        if (!$output['success']) {
            $error = htmlspecialchars($output['error'] ?? '未知错误');
            $html .= "<div class=\"error\">{$error}</div>";
        } else {
            $html .= "<div class=\"message\">{$message}</div>";
        }

        $html .= "</div>";

        return $html;
    }
}
