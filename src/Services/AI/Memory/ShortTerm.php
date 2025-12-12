<?php
declare(strict_types=1);

namespace Services\AI\Memory;

/**
 * 短期记忆
 * 存储最近的对话历史
 */
class ShortTerm
{
    private array $storage = [];
    private int $defaultTtl;

    public function __construct(int $defaultTtl = 3600)
    {
        $this->defaultTtl = $defaultTtl;
    }

    /**
     * 添加消息到短期记忆
     *
     * @param string $userId 用户ID
     * @param array $messages 消息列表
     * @param string|null $sessionId 会话ID
     */
    public function add(string $userId, array $messages, ?string $sessionId = null): void
    {
        $key = $this->getKey($userId, $sessionId);

        if (!isset($this->storage[$key])) {
            $this->storage[$key] = [
                'messages' => [],
                'created_at' => time(),
                'updated_at' => time(),
            ];
        }

        foreach ($messages as $message) {
            $this->storage[$key]['messages'][] = array_merge($message, [
                'timestamp' => time(),
            ]);
        }

        $this->storage[$key]['updated_at'] = time();
    }

    /**
     * 获取短期记忆
     *
     * @param string $userId 用户ID
     * @param string|null $sessionId 会话ID
     * @param int|null $limit 返回数量限制
     * @return array 消息列表
     */
    public function get(string $userId, ?string $sessionId = null, ?int $limit = null): array
    {
        $key = $this->getKey($userId, $sessionId);

        if (!isset($this->storage[$key])) {
            return [];
        }

        $messages = $this->storage[$key]['messages'];

        // 检查是否过期
        $createdAt = $this->storage[$key]['created_at'];
        if (time() - $createdAt > $this->defaultTtl) {
            $this->clear($userId, $sessionId);
            return [];
        }

        if ($limit !== null) {
            $messages = array_slice($messages, -$limit);
        }

        return $messages;
    }

    /**
     * 获取消息数量
     *
     * @param string $userId 用户ID
     * @param string|null $sessionId 会话ID
     * @return int 消息数量
     */
    public function count(string $userId, ?string $sessionId = null): int
    {
        $messages = $this->get($userId, $sessionId);
        return count($messages);
    }

    /**
     * 修剪短期记忆（只保留最近的N条）
     *
     * @param string $userId 用户ID
     * @param string|null $sessionId 会话ID
     * @param int $keepLast 保留数量
     */
    public function trim(string $userId, ?string $sessionId, int $keepLast): void
    {
        $key = $this->getKey($userId, $sessionId);

        if (!isset($this->storage[$key])) {
            return;
        }

        $messages = $this->storage[$key]['messages'];
        $this->storage[$key]['messages'] = array_slice($messages, -$keepLast);
        $this->storage[$key]['updated_at'] = time();
    }

    /**
     * 清空短期记忆
     *
     * @param string $userId 用户ID
     * @param string|null $sessionId 会话ID
     */
    public function clear(string $userId, ?string $sessionId = null): void
    {
        $key = $this->getKey($userId, $sessionId);
        unset($this->storage[$key]);
    }

    /**
     * 获取所有会话
     *
     * @param string $userId 用户ID
     * @return array 会话列表
     */
    public function getSessions(string $userId): array
    {
        $sessions = [];

        foreach ($this->storage as $key => $data) {
            if (str_starts_with($key, $userId . ':')) {
                $sessionId = substr($key, strlen($userId) + 1);
                $sessions[$sessionId] = [
                    'message_count' => count($data['messages']),
                    'created_at' => $data['created_at'],
                    'updated_at' => $data['updated_at'],
                ];
            }
        }

        return $sessions;
    }

    /**
     * 清理过期的记忆
     */
    public function cleanup(): void
    {
        $now = time();

        foreach ($this->storage as $key => $data) {
            if ($now - $data['created_at'] > $this->defaultTtl) {
                unset($this->storage[$key]);
            }
        }
    }

    /**
     * 生成存储键
     */
    private function getKey(string $userId, ?string $sessionId): string
    {
        return $sessionId !== null
            ? "{$userId}:{$sessionId}"
            : $userId;
    }

    /**
     * 获取最后一条消息
     *
     * @param string $userId 用户ID
     * @param string|null $sessionId 会话ID
     * @return array|null 最后一条消息
     */
    public function getLast(string $userId, ?string $sessionId = null): ?array
    {
        $messages = $this->get($userId, $sessionId);

        if (empty($messages)) {
            return null;
        }

        return end($messages);
    }
}
