<?php
declare(strict_types=1);

namespace Services\AI\Core\Utils;

/**
 * Token 计数器
 * 估算和计算 Token 数量
 */
class TokenCounter
{
    private const CHARS_PER_TOKEN_ZH = 2.0;
    private const CHARS_PER_TOKEN_EN = 4.0;

    /**
     * 估算文本的 Token 数量
     *
     * @param string $text 文本内容
     * @return int Token 数量
     */
    public function count(string $text): int
    {
        $length = mb_strlen($text);

        // 检测语言（简单方式：根据中文字符比例）
        $chineseRatio = $this->getChineseRatio($text);

        if ($chineseRatio > 0.5) {
            // 主要是中文
            return (int) ceil($length / self::CHARS_PER_TOKEN_ZH);
        } else {
            // 主要是英文
            return (int) ceil($length / self::CHARS_PER_TOKEN_EN);
        }
    }

    /**
     * 估算消息列表的 Token 数量
     *
     * @param array $messages 消息列表
     * @return int Token 数量
     */
    public function countMessages(array $messages): int
    {
        $totalTokens = 0;

        foreach ($messages as $message) {
            // 每条消息的元数据约 4 tokens
            $totalTokens += 4;

            // 计算 role
            if (isset($message['role'])) {
                $totalTokens += 1;
            }

            // 计算 content
            if (isset($message['content'])) {
                $totalTokens += $this->count($message['content']);
            }

            // 计算 name（如果有）
            if (isset($message['name'])) {
                $totalTokens += 1;
            }

            // 计算 tool_calls（如果有）
            if (isset($message['tool_calls'])) {
                foreach ($message['tool_calls'] as $toolCall) {
                    $totalTokens += $this->countToolCall($toolCall);
                }
            }
        }

        return $totalTokens;
    }

    /**
     * 估算工具调用的 Token 数量
     *
     * @param array $toolCall 工具调用
     * @return int Token 数量
     */
    public function countToolCall(array $toolCall): int
    {
        $tokens = 0;

        // 工具调用基础开销
        $tokens += 5;

        // 函数名
        if (isset($toolCall['function']['name'])) {
            $tokens += $this->count($toolCall['function']['name']);
        }

        // 参数
        if (isset($toolCall['function']['arguments'])) {
            $tokens += $this->count($toolCall['function']['arguments']);
        }

        return $tokens;
    }

    /**
     * 估算工具定义的 Token 数量
     *
     * @param array $tools 工具定义列表
     * @return int Token 数量
     */
    public function countTools(array $tools): int
    {
        $totalTokens = 0;

        foreach ($tools as $tool) {
            $totalTokens += $this->countToolDefinition($tool);
        }

        return $totalTokens;
    }

    /**
     * 估算单个工具定义的 Token 数量
     *
     * @param array $tool 工具定义
     * @return int Token 数量
     */
    public function countToolDefinition(array $tool): int
    {
        $json = json_encode($tool, JSON_UNESCAPED_UNICODE);
        return $this->count($json);
    }

    /**
     * 获取中文字符比例
     *
     * @param string $text 文本
     * @return float 中文比例（0-1）
     */
    private function getChineseRatio(string $text): float
    {
        $totalChars = mb_strlen($text);

        if ($totalChars === 0) {
            return 0.0;
        }

        $chineseChars = preg_match_all('/[\x{4e00}-\x{9fa5}]/u', $text);

        return $chineseChars / $totalChars;
    }

    /**
     * 估算请求的总 Token 数量
     *
     * @param array $messages 消息列表
     * @param array $tools 工具列表
     * @return int Token 数量
     */
    public function countRequest(array $messages, array $tools = []): int
    {
        $tokens = $this->countMessages($messages);

        if (!empty($tools)) {
            $tokens += $this->countTools($tools);
        }

        // 请求的基础开销
        $tokens += 10;

        return $tokens;
    }

    /**
     * 根据 Token 限制截断文本
     *
     * @param string $text 文本
     * @param int $maxTokens 最大 Token 数
     * @return string 截断后的文本
     */
    public function truncate(string $text, int $maxTokens): string
    {
        $currentTokens = $this->count($text);

        if ($currentTokens <= $maxTokens) {
            return $text;
        }

        // 估算需要保留的字符数
        $chineseRatio = $this->getChineseRatio($text);
        $charsPerToken = $chineseRatio > 0.5
            ? self::CHARS_PER_TOKEN_ZH
            : self::CHARS_PER_TOKEN_EN;

        $maxChars = (int) floor($maxTokens * $charsPerToken);

        return mb_substr($text, 0, $maxChars) . '...';
    }

    /**
     * 截断消息列表以符合 Token 限制
     *
     * @param array $messages 消息列表
     * @param int $maxTokens 最大 Token 数
     * @return array 截断后的消息列表
     */
    public function truncateMessages(array $messages, int $maxTokens): array
    {
        $currentTokens = $this->countMessages($messages);

        if ($currentTokens <= $maxTokens) {
            return $messages;
        }

        // 从最旧的消息开始移除（保留系统消息和最后几条）
        $truncated = [];
        $systemMessages = [];
        $recentMessages = [];

        foreach ($messages as $index => $message) {
            if ($message['role'] === 'system') {
                $systemMessages[] = $message;
            } else {
                $recentMessages[] = $message;
            }
        }

        // 保留系统消息
        $truncated = $systemMessages;

        // 从最新的消息开始添加
        $recentMessages = array_reverse($recentMessages);
        $tokens = $this->countMessages($truncated);

        foreach ($recentMessages as $message) {
            $messageTokens = $this->countMessages([$message]);

            if ($tokens + $messageTokens > $maxTokens) {
                break;
            }

            array_unshift($truncated, $message);
            $tokens += $messageTokens;
        }

        return $truncated;
    }
}
