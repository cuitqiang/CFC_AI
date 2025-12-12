<?php
declare(strict_types=1);

namespace Services\AI\Memory;

use PDO;
use Services\AI\Bootstrap;

/**
 * 崔哥智能记忆引擎 V3
 * 
 * 实现类似 Claude/ChatGPT 的分层记忆压缩系统：
 * 1. 工作记忆 (Working Memory) - 当前对话窗口
 * 2. 情景记忆 (Episodic Memory) - 对话摘要
 * 3. 语义记忆 (Semantic Memory) - 用户事实库
 * 
 * @author CRM_AI_V7
 */
class CuigeMemoryEngine
{
    private PDO $pdo;
    private $modelRouter;
    
    // 配置参数
    private int $workingMemorySize = 10;      // 工作记忆条数
    private int $compressionThreshold = 20;   // 触发压缩的消息数
    private int $summaryBatchSize = 10;       // 每次摘要的消息数
    private float $decayRate = 0.95;          // 时间衰减率（每天）
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->modelRouter = Bootstrap::getModelRouter();
    }
    
    /**
     * 构建完整的对话上下文
     * 核心方法：组合三层记忆形成最终 prompt
     */
    public function buildContext(string $userId, string $sessionId): array
    {
        $messages = [];
        
        // 1. 系统提示 + 用户档案
        $systemPrompt = $this->buildSystemPrompt($userId);
        $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        
        // 2. 注入历史摘要（情景记忆）
        $summaries = $this->getEpisodicMemory($userId, $sessionId);
        if (!empty($summaries)) {
            $summaryText = "【之前对话的关键信息】\n" . implode("\n", $summaries);
            $messages[] = ['role' => 'system', 'content' => $summaryText];
        }
        
        // 3. 工作记忆（最近对话）
        $workingMemory = $this->getWorkingMemory($sessionId);
        foreach ($workingMemory as $msg) {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }
        
        return $messages;
    }
    
    /**
     * 构建系统提示（包含用户档案）
     */
    private function buildSystemPrompt(string $userId): string
    {
        $basePrompt = <<<PROMPT
你是崔哥，一个说话直接、幽默风趣的AI助手。你的特点：
1. 说话接地气，偶尔带点调侃但不失礼貌
2. 回答简洁有力，不啰嗦
3. 遇到不懂的会坦诚说"这个我还真不太清楚"
4. 会主动提起你记得的关于用户的信息，也记得你自己说过的话
5. 语气像个老朋友，但保持专业
6. 如果你之前给过建议或做过承诺，要记住并跟进

记住：你是崔哥，不是普通的AI助手。保持你的个性！
PROMPT;
        
        // 注入语义记忆（用户事实）
        $facts = $this->getSemanticMemory($userId);
        if (!empty($facts)) {
            $basePrompt .= "\n\n【你记得的关于这个用户的信息】\n";
            foreach ($facts as $fact) {
                $importance = $fact['importance'] > 7 ? '（重要）' : '';
                $basePrompt .= "- {$fact['category']}: {$fact['content']}{$importance}\n";
            }
        }
        
        // 注入 AI 自己说过的重要内容
        $aiMemories = $this->getAIMemories($userId);
        if (!empty($aiMemories)) {
            $basePrompt .= "\n【你（崔哥）之前说过的重要内容】\n";
            foreach ($aiMemories as $mem) {
                $basePrompt .= "- {$mem['category']}: {$mem['content']}\n";
            }
        }
        
        // 注入用户画像
        $profile = $this->getUserProfile($userId);
        if ($profile) {
            $basePrompt .= "\n【用户特征分析】\n";
            if (!empty($profile['personality_summary'])) {
                $basePrompt .= "性格: {$profile['personality_summary']}\n";
            }
            if (!empty($profile['communication_style'])) {
                $basePrompt .= "沟通风格: {$profile['communication_style']}\n";
            }
            if (!empty($profile['interests'])) {
                $basePrompt .= "兴趣: {$profile['interests']}\n";
            }
        }
        
        return $basePrompt;
    }
    
    /**
     * 获取 AI 自己说过的重要内容
     */
    public function getAIMemories(string $userId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("
            SELECT category, subject, content, importance
            FROM cuige_long_memory
            WHERE user_id = ? AND source = 'ai' AND is_active = 1
            ORDER BY importance DESC, last_mentioned_at DESC
            LIMIT {$limit}
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 获取工作记忆（最近 N 条消息）
     */
    public function getWorkingMemory(string $sessionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT role, content, created_at
            FROM cuige_short_memory
            WHERE session_id = ?
            ORDER BY created_at DESC
            LIMIT {$this->workingMemorySize}
        ");
        $stmt->execute([$sessionId]);
        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    /**
     * 获取情景记忆（对话摘要）
     */
    public function getEpisodicMemory(string $userId, string $sessionId): array
    {
        // 当前会话的摘要
        $stmt = $this->pdo->prepare("
            SELECT summary, key_points, importance_score
            FROM cuige_conversation_summaries
            WHERE session_id = ? AND is_archived = 0
            ORDER BY sequence_num DESC
            LIMIT 3
        ");
        $stmt->execute([$sessionId]);
        $currentSummaries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 历史会话的重要摘要
        $stmt = $this->pdo->prepare("
            SELECT summary, key_points
            FROM cuige_conversation_summaries
            WHERE user_id = ? AND session_id != ? AND importance_score >= 7
            ORDER BY created_at DESC
            LIMIT 3
        ");
        $stmt->execute([$userId, $sessionId]);
        $historySummaries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $result = [];
        foreach ($historySummaries as $s) {
            $result[] = "[历史] {$s['summary']}";
        }
        foreach (array_reverse($currentSummaries) as $s) {
            $result[] = $s['summary'];
        }
        
        return $result;
    }
    
    /**
     * 获取语义记忆（用户事实库）
     */
    public function getSemanticMemory(string $userId, int $limit = 15): array
    {
        $stmt = $this->pdo->prepare("
            SELECT category, subject, content, importance, confidence, mention_count
            FROM cuige_long_memory
            WHERE user_id = ? AND is_active = 1 AND confidence >= 60
            ORDER BY importance DESC, mention_count DESC, last_mentioned_at DESC
            LIMIT {$limit}
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 获取用户画像
     */
    public function getUserProfile(string $userId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT personality_summary, communication_style, interests, 
                   topics_of_interest as key_topics
            FROM cuige_user_profile
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // 处理 JSON 字段
            if (is_string($result['interests'])) {
                $result['interests'] = implode(', ', json_decode($result['interests'], true) ?? []);
            }
            if (is_string($result['key_topics'])) {
                $result['key_topics'] = implode(', ', json_decode($result['key_topics'], true) ?? []);
            }
        }
        
        return $result ?: null;
    }
    
    /**
     * 保存消息到工作记忆
     */
    public function saveToWorkingMemory(string $sessionId, string $userId, string $role, string $content): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO cuige_short_memory (session_id, user_id, role, content)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$sessionId, $userId, $role, $content]);
        
        // 检查是否需要触发压缩
        $this->checkAndCompress($sessionId, $userId);
    }
    
    /**
     * 检查并触发压缩
     * 当工作记忆超过阈值时，触发递归摘要
     */
    private function checkAndCompress(string $sessionId, string $userId): void
    {
        // 统计未压缩的消息数
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as cnt FROM cuige_short_memory
            WHERE session_id = ? AND is_summarized = 0
        ");
        $stmt->execute([$sessionId]);
        $count = (int)$stmt->fetch()['cnt'];
        
        if ($count >= $this->compressionThreshold) {
            $this->triggerCompression($sessionId, $userId);
        }
    }
    
    /**
     * 触发压缩任务
     */
    private function triggerCompression(string $sessionId, string $userId): void
    {
        // 插入压缩任务
        $stmt = $this->pdo->prepare("
            INSERT INTO cuige_memory_tasks (session_id, user_id, task_type, status, priority)
            VALUES (?, ?, 'compress', 'pending', 10)
            ON DUPLICATE KEY UPDATE status = 'pending', updated_at = NOW()
        ");
        $stmt->execute([$sessionId, $userId]);
    }
    
    /**
     * 执行递归摘要压缩
     * 核心压缩算法：将 N 条消息压缩为 1 条摘要
     */
    public function compressMessages(string $sessionId, string $userId): void
    {
        // 获取未压缩的消息
        $stmt = $this->pdo->prepare("
            SELECT id, role, content, created_at
            FROM cuige_short_memory
            WHERE session_id = ? AND is_summarized = 0
            ORDER BY created_at ASC
            LIMIT {$this->summaryBatchSize}
        ");
        $stmt->execute([$sessionId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($messages) < $this->summaryBatchSize) {
            return; // 不足一批，不压缩
        }
        
        // 构建压缩 prompt
        $conversation = "";
        foreach ($messages as $msg) {
            $role = $msg['role'] === 'user' ? '用户' : '崔哥';
            $conversation .= "{$role}: {$msg['content']}\n";
        }
        
        $compressPrompt = <<<PROMPT
请分析以下对话，提取关键信息：

{$conversation}

请输出 JSON 格式：
{
    "summary": "一句话总结这段对话的主要内容（30字以内）",
    "key_facts": ["提取的关键事实1", "事实2"],
    "user_intent": "用户的主要意图或需求",
    "emotional_tone": "用户的情绪基调（积极/中性/消极）",
    "importance_score": 1-10的重要性评分,
    "action_items": ["需要记住的承诺或待办事项"]
}
PROMPT;
        
        try {
            $response = $this->modelRouter->chat('deepseek-v3-250324', [
                ['role' => 'system', 'content' => '你是一个对话分析专家，负责提取对话中的关键信息。只输出JSON，不要其他内容。'],
                ['role' => 'user', 'content' => $compressPrompt]
            ], ['temperature' => 0.3, 'max_tokens' => 500]);
            
            $content = $response['content'] ?? '';
            
            // 清理 JSON
            $content = preg_replace('/```json\s*/', '', $content);
            $content = preg_replace('/```\s*/', '', $content);
            $result = json_decode(trim($content), true);
            
            if ($result && isset($result['summary'])) {
                // 保存摘要
                $this->saveSummary($sessionId, $userId, $result, $messages);
                
                // 提取事实到语义记忆
                $this->extractFacts($userId, $result);
                
                // 标记消息已压缩
                $ids = array_column($messages, 'id');
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $this->pdo->prepare("
                    UPDATE cuige_short_memory SET is_summarized = 1
                    WHERE id IN ({$placeholders})
                ");
                $stmt->execute($ids);
            }
            
        } catch (\Exception $e) {
            error_log("压缩失败: " . $e->getMessage());
        }
    }
    
    /**
     * 保存对话摘要
     */
    private function saveSummary(string $sessionId, string $userId, array $result, array $messages): void
    {
        // 获取序列号
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(MAX(sequence_num), 0) + 1 as next_seq
            FROM cuige_conversation_summaries
            WHERE session_id = ?
        ");
        $stmt->execute([$sessionId]);
        $seqNum = (int)$stmt->fetch()['next_seq'];
        
        $stmt = $this->pdo->prepare("
            INSERT INTO cuige_conversation_summaries 
            (session_id, user_id, sequence_num, summary, key_points, emotional_tone, 
             importance_score, message_count, start_time, end_time)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $sessionId,
            $userId,
            $seqNum,
            $result['summary'],
            json_encode($result['key_facts'] ?? [], JSON_UNESCAPED_UNICODE),
            $result['emotional_tone'] ?? 'neutral',
            $result['importance_score'] ?? 5,
            count($messages),
            $messages[0]['created_at'],
            $messages[count($messages) - 1]['created_at']
        ]);
    }
    
    /**
     * 提取事实到语义记忆
     */
    private function extractFacts(string $userId, array $result): void
    {
        if (empty($result['key_facts'])) {
            return;
        }
        
        foreach ($result['key_facts'] as $fact) {
            // 分类事实
            $category = $this->categorizeFact($fact);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO cuige_long_memory 
                (user_id, category, subject, content, importance, confidence, source)
                VALUES (?, ?, ?, ?, ?, ?, 'user')
                ON DUPLICATE KEY UPDATE 
                    mention_count = mention_count + 1,
                    last_mentioned_at = NOW(),
                    confidence = LEAST(confidence + 5, 100)
            ");
            $stmt->execute([
                $userId,
                $category,
                mb_substr($fact, 0, 50),
                $fact,
                $result['importance_score'] ?? 5,
                75
            ]);
        }
    }
    
    /**
     * 提取 AI 回复中的重要内容
     * 承诺、建议、计划、关键信息等
     */
    public function extractAIResponseFacts(string $userId, string $aiResponse): void
    {
        // 使用 AI 分析自己说了什么重要内容
        $prompt = <<<PROMPT
分析以下 AI 助手（崔哥）的回复，提取需要记住的重要内容：

AI回复：{$aiResponse}

请识别并提取以下类型的信息（如果有的话）：
1. 承诺 - AI 承诺要做的事情（如"我帮你..."、"下次我给你..."）
2. 建议 - AI 给出的具体建议
3. 计划 - 提到的未来计划或约定
4. 关键信息 - AI 告诉用户的重要信息

只输出 JSON 格式：
{
    "has_important_content": true/false,
    "items": [
        {"type": "承诺|建议|计划|关键信息", "content": "具体内容"},
        ...
    ]
}

如果回复只是闲聊、问候、没有实质性承诺/建议，返回：{"has_important_content": false, "items": []}
PROMPT;

        try {
            $response = $this->modelRouter->chat('deepseek-v3-250324', [
                ['role' => 'system', 'content' => '你是一个对话分析专家，负责提取 AI 回复中的承诺和关键信息。只输出JSON。'],
                ['role' => 'user', 'content' => $prompt]
            ], ['temperature' => 0.2, 'max_tokens' => 300]);
            
            $content = $response['content'] ?? '';
            
            // 清理 JSON
            $content = preg_replace('/```json\s*/', '', $content);
            $content = preg_replace('/```\s*/', '', $content);
            $result = json_decode(trim($content), true);
            
            if ($result && !empty($result['has_important_content']) && !empty($result['items'])) {
                foreach ($result['items'] as $item) {
                    $this->saveAIMemory($userId, $item['type'], $item['content']);
                }
            }
        } catch (\Exception $e) {
            error_log("AI memory extraction failed: " . $e->getMessage());
        }
    }
    
    /**
     * 保存 AI 的记忆（承诺、建议等）
     */
    private function saveAIMemory(string $userId, string $type, string $content): void
    {
        // 映射类型到分类
        $categoryMap = [
            '承诺' => 'AI承诺',
            '建议' => 'AI建议',
            '计划' => 'AI计划',
            '关键信息' => 'AI告知'
        ];
        $category = $categoryMap[$type] ?? 'AI其他';
        
        $stmt = $this->pdo->prepare("
            INSERT INTO cuige_long_memory 
            (user_id, category, subject, content, importance, confidence, source)
            VALUES (?, ?, ?, ?, 8, 90, 'ai')
            ON DUPLICATE KEY UPDATE 
                mention_count = mention_count + 1,
                last_mentioned_at = NOW()
        ");
        $stmt->execute([
            $userId,
            $category,
            mb_substr($content, 0, 50),
            $content
        ]);
    }
    
    /**
     * 事实分类
     */
    private function categorizeFact(string $fact): string
    {
        $patterns = [
            '身份' => '/(?:叫|是|名字|姓|名)/u',
            '工作' => '/(?:工作|职业|公司|上班|做|从事)/u',
            '位置' => '/(?:在|住|来自|城市|地方)/u',
            '年龄' => '/(?:岁|年龄|出生|生日)/u',
            '家庭' => '/(?:家人|父母|孩子|老婆|老公|结婚)/u',
            '爱好' => '/(?:喜欢|爱好|兴趣|经常|习惯)/u',
            '偏好' => '/(?:喜欢|讨厌|偏好|想要|希望)/u',
            '事件' => '/(?:发生|经历|遇到|最近|上次)/u',
        ];
        
        foreach ($patterns as $category => $pattern) {
            if (preg_match($pattern, $fact)) {
                return $category;
            }
        }
        
        return '其他';
    }
    
    /**
     * 递归摘要：将多个摘要压缩成超级摘要
     */
    public function compressSummaries(string $userId): void
    {
        // 获取需要压缩的摘要（超过10个）
        $stmt = $this->pdo->prepare("
            SELECT id, summary, key_points, importance_score
            FROM cuige_conversation_summaries
            WHERE user_id = ? AND is_archived = 0
            ORDER BY created_at ASC
            LIMIT 10
        ");
        $stmt->execute([$userId]);
        $summaries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($summaries) < 10) {
            return;
        }
        
        // 构建超级摘要 prompt
        $text = "";
        foreach ($summaries as $s) {
            $text .= "- {$s['summary']}\n";
        }
        
        $prompt = <<<PROMPT
以下是与用户多次对话的摘要，请整合成一个精炼的总结：

{$text}

要求：
1. 提取最重要的3-5个关键信息
2. 合并重复的内容
3. 突出用户的核心需求和特征
4. 100字以内

直接输出总结内容，不要其他格式。
PROMPT;
        
        try {
            $response = $this->modelRouter->chat('deepseek-v3-250324', [
                ['role' => 'user', 'content' => $prompt]
            ], ['temperature' => 0.3, 'max_tokens' => 200]);
            
            $superSummary = $response['content'] ?? '';
            
            if ($superSummary) {
                // 归档旧摘要
                $ids = array_column($summaries, 'id');
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $this->pdo->prepare("
                    UPDATE cuige_conversation_summaries SET is_archived = 1
                    WHERE id IN ({$placeholders})
                ");
                $stmt->execute($ids);
                
                // 保存超级摘要
                $stmt = $this->pdo->prepare("
                    INSERT INTO cuige_conversation_summaries 
                    (session_id, user_id, sequence_num, summary, importance_score, 
                     message_count, is_super_summary)
                    VALUES ('_super_', ?, 0, ?, 10, ?, 1)
                ");
                $stmt->execute([$userId, $superSummary, count($summaries)]);
            }
            
        } catch (\Exception $e) {
            error_log("超级摘要失败: " . $e->getMessage());
        }
    }
    
    /**
     * 分析并更新用户画像
     */
    public function updateUserProfile(string $userId): void
    {
        // 获取用户的所有语义记忆
        $facts = $this->getSemanticMemory($userId, 50);
        if (count($facts) < 5) {
            return; // 数据太少，不分析
        }
        
        $factText = "";
        foreach ($facts as $f) {
            $factText .= "- [{$f['category']}] {$f['content']}\n";
        }
        
        $prompt = <<<PROMPT
基于以下用户信息，分析用户画像：

{$factText}

输出 JSON 格式：
{
    "personality_summary": "性格特点描述（20字以内）",
    "communication_style": "沟通风格偏好（如：直接/委婉/幽默等）",
    "interests": "主要兴趣爱好（逗号分隔）",
    "key_topics": "经常谈论的话题（逗号分隔）",
    "emotional_baseline": "情绪基调（积极/中性/消极）"
}
PROMPT;
        
        try {
            $response = $this->modelRouter->chat('deepseek-v3-250324', [
                ['role' => 'system', 'content' => '你是用户画像分析专家。只输出JSON。'],
                ['role' => 'user', 'content' => $prompt]
            ], ['temperature' => 0.3, 'max_tokens' => 300]);
            
            $content = $response['content'] ?? '';
            $content = preg_replace('/```json\s*/', '', $content);
            $content = preg_replace('/```\s*/', '', $content);
            $profile = json_decode(trim($content), true);
            
            if ($profile) {
                // 将数组转为 JSON 存储
                $interests = is_array($profile['interests'] ?? null) 
                    ? json_encode($profile['interests'], JSON_UNESCAPED_UNICODE)
                    : json_encode(explode(',', $profile['interests'] ?? ''), JSON_UNESCAPED_UNICODE);
                    
                $keyTopics = is_array($profile['key_topics'] ?? null)
                    ? json_encode($profile['key_topics'], JSON_UNESCAPED_UNICODE)
                    : json_encode(explode(',', $profile['key_topics'] ?? ''), JSON_UNESCAPED_UNICODE);
                
                $stmt = $this->pdo->prepare("
                    INSERT INTO cuige_user_profile 
                    (user_id, personality_summary, communication_style, interests, topics_of_interest)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        personality_summary = VALUES(personality_summary),
                        communication_style = VALUES(communication_style),
                        interests = VALUES(interests),
                        topics_of_interest = VALUES(topics_of_interest),
                        profile_updated_at = NOW()
                ");
                $stmt->execute([
                    $userId,
                    $profile['personality_summary'] ?? '',
                    $profile['communication_style'] ?? '',
                    $interests,
                    $keyTopics
                ]);
            }
            
        } catch (\Exception $e) {
            error_log("用户画像更新失败: " . $e->getMessage());
        }
    }
    
    /**
     * 记忆衰减：降低旧记忆的重要性
     */
    public function decayMemories(): void
    {
        // 语义记忆衰减
        $this->pdo->exec("
            UPDATE cuige_long_memory
            SET importance = GREATEST(importance * {$this->decayRate}, 1)
            WHERE last_mentioned_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        
        // 低重要性记忆标记为不活跃
        $this->pdo->exec("
            UPDATE cuige_long_memory
            SET is_active = 0
            WHERE importance < 2 AND mention_count < 2
        ");
    }
    
    /**
     * 即时提取关键信息（实时处理）
     */
    public function extractImmediateFacts(string $userId, string $message): void
    {
        $patterns = [
            '身份' => [
                'pattern' => '/(?:我(?:叫|是|的名字是?)|名字(?:叫|是))([^\s，。,\.]{2,8})/u',
                'importance' => 9,
            ],
            '年龄' => [
                'pattern' => '/我(?:今年)?(\d{1,3})岁/u',
                'importance' => 8,
            ],
            '工作' => [
                'pattern' => '/我(?:是|在|做)([^\s，。,\.]{2,20})(?:工作|上班|的)?/u',
                'importance' => 8,
            ],
            '位置' => [
                'pattern' => '/我(?:在|住|来自)([^\s，。,\.]{2,10})/u',
                'importance' => 7,
            ],
            '爱好' => [
                'pattern' => '/我(?:喜欢|爱|经常)([^\s，。,\.]{2,15})/u',
                'importance' => 6,
            ],
        ];
        
        foreach ($patterns as $category => $config) {
            if (preg_match($config['pattern'], $message, $matches)) {
                $content = trim($matches[1]);
                if (mb_strlen($content) >= 2) {
                    $stmt = $this->pdo->prepare("
                        INSERT INTO cuige_long_memory 
                        (user_id, category, subject, content, importance, confidence)
                        VALUES (?, ?, ?, ?, ?, 85)
                        ON DUPLICATE KEY UPDATE 
                            content = VALUES(content),
                            mention_count = mention_count + 1,
                            last_mentioned_at = NOW(),
                            importance = GREATEST(importance, VALUES(importance)),
                            confidence = LEAST(confidence + 5, 100)
                    ");
                    $stmt->execute([
                        $userId,
                        $category,
                        $content,
                        $message,
                        $config['importance']
                    ]);
                }
            }
        }
    }
}
