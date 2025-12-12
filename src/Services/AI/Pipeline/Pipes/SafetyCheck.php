<?php
declare(strict_types=1);

namespace Services\AI\Pipeline\Pipes;

use Services\AI\Pipeline\PipelineContext;

/**
 * 安全检查 Pipe
 * 检测并过滤有害、敏感或不当内容
 */
class SafetyCheck
{
    private array $bannedKeywords = [
        'drop table',
        'delete from',
        'truncate',
        '<script>',
        'eval(',
        'exec(',
    ];

    private int $maxInputLength;

    public function __construct(int $maxInputLength = 10000)
    {
        $this->maxInputLength = $maxInputLength;
    }

    /**
     * 执行安全检查
     */
    public function __invoke(PipelineContext $context): void
    {
        $input = $context->getUserInput();

        // 检查输入长度
        if (mb_strlen($input) > $this->maxInputLength) {
            $context->stop('输入内容过长，最大允许 ' . $this->maxInputLength . ' 字符');
            $context->logExecution('safety_check', 'Input too long');
            return;
        }

        // 检查危险关键词
        $lowerInput = strtolower($input);
        foreach ($this->bannedKeywords as $keyword) {
            if (str_contains($lowerInput, strtolower($keyword))) {
                $context->stop('输入包含不允许的内容');
                $context->logExecution('safety_check', 'Banned keyword detected: ' . $keyword);
                return;
            }
        }

        // 检查SQL注入模式
        if ($this->detectSqlInjection($input)) {
            $context->stop('检测到潜在的SQL注入攻击');
            $context->logExecution('safety_check', 'SQL injection pattern detected');
            return;
        }

        // 检查XSS模式
        if ($this->detectXss($input)) {
            $context->stop('检测到潜在的XSS攻击');
            $context->logExecution('safety_check', 'XSS pattern detected');
            return;
        }

        $context->logExecution('safety_check', 'Safety check passed');
    }

    private function detectSqlInjection(string $input): bool
    {
        $patterns = [
            '/(\bunion\b.*\bselect\b)/i',
            '/(\bor\b\s+["\']?\d+["\']?\s*=\s*["\']?\d+)/i',
            '/(;\s*drop\s+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    private function detectXss(string $input): bool
    {
        $patterns = [
            '/<script[^>]*>.*?<\/script>/is',
            '/on\w+\s*=\s*["\'].*?["\']/i',
            '/javascript:/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }
}
