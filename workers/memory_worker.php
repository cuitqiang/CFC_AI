<?php
/**
 * 崔哥记忆处理 Worker
 * 
 * 后台运行的 AI 服务，负责：
 * 1. 压缩短期记忆为长期记忆
 * 2. 提取关键信息
 * 3. 生成对话摘要
 * 4. 更新用户画像
 * 
 * 运行方式: wsl php /mnt/h/Desktop/RUST/CRM_AI_V7/workers/memory_worker.php
 */

declare(strict_types=1);

// 切换工作目录
chdir(__DIR__ . '/../');
require_once __DIR__ . '/../vendor/autoload.php';

use Services\AI\Bootstrap;

// 配置
const MAX_SHORT_MEMORY = 30;      // 短期记忆最大条数（超过触发压缩）
const COMPRESS_BATCH_SIZE = 20;   // 每次压缩的消息数
const WORKER_SLEEP = 5;           // 空闲时休眠秒数
const MAX_RETRIES = 3;            // 最大重试次数

class MemoryWorker
{
    private PDO $pdo;
    private $router;
    private bool $running = true;
    
    public function __construct()
    {
        Bootstrap::initialize();
        $this->router = Bootstrap::getModelRouter();
        $this->initDatabase();
        
        // 注册信号处理
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'shutdown']);
            pcntl_signal(SIGINT, [$this, 'shutdown']);
        }
    }
    
    private function initDatabase(): void
    {
        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $dbname = $_ENV['DB_DATABASE'] ?? 'cy_cfc';
        $user = $_ENV['DB_USERNAME'] ?? 'cy_cfc';
        $pass = $_ENV['DB_PASSWORD'] ?? '123456';
        
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }
    
    public function shutdown(): void
    {
        $this->log("收到停止信号，正在关闭...");
        $this->running = false;
    }
    
    public function run(): void
    {
        $this->log("记忆处理 Worker 启动");
        
        while ($this->running) {
            try {
                // 1. 处理队列任务
                $this->processQueue();
                
                // 2. 检查需要压缩的用户
                $this->checkAndCompress();
                
                // 3. 处理信号
                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }
                
            } catch (Exception $e) {
                $this->log("错误: " . $e->getMessage(), 'ERROR');
            }
            
            sleep(WORKER_SLEEP);
        }
        
        $this->log("Worker 已停止");
    }
    
    /**
     * 处理队列中的任务
     */
    private function processQueue(): void
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM cuige_memory_queue 
            WHERE status = 'pending' AND retry_count < ?
            ORDER BY priority DESC, created_at ASC
            LIMIT 5
        ");
        $stmt->execute([MAX_RETRIES]);
        $tasks = $stmt->fetchAll();
        
        foreach ($tasks as $task) {
            $this->processTask($task);
        }
    }
    
    /**
     * 处理单个任务
     */
    private function processTask(array $task): void
    {
        $taskId = $task['id'];
        $this->log("处理任务 #{$taskId}: {$task['task_type']}");
        
        // 标记为处理中
        $stmt = $this->pdo->prepare("
            UPDATE cuige_memory_queue 
            SET status = 'processing', started_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$taskId]);
        
        try {
            $payload = json_decode($task['payload'], true);
            $result = null;
            
            switch ($task['task_type']) {
                case 'compress':
                    $result = $this->taskCompress($task['user_id'], $payload);
                    break;
                case 'extract':
                    $result = $this->taskExtract($task['user_id'], $payload);
                    break;
                case 'summarize':
                    $result = $this->taskSummarize($task['user_id'], $payload);
                    break;
                case 'update_profile':
                    $result = $this->taskUpdateProfile($task['user_id'], $payload);
                    break;
                default:
                    throw new Exception("未知任务类型: {$task['task_type']}");
            }
            
            // 标记完成
            $stmt = $this->pdo->prepare("
                UPDATE cuige_memory_queue 
                SET status = 'completed', result = ?, completed_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([json_encode($result, JSON_UNESCAPED_UNICODE), $taskId]);
            
            $this->log("任务 #{$taskId} 完成");
            
        } catch (Exception $e) {
            // 标记失败，增加重试计数
            $stmt = $this->pdo->prepare("
                UPDATE cuige_memory_queue 
                SET status = 'pending', retry_count = retry_count + 1, 
                    result = ?
                WHERE id = ?
            ");
            $stmt->execute([json_encode(['error' => $e->getMessage()]), $taskId]);
            
            $this->log("任务 #{$taskId} 失败: " . $e->getMessage(), 'ERROR');
        }
    }
    
    /**
     * 检查并压缩超量的短期记忆
     */
    private function checkAndCompress(): void
    {
        // 找出短期记忆超过限制的用户
        $stmt = $this->pdo->query("
            SELECT user_id, COUNT(*) as cnt 
            FROM cuige_short_memory 
            GROUP BY user_id 
            HAVING cnt > " . MAX_SHORT_MEMORY
        );
        $users = $stmt->fetchAll();
        
        foreach ($users as $user) {
            $this->log("用户 {$user['user_id']} 有 {$user['cnt']} 条短期记忆，触发压缩");
            
            // 添加压缩任务
            $this->addTask($user['user_id'], 'compress', [
                'count' => $user['cnt'],
                'compress_size' => COMPRESS_BATCH_SIZE
            ]);
        }
    }
    
    /**
     * 添加任务到队列
     */
    private function addTask(string $userId, string $type, array $payload, int $priority = 5): void
    {
        // 检查是否已有相同任务
        $stmt = $this->pdo->prepare("
            SELECT id FROM cuige_memory_queue 
            WHERE user_id = ? AND task_type = ? AND status IN ('pending', 'processing')
            LIMIT 1
        ");
        $stmt->execute([$userId, $type]);
        
        if ($stmt->fetch()) {
            return; // 已有任务，跳过
        }
        
        $stmt = $this->pdo->prepare("
            INSERT INTO cuige_memory_queue (user_id, task_type, payload, priority) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $type, json_encode($payload), $priority]);
    }
    
    /**
     * 任务：压缩短期记忆
     */
    private function taskCompress(string $userId, array $payload): array
    {
        $compressSize = $payload['compress_size'] ?? COMPRESS_BATCH_SIZE;
        
        // 获取最旧的一批消息
        $stmt = $this->pdo->prepare("
            SELECT id, session_id, role, content, created_at 
            FROM cuige_short_memory 
            WHERE user_id = ? 
            ORDER BY id ASC 
            LIMIT {$compressSize}
        ");
        $stmt->execute([$userId]);
        $messages = $stmt->fetchAll();
        
        if (count($messages) < 5) {
            return ['skipped' => true, 'reason' => 'not enough messages'];
        }
        
        // 按 session 分组
        $sessions = [];
        foreach ($messages as $msg) {
            $sessions[$msg['session_id']][] = $msg;
        }
        
        $extracted = [];
        $summarized = [];
        
        foreach ($sessions as $sessionId => $sessionMsgs) {
            // 1. 提取关键信息
            $keyInfo = $this->extractKeyInfo($userId, $sessionMsgs);
            $extracted = array_merge($extracted, $keyInfo);
            
            // 2. 生成对话摘要
            if (count($sessionMsgs) >= 4) {
                $summary = $this->generateSummary($userId, $sessionId, $sessionMsgs);
                if ($summary) {
                    $summarized[] = $summary;
                }
            }
        }
        
        // 3. 删除已处理的短期记忆
        $ids = array_column($messages, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("DELETE FROM cuige_short_memory WHERE id IN ({$placeholders})");
        $stmt->execute($ids);
        
        return [
            'compressed' => count($messages),
            'extracted' => count($extracted),
            'summarized' => count($summarized)
        ];
    }
    
    /**
     * 从对话中提取关键信息
     */
    private function extractKeyInfo(string $userId, array $messages): array
    {
        // 构建对话文本
        $conversation = "";
        foreach ($messages as $msg) {
            $role = $msg['role'] === 'user' ? '用户' : '崔哥';
            $conversation .= "{$role}: {$msg['content']}\n";
        }
        
        $prompt = <<<PROMPT
分析以下对话，提取用户的关键信息。只提取明确提到的信息，不要推测。

对话内容：
{$conversation}

请以 JSON 数组格式返回，每项包含：
- category: 类别 (identity/preference/event/relationship/knowledge)
- subject: 主题（如：姓名、工作、去北京）
- content: 具体内容
- importance: 重要性 1-10
- confidence: 置信度 0-100

只返回 JSON，不要其他内容。如果没有提取到信息，返回空数组 []
PROMPT;

        try {
            $response = $this->router->chat('deepseek-chat', [
                ['role' => 'system', 'content' => '你是一个信息提取专家，擅长从对话中提取结构化信息。'],
                ['role' => 'user', 'content' => $prompt]
            ], ['temperature' => 0.3, 'max_tokens' => 1000]);
            
            $content = $response['content'] ?? '';
            
            // 尝试解析 JSON
            $content = preg_replace('/```json\s*|\s*```/', '', $content);
            $items = json_decode($content, true);
            
            if (!is_array($items)) {
                return [];
            }
            
            // 保存到长期记忆
            foreach ($items as $item) {
                $this->saveLongMemory($userId, $item);
            }
            
            return $items;
            
        } catch (Exception $e) {
            $this->log("提取信息失败: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    /**
     * 保存长期记忆
     */
    private function saveLongMemory(string $userId, array $item): void
    {
        if (empty($item['subject']) || empty($item['content'])) {
            return;
        }
        
        $stmt = $this->pdo->prepare("
            INSERT INTO cuige_long_memory 
                (user_id, category, subject, content, importance, confidence, source_summary) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                content = VALUES(content),
                importance = GREATEST(importance, VALUES(importance)),
                confidence = VALUES(confidence),
                mention_count = mention_count + 1,
                last_mentioned_at = NOW()
        ");
        
        $stmt->execute([
            $userId,
            $item['category'] ?? 'knowledge',
            $item['subject'],
            $item['content'],
            $item['importance'] ?? 5,
            $item['confidence'] ?? 80,
            '自动提取 @ ' . date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * 生成对话摘要
     */
    private function generateSummary(string $userId, string $sessionId, array $messages): ?array
    {
        $conversation = "";
        foreach ($messages as $msg) {
            $role = $msg['role'] === 'user' ? '用户' : '崔哥';
            $conversation .= "{$role}: {$msg['content']}\n";
        }
        
        $prompt = <<<PROMPT
为以下对话生成一个简短摘要。

对话内容：
{$conversation}

请返回 JSON 格式：
{
    "title": "对话标题（10字以内）",
    "summary": "对话摘要（50字以内）",
    "key_points": ["要点1", "要点2"],
    "emotional_tone": "positive/negative/neutral"
}

只返回 JSON，不要其他内容。
PROMPT;

        try {
            $response = $this->router->chat('deepseek-chat', [
                ['role' => 'system', 'content' => '你是一个对话摘要专家。'],
                ['role' => 'user', 'content' => $prompt]
            ], ['temperature' => 0.3, 'max_tokens' => 500]);
            
            $content = $response['content'] ?? '';
            $content = preg_replace('/```json\s*|\s*```/', '', $content);
            $result = json_decode($content, true);
            
            if (!$result || empty($result['title'])) {
                return null;
            }
            
            // 保存到情景记忆
            $startTime = $messages[0]['created_at'];
            $endTime = end($messages)['created_at'];
            
            $stmt = $this->pdo->prepare("
                INSERT INTO cuige_episode_memory 
                    (user_id, session_id, episode_type, title, summary, key_points, emotional_tone, message_count, start_time, end_time) 
                VALUES (?, ?, 'conversation', ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $sessionId,
                $result['title'],
                $result['summary'],
                json_encode($result['key_points'] ?? [], JSON_UNESCAPED_UNICODE),
                $result['emotional_tone'] ?? 'neutral',
                count($messages),
                $startTime,
                $endTime
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            $this->log("生成摘要失败: " . $e->getMessage(), 'ERROR');
            return null;
        }
    }
    
    /**
     * 任务：提取信息（手动触发）
     */
    private function taskExtract(string $userId, array $payload): array
    {
        $messages = $payload['messages'] ?? [];
        return $this->extractKeyInfo($userId, $messages);
    }
    
    /**
     * 任务：生成摘要（手动触发）
     */
    private function taskSummarize(string $userId, array $payload): array
    {
        $sessionId = $payload['session_id'] ?? '';
        $messages = $payload['messages'] ?? [];
        
        $result = $this->generateSummary($userId, $sessionId, $messages);
        return $result ?? ['error' => 'failed to generate summary'];
    }
    
    /**
     * 任务：更新用户画像
     */
    private function taskUpdateProfile(string $userId, array $payload): array
    {
        // 获取用户的长期记忆
        $stmt = $this->pdo->prepare("
            SELECT category, subject, content 
            FROM cuige_long_memory 
            WHERE user_id = ? AND is_active = 1
            ORDER BY importance DESC, mention_count DESC
            LIMIT 50
        ");
        $stmt->execute([$userId]);
        $memories = $stmt->fetchAll();
        
        if (empty($memories)) {
            return ['skipped' => true, 'reason' => 'no memories'];
        }
        
        $memoryText = "";
        foreach ($memories as $m) {
            $memoryText .= "- [{$m['category']}] {$m['subject']}: {$m['content']}\n";
        }
        
        $prompt = <<<PROMPT
基于以下用户信息，生成用户画像：

{$memoryText}

请返回 JSON 格式：
{
    "nickname": "用户的称呼",
    "personality_summary": "性格特点描述（50字以内）",
    "interests": ["兴趣1", "兴趣2"],
    "communication_style": "formal/casual/humorous",
    "topics_of_interest": ["话题1", "话题2"]
}

只返回 JSON。
PROMPT;

        try {
            $response = $this->router->chat('deepseek-chat', [
                ['role' => 'system', 'content' => '你是一个用户分析专家。'],
                ['role' => 'user', 'content' => $prompt]
            ], ['temperature' => 0.3, 'max_tokens' => 500]);
            
            $content = $response['content'] ?? '';
            $content = preg_replace('/```json\s*|\s*```/', '', $content);
            $profile = json_decode($content, true);
            
            if ($profile) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO cuige_user_profile 
                        (user_id, nickname, personality_summary, interests, communication_style, topics_of_interest) 
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        nickname = VALUES(nickname),
                        personality_summary = VALUES(personality_summary),
                        interests = VALUES(interests),
                        communication_style = VALUES(communication_style),
                        topics_of_interest = VALUES(topics_of_interest),
                        profile_updated_at = NOW()
                ");
                $stmt->execute([
                    $userId,
                    $profile['nickname'] ?? '朋友',
                    $profile['personality_summary'] ?? null,
                    json_encode($profile['interests'] ?? [], JSON_UNESCAPED_UNICODE),
                    $profile['communication_style'] ?? 'casual',
                    json_encode($profile['topics_of_interest'] ?? [], JSON_UNESCAPED_UNICODE)
                ]);
                
                return $profile;
            }
            
            return ['error' => 'failed to parse profile'];
            
        } catch (Exception $e) {
            $this->log("更新画像失败: " . $e->getMessage(), 'ERROR');
            return ['error' => $e->getMessage()];
        }
    }
    
    private function log(string $message, string $level = 'INFO'): void
    {
        $time = date('Y-m-d H:i:s');
        echo "[{$time}] [{$level}] {$message}\n";
    }
}

// 运行 Worker
$worker = new MemoryWorker();
$worker->run();
