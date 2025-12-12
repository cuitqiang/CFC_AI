<?php
declare(strict_types=1);

namespace Services\AI\Memory;

/**
 * 摘要记忆
 * 长期压缩的对话摘要
 */
class Summary
{
    private array $storage = [];

    /**
     * 生成对话摘要
     *
     * @param array $messages 消息列表
     * @return string 摘要文本
     */
    public function generate(array $messages): string
    {
        // TODO: 实际项目中应使用 AI 模型生成摘要
        // 现在使用简单的提取方式

        $userMessages = [];
        $assistantMessages = [];

        foreach ($messages as $message) {
            if ($message['role'] === 'user') {
                $userMessages[] = $message['content'];
            } elseif ($message['role'] === 'assistant') {
                $assistantMessages[] = $message['content'];
            }
        }

        $summary = "对话摘要：\n";
        $summary .= "用户主要询问了：" . implode('、', array_slice($userMessages, 0, 3)) . "\n";
        $summary .= "助手提供了相关的回答和帮助。";

        return $summary;
    }

    /**
     * 使用 AI 模型生成摘要（高级版本）
     *
     * @param array $messages 消息列表
     * @param object|null $modelRouter 模型路由器
     * @return string 摘要文本
     */
    public function generateWithAI(array $messages, ?object $modelRouter = null): string
    {
        if ($modelRouter === null) {
            return $this->generate($messages);
        }

        // 构建摘要提示
        $conversation = $this->formatConversation($messages);

        $prompt = [
            [
                'role' => 'system',
                'content' => '你是一个对话摘要助手。请简洁地总结以下对话的关键内容，保留重要信息、用户偏好和决策。'
            ],
            [
                'role' => 'user',
                'content' => "请总结以下对话：\n\n{$conversation}"
            ]
        ];

        try {
            $response = $modelRouter->chat('deepseek-chat', $prompt, [
                'max_tokens' => 200,
                'temperature' => 0.3,
            ]);

            return $response['choices'][0]['message']['content'] ?? $this->generate($messages);

        } catch (\Throwable $e) {
            return $this->generate($messages);
        }
    }

    /**
     * 格式化对话为文本
     */
    private function formatConversation(array $messages): string
    {
        $lines = [];

        foreach ($messages as $message) {
            $role = $message['role'] === 'user' ? '用户' : '助手';
            $content = $message['content'];
            $lines[] = "{$role}: {$content}";
        }

        return implode("\n", $lines);
    }

    /**
     * 合并两个摘要
     *
     * @param string $summary1 摘要1
     * @param string $summary2 摘要2
     * @return string 合并后的摘要
     */
    public function merge(string $summary1, string $summary2): string
    {
        if (empty($summary1)) {
            return $summary2;
        }

        if (empty($summary2)) {
            return $summary1;
        }

        return $summary1 . "\n\n" . $summary2;
    }

    /**
     * 保存摘要
     *
     * @param string $userId 用户ID
     * @param string $summary 摘要文本
     */
    public function save(string $userId, string $summary): void
    {
        $this->storage[$userId] = [
            'summary' => $summary,
            'updated_at' => time(),
        ];
    }

    /**
     * 获取摘要
     *
     * @param string $userId 用户ID
     * @return string 摘要文本
     */
    public function get(string $userId): string
    {
        return $this->storage[$userId]['summary'] ?? '';
    }

    /**
     * 清空摘要
     *
     * @param string $userId 用户ID
     */
    public function clear(string $userId): void
    {
        unset($this->storage[$userId]);
    }

    /**
     * 检查是否有摘要
     *
     * @param string $userId 用户ID
     * @return bool 是否有摘要
     */
    public function has(string $userId): bool
    {
        return isset($this->storage[$userId]);
    }

    /**
     * 追加到摘要
     *
     * @param string $userId 用户ID
     * @param string $newContent 新内容
     */
    public function append(string $userId, string $newContent): void
    {
        $existing = $this->get($userId);
        $merged = $this->merge($existing, $newContent);
        $this->save($userId, $merged);
    }

    /**
     * 获取摘要的元数据
     *
     * @param string $userId 用户ID
     * @return array 元数据
     */
    public function getMeta(string $userId): array
    {
        if (!isset($this->storage[$userId])) {
            return [];
        }

        return [
            'length' => mb_strlen($this->storage[$userId]['summary']),
            'updated_at' => $this->storage[$userId]['updated_at'],
        ];
    }
}
