<?php
declare(strict_types=1);

namespace Services\AI\Cuige;

use PDO;

/**
 * 崔哥 AI 数据仓库
 * 
 * 负责所有数据库交互，遵循 Repository 模式
 */
class CuigeRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ==================== 会话管理 ====================

    /**
     * 确保会话存在
     */
    public function ensureSession(string $sessionId, string $userId): void
    {
        $stmt = $this->pdo->prepare("SELECT id FROM cuige_sessions WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        
        if (!$stmt->fetch()) {
            $stmt = $this->pdo->prepare("
                INSERT INTO cuige_sessions (session_id, user_id, status)
                VALUES (?, ?, 'active')
            ");
            $stmt->execute([$sessionId, $userId]);
        }
    }

    /**
     * 更新会话活跃时间
     */
    public function updateSessionActivity(string $sessionId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE cuige_sessions 
            SET updated_at = NOW(), message_count = message_count + 1
            WHERE session_id = ?
        ");
        $stmt->execute([$sessionId]);
    }

    /**
     * 获取所有会话
     */
    public function getSessions(string $userId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare("
            SELECT session_id, user_id, status, message_count, created_at, updated_at
            FROM cuige_sessions 
            WHERE user_id = ?
            ORDER BY updated_at DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }

    // ==================== 短期记忆 ====================

    /**
     * 保存短期记忆
     */
    public function saveShortMemory(string $sessionId, string $userId, string $role, string $content): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO cuige_short_memory (session_id, user_id, role, content)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$sessionId, $userId, $role, $content]);
    }

    /**
     * 获取短期记忆
     */
    public function getShortMemory(string $sessionId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("
            SELECT role, content 
            FROM cuige_short_memory 
            WHERE session_id = ? AND is_summarized = 0
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$sessionId, $limit]);
        $messages = $stmt->fetchAll();
        return array_reverse($messages);
    }

    /**
     * 标记消息已压缩
     */
    public function markMessagesCompressed(string $sessionId, int $count): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE cuige_short_memory 
            SET is_summarized = 1 
            WHERE session_id = ? AND is_summarized = 0
            ORDER BY created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$sessionId, $count]);
    }

    // ==================== 长期记忆 ====================

    /**
     * 保存长期记忆
     */
    public function saveLongMemory(
        string $userId,
        string $category,
        string $subject,
        string $content,
        string $source = 'user'
    ): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO cuige_long_memory (user_id, category, subject, content, importance, confidence, source)
            VALUES (?, ?, ?, ?, 8, 80, ?)
            ON DUPLICATE KEY UPDATE 
                subject = VALUES(subject),
                mention_count = mention_count + 1,
                last_mentioned_at = NOW(),
                importance = LEAST(importance + 1, 10)
        ");
        $stmt->execute([$userId, $category, $subject, $content, $source]);
    }

    /**
     * 获取长期记忆
     */
    public function getLongMemories(string $userId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare("
            SELECT category as key_type, subject as key_info, content as detail, importance, source
            FROM cuige_long_memory 
            WHERE user_id = ? AND importance > 3 AND is_active = 1
            ORDER BY importance DESC, last_mentioned_at DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }

    // ==================== 情景记忆 ====================

    /**
     * 保存情景记忆（对话摘要）
     */
    public function saveEpisodicMemory(
        string $sessionId,
        string $userId,
        string $summary,
        int $messageCount
    ): void {
        // 提取关键点
        $keyPoints = [];
        if (preg_match('/用户信息[:：]\s*(.+?)(?=对话要点|AI|$)/s', $summary, $m)) {
            $keyPoints['user_info'] = trim($m[1]);
        }
        if (preg_match('/对话要点[:：]\s*(.+?)(?=AI|$)/s', $summary, $m)) {
            $keyPoints['main_points'] = trim($m[1]);
        }
        if (preg_match('/AI承诺|AI建议[:：]\s*(.+)$/s', $summary, $m)) {
            $keyPoints['ai_commitments'] = trim($m[1]);
        }
        
        $stmt = $this->pdo->prepare("
            INSERT INTO cuige_episode_memory 
            (session_id, user_id, summary, key_points, message_count, importance_score)
            VALUES (?, ?, ?, ?, ?, 7)
        ");
        $stmt->execute([
            $sessionId,
            $userId,
            $summary,
            json_encode($keyPoints, JSON_UNESCAPED_UNICODE),
            $messageCount
        ]);
    }

    /**
     * 获取情景记忆
     */
    public function getEpisodicMemories(string $userId, int $limit = 3): array
    {
        $stmt = $this->pdo->prepare("
            SELECT summary, key_points as key_topics, emotional_tone 
            FROM cuige_episode_memory 
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }

    // ==================== 用户画像 ====================

    /**
     * 获取用户画像
     */
    public function getUserProfile(string $userId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM cuige_user_profile WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * 更新用户画像
     */
    public function updateUserProfile(string $userId, array $data): void
    {
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            $fields[] = "{$key} = ?";
            $values[] = $value;
        }
        
        $values[] = $userId;
        
        $stmt = $this->pdo->prepare("
            UPDATE cuige_user_profile 
            SET " . implode(', ', $fields) . "
            WHERE user_id = ?
        ");
        $stmt->execute($values);
    }

    // ==================== 任务队列 ====================

    /**
     * 创建记忆处理任务
     */
    public function createMemoryTask(string $sessionId, string $userId, string $taskType = 'extract'): void
    {
        $stmt = $this->pdo->prepare("
            SELECT id FROM cuige_memory_tasks 
            WHERE session_id = ? AND task_type = ? AND status = 'pending'
        ");
        $stmt->execute([$sessionId, $taskType]);
        
        if (!$stmt->fetch()) {
            $stmt = $this->pdo->prepare("
                INSERT INTO cuige_memory_tasks (session_id, user_id, task_type)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$sessionId, $userId, $taskType]);
        }
    }
}
