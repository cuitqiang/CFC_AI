<?php
declare(strict_types=1);

namespace Services\AI\Cuige;

use Services\AI\Bootstrap;
use Services\AI\Core\ModelRouter;
use Services\AI\Core\RAG\VectorService;

/**
 * 崔哥 AI 服务
 * 
 * 核心业务逻辑层，处理聊天、记忆、压缩等功能
 * 集成 RAG 知识库搜索能力
 */
class CuigeService
{
    private CuigeRepository $repository;
    private ModelRouter $modelRouter;
    private CuigeConfig $config;
    private ?VectorService $vectorService;

    public function __construct(
        CuigeRepository $repository,
        ModelRouter $modelRouter,
        CuigeConfig $config,
        ?VectorService $vectorService = null
    ) {
        $this->repository = $repository;
        $this->modelRouter = $modelRouter;
        $this->config = $config;
        $this->vectorService = $vectorService;
    }

    /**
     * 处理聊天请求
     *
     * @param string $message 用户消息
     * @param string $sessionId 会话ID
     * @param string $userId 用户ID
     * @return array 聊天响应
     */
    public function chat(string $message, string $sessionId, string $userId): array
    {
        // 确保会话存在
        $this->repository->ensureSession($sessionId, $userId);
        
        // 即时提取关键信息
        $this->extractKeyInfo($userId, $message);
        
        // 保存用户消息到短期记忆
        $this->repository->saveShortMemory($sessionId, $userId, 'user', $message);
        
        // 构建智能上下文（包含 RAG 知识库搜索）
        $messages = $this->buildContext($userId, $sessionId, $message);
        
        // 添加当前消息
        $lastMsg = end($messages);
        if ($lastMsg['role'] !== 'user' || $lastMsg['content'] !== $message) {
            $messages[] = ['role' => 'user', 'content' => $message];
        }
        
        // 智能压缩检测
        $compressionStatus = $this->checkCompression($messages);
        
        // 如果需要压缩，启动压缩引擎
        if ($compressionStatus['needs_compression']) {
            $messages = $this->compressMemory($sessionId, $userId, $messages);
        }
        
        // 调用 AI
        $response = $this->modelRouter->chat($this->config->getModel(), $messages, [
            'temperature' => 0.8,
            'max_tokens' => 1000
        ]);
        
        $reply = $response['content'] ?? '';
        
        if (empty($reply)) {
            throw new \RuntimeException('AI返回内容为空');
        }
        
        // 保存助手回复
        $this->repository->saveShortMemory($sessionId, $userId, 'assistant', $reply);
        
        // 提取 AI 回复中的重要内容
        $this->extractAIMemory($userId, $reply);
        
        // 更新会话活跃时间
        $this->repository->updateSessionActivity($sessionId);
        
        return [
            'success' => true,
            'reply' => $reply,
            'session_id' => $sessionId,
            'context_info' => [
                'tokens_used' => $compressionStatus['current_tokens'],
                'tokens_max' => $compressionStatus['max_available'],
                'usage_percent' => $compressionStatus['usage_percent'],
                'compressed' => $compressionStatus['needs_compression']
            ]
        ];
    }

    /**
     * 流式聊天请求
     *
     * @param string $message 用户消息
     * @param string $sessionId 会话ID
     * @param string $userId 用户ID
     * @param callable $onChunk 每收到一块数据的回调
     * @return array 完成后的元数据
     */
    public function streamChat(string $message, string $sessionId, string $userId, callable $onChunk): array
    {
        // 确保会话存在
        $this->repository->ensureSession($sessionId, $userId);
        
        // 即时提取关键信息
        $this->extractKeyInfo($userId, $message);
        
        // 保存用户消息到短期记忆
        $this->repository->saveShortMemory($sessionId, $userId, 'user', $message);
        
        // 构建智能上下文（包含 RAG 知识库搜索）
        $messages = $this->buildContext($userId, $sessionId, $message);
        
        // 添加当前消息
        $lastMsg = end($messages);
        if ($lastMsg['role'] !== 'user' || $lastMsg['content'] !== $message) {
            $messages[] = ['role' => 'user', 'content' => $message];
        }
        
        // 智能压缩检测
        $compressionStatus = $this->checkCompression($messages);
        
        // 如果需要压缩，启动压缩引擎
        if ($compressionStatus['needs_compression']) {
            $messages = $this->compressMemory($sessionId, $userId, $messages);
            // 通知前端正在压缩
            $onChunk(['type' => 'status', 'message' => '🧠 正在压缩记忆...']);
        }
        
        // 流式调用 AI
        $fullResponse = '';
        
        $this->modelRouter->streamChat($this->config->getModel(), $messages, function($data) use (&$fullResponse, $onChunk) {
            // 从 OpenAI/DeepSeek 流式响应中提取文本
            $content = $data['choices'][0]['delta']['content'] ?? '';
            if (!empty($content)) {
                $fullResponse .= $content;
                $onChunk(['type' => 'chunk', 'content' => $content]);
            }
        }, [
            'temperature' => 0.8,
            'max_tokens' => 1000
        ]);
        
        // 保存完整回复
        if (!empty($fullResponse)) {
            $this->repository->saveShortMemory($sessionId, $userId, 'assistant', $fullResponse);
            
            // 异步提取 AI 记忆（不阻塞）
            // $this->extractAIMemory($userId, $fullResponse);
            
            $this->repository->updateSessionActivity($sessionId);
        }
        
        return [
            'session_id' => $sessionId,
            'context_info' => [
                'tokens_used' => $compressionStatus['current_tokens'],
                'tokens_max' => $compressionStatus['max_available'],
                'usage_percent' => $compressionStatus['usage_percent'],
                'compressed' => $compressionStatus['needs_compression']
            ]
        ];
    }

    /**
     * 获取上下文状态
     */
    public function getContextStatus(string $sessionId, string $userId): array
    {
        $messages = $this->buildContext($userId, $sessionId);
        $status = $this->checkCompression($messages);
        $modelConfig = $this->config->getModelConfig();
        
        $systemTokens = 0;
        $conversationTokens = 0;
        $messageCount = 0;
        
        foreach ($messages as $msg) {
            $tokens = $this->estimateTokens($msg['content'] ?? '');
            if ($msg['role'] === 'system') {
                $systemTokens += $tokens;
            } else {
                $conversationTokens += $tokens;
                $messageCount++;
            }
        }
        
        return [
            'success' => true,
            'context' => [
                'total_tokens' => $status['current_tokens'],
                'system_tokens' => $systemTokens,
                'conversation_tokens' => $conversationTokens,
                'message_count' => $messageCount,
                'usage_percent' => $status['usage_percent'],
                'needs_compression' => $status['needs_compression'],
                'threshold' => $status['threshold'],
                'max_available' => $status['max_available']
            ],
            'model_config' => [
                'name' => $this->config->getModel(),
                'max_context' => $modelConfig['max_context'],
                'max_output' => $modelConfig['max_output'],
                'compress_at' => ($modelConfig['compress_threshold'] * 100) . '%'
            ],
            'status' => $this->getStatusEmoji($status['usage_percent'], $status['needs_compression'])
        ];
    }

    /**
     * 获取历史记录
     */
    public function getHistory(string $sessionId, int $limit = 50): array
    {
        $messages = $this->repository->getShortMemory($sessionId, $limit);
        return [
            'success' => true,
            'messages' => $messages,
            'count' => count($messages)
        ];
    }

    /**
     * 获取会话列表
     */
    public function getSessions(string $userId, int $limit = 20): array
    {
        $sessions = $this->repository->getSessions($userId, $limit);
        
        // 为每个会话添加预览（最后一条消息）
        foreach ($sessions as &$session) {
            $lastMessages = $this->repository->getShortMemory($session['session_id'], 1);
            if (!empty($lastMessages)) {
                $lastMsg = $lastMessages[0];
                $preview = mb_substr($lastMsg['content'], 0, 50);
                if (mb_strlen($lastMsg['content']) > 50) {
                    $preview .= '...';
                }
                $session['preview'] = $preview;
                $session['last_role'] = $lastMsg['role'];
            } else {
                $session['preview'] = '暂无消息';
                $session['last_role'] = '';
            }
        }
        
        return [
            'success' => true,
            'sessions' => $sessions,
            'count' => count($sessions)
        ];
    }

    /**
     * 获取用户记忆
     */
    public function getMemories(string $userId): array
    {
        return [
            'success' => true,
            'long_term' => $this->repository->getLongMemories($userId, 50),
            'episodes' => $this->repository->getEpisodicMemories($userId, 10)
        ];
    }

    /**
     * 获取用户画像
     */
    public function getProfile(string $userId): array
    {
        return [
            'success' => true,
            'profile' => $this->repository->getUserProfile($userId)
        ];
    }

    /**
     * 搜索 RAG 知识库
     * 
     * @param string $query 查询文本
     * @param int $topK 返回数量
     * @return array 搜索结果
     */
    public function searchKnowledge(string $query, int $topK = 3): array
    {
        if (!$this->vectorService) {
            return [];
        }
        
        try {
            $results = $this->vectorService->search($query, $topK);
            // 过滤空内容的结果
            return array_filter($results, fn($r) => !empty($r['content']));
        } catch (\Throwable $e) {
            // RAG 搜索失败不影响主流程
            error_log("RAG search error: " . $e->getMessage());
            return [];
        }
    }

    // ==================== 私有方法 ====================

    /**
     * 构建智能上下文
     * 
     * @param string $userId 用户ID
     * @param string $sessionId 会话ID
     * @param string $currentMessage 当前用户消息（用于RAG搜索）
     */
    private function buildContext(string $userId, string $sessionId, string $currentMessage = ''): array
    {
        $messages = [];
        
        // 系统提示
        $systemPrompt = $this->config->getSystemPrompt();
        
        // RAG 知识库搜索（如果有当前消息）
        if (!empty($currentMessage) && $this->vectorService) {
            $ragResults = $this->searchKnowledge($currentMessage, 3);
            if (!empty($ragResults)) {
                $systemPrompt .= "\n\n【相关知识库参考】\n";
                $systemPrompt .= "以下是从知识库中检索到的相关信息，请在回答时参考这些真实资料：\n";
                foreach ($ragResults as $i => $result) {
                    $content = $result['content'] ?? '';
                    $fileName = $result['file_name'] ?? '未知来源';
                    // 截取内容，避免过长
                    if (mb_strlen($content) > 800) {
                        $content = mb_substr($content, 0, 800) . '...';
                    }
                    $systemPrompt .= "\n📄 【文档：{$fileName}】\n";
                    $systemPrompt .= "{$content}\n";
                    $systemPrompt .= "---\n";
                }
                $systemPrompt .= "\n重要提示：请基于以上知识库的真实内容来回答用户问题，不要编造内容。如果知识库中没有相关信息，请诚实告知用户。\n";
            }
        }
        
        // 添加用户画像
        $profile = $this->repository->getUserProfile($userId);
        if ($profile && !empty($profile['personality_traits'])) {
            $systemPrompt .= "\n\n【用户画像】\n";
            $systemPrompt .= "性格特征：" . $profile['personality_traits'] . "\n";
            if (!empty($profile['communication_style'])) {
                $systemPrompt .= "沟通风格：" . $profile['communication_style'] . "\n";
            }
            if (!empty($profile['interests'])) {
                $systemPrompt .= "兴趣爱好：" . $profile['interests'] . "\n";
            }
        }
        
        // 添加长期记忆
        $longTermMemories = $this->repository->getLongMemories($userId);
        if (!empty($longTermMemories)) {
            $userMemories = [];
            $aiMemories = [];
            
            foreach ($longTermMemories as $mem) {
                $source = $mem['source'] ?? 'user';
                if ($source === 'ai') {
                    $aiMemories[] = $mem;
                } else {
                    $userMemories[] = $mem;
                }
            }
            
            if (!empty($userMemories)) {
                $systemPrompt .= "\n\n【你记得的关于这个用户的信息】\n";
                foreach ($userMemories as $mem) {
                    $systemPrompt .= "- {$mem['key_type']}：{$mem['key_info']}";
                    if (!empty($mem['detail'])) {
                        $systemPrompt .= "（{$mem['detail']}）";
                    }
                    $systemPrompt .= "\n";
                }
            }
            
            if (!empty($aiMemories)) {
                $systemPrompt .= "\n\n【你（崔哥）之前说过的重要内容】\n";
                foreach ($aiMemories as $mem) {
                    $systemPrompt .= "- {$mem['key_type']}：{$mem['detail']}\n";
                }
            }
        }
        
        // 添加对话摘要
        $episodes = $this->repository->getEpisodicMemories($userId);
        if (!empty($episodes)) {
            $systemPrompt .= "\n\n【最近几次对话摘要】\n";
            foreach ($episodes as $ep) {
                $systemPrompt .= "- " . $ep['summary'] . "\n";
            }
        }
        
        $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        
        // 添加短期记忆
        $shortTermMemory = $this->repository->getShortMemory($sessionId);
        foreach ($shortTermMemory as $msg) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content']
            ];
        }
        
        return $messages;
    }

    /**
     * 检查是否需要压缩
     */
    private function checkCompression(array $messages): array
    {
        $config = $this->config->getModelConfig();
        $currentTokens = $this->estimateMessagesTokens($messages);
        $threshold = (int)(($config['max_context'] - $config['max_output']) * $config['compress_threshold']);
        
        return [
            'needs_compression' => $currentTokens >= $threshold,
            'current_tokens' => $currentTokens,
            'threshold' => $threshold,
            'max_available' => $config['max_context'] - $config['max_output'],
            'usage_percent' => round($currentTokens / ($config['max_context'] - $config['max_output']) * 100, 1)
        ];
    }

    /**
     * 智能压缩记忆
     */
    private function compressMemory(string $sessionId, string $userId, array $messages): array
    {
        $systemPrompt = '';
        $conversations = [];
        
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemPrompt = $msg['content'];
            } else {
                $conversations[] = $msg;
            }
        }
        
        if (count($conversations) < 6) {
            return $messages;
        }
        
        $recentMessages = array_slice($conversations, -4);
        $toCompress = array_slice($conversations, 0, -4);
        
        if (count($toCompress) < 4) {
            return $messages;
        }
        
        // 构建压缩提示
        $compressPrompt = "请压缩以下对话记录，保留关键信息：\n\n";
        foreach ($toCompress as $msg) {
            $role = $msg['role'] === 'user' ? '用户' : '崔哥';
            $compressPrompt .= "{$role}：{$msg['content']}\n";
        }
        $compressPrompt .= "\n要求：\n1. 提取用户信息（名字、工作等）\n2. 总结对话要点\n3. 记录AI的承诺/建议\n4. 控制在200字以内";
        
        try {
            $compressResponse = $this->modelRouter->chat($this->config->getModel(), [
                ['role' => 'system', 'content' => '你是对话压缩专家，只输出压缩结果，不要其他内容。'],
                ['role' => 'user', 'content' => $compressPrompt]
            ], [
                'temperature' => 0.3,
                'max_tokens' => 500
            ]);
            
            $summary = $compressResponse['content'] ?? '';
            
            if (!empty($summary)) {
                $this->repository->saveEpisodicMemory($sessionId, $userId, $summary, count($toCompress));
                $this->repository->markMessagesCompressed($sessionId, count($toCompress));
                
                $newMessages = [];
                $newMessages[] = ['role' => 'system', 'content' => $systemPrompt];
                $newMessages[] = ['role' => 'system', 'content' => "【之前对话的摘要】\n{$summary}"];
                
                foreach ($recentMessages as $msg) {
                    $newMessages[] = $msg;
                }
                
                return $newMessages;
            }
        } catch (\Exception $e) {
            error_log("Memory compression failed: " . $e->getMessage());
        }
        
        return $messages;
    }

    /**
     * 即时提取用户关键信息
     */
    private function extractKeyInfo(string $userId, string $message): void
    {
        $patterns = [
            '名字' => '/(?:我(?:叫|是|的名字是?)|名字(?:叫|是))([^\s，。,\.]{2,8})/u',
            '年龄' => '/我(?:今年)?(\d{1,3})岁/u',
            '工作' => '/我(?:是|在|做)([^\s，。,\.]{2,20})(?:工作|上班)?/u',
            '城市' => '/我(?:在|住)([^\s，。,\.]{2,10})(?:住|工作|生活)?/u',
        ];
        
        foreach ($patterns as $category => $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                $subject = trim($matches[1]);
                if (strlen($subject) > 1) {
                    $this->repository->saveLongMemory($userId, $category, $subject, $message, 'user');
                }
            }
        }
    }

    /**
     * 提取 AI 回复中的重要内容
     */
    private function extractAIMemory(string $userId, string $aiResponse): void
    {
        $prompt = <<<PROMPT
分析以下 AI 助手（崔哥）的回复，提取需要记住的重要内容：

AI回复：{$aiResponse}

请识别以下类型的信息（如果有）：
1. 承诺 - AI承诺要做的事情
2. 建议 - AI给出的具体建议
3. 计划 - 提到的未来计划或约定
4. 关键信息 - AI告诉用户的重要信息

只输出 JSON：
{"has_content": true/false, "items": [{"type": "承诺|建议|计划|关键信息", "content": "内容"}]}

如果只是闲聊/问候，返回 {"has_content": false, "items": []}
PROMPT;

        try {
            $response = $this->modelRouter->chat($this->config->getModel(), [
                ['role' => 'system', 'content' => '只输出JSON，不要其他内容。'],
                ['role' => 'user', 'content' => $prompt]
            ], ['temperature' => 0.2, 'max_tokens' => 300]);
            
            $content = $response['content'] ?? '';
            $content = preg_replace('/```json\s*/', '', $content);
            $content = preg_replace('/```\s*/', '', $content);
            $result = json_decode(trim($content), true);
            
            if ($result && !empty($result['has_content']) && !empty($result['items'])) {
                $typeMap = [
                    '承诺' => 'AI承诺', '建议' => 'AI建议',
                    '计划' => 'AI计划', '关键信息' => 'AI告知'
                ];
                
                foreach ($result['items'] as $item) {
                    $category = $typeMap[$item['type']] ?? 'AI其他';
                    $this->repository->saveLongMemory(
                        $userId,
                        $category,
                        mb_substr($item['content'], 0, 50),
                        $item['content'],
                        'ai'
                    );
                }
            }
        } catch (\Exception $e) {
            error_log("AI memory extraction failed: " . $e->getMessage());
        }
    }

    /**
     * 估算 token 数量
     */
    private function estimateTokens(string $text): int
    {
        $chineseCount = preg_match_all('/[\x{4e00}-\x{9fff}]/u', $text);
        $otherCount = mb_strlen($text) - $chineseCount;
        return (int)($chineseCount / 1.5 + $otherCount / 4);
    }

    /**
     * 估算消息数组的 token 数量
     */
    private function estimateMessagesTokens(array $messages): int
    {
        $total = 0;
        foreach ($messages as $msg) {
            $total += $this->estimateTokens($msg['content'] ?? '');
            $total += 4;
        }
        return $total;
    }

    /**
     * 获取状态 Emoji
     */
    private function getStatusEmoji(float $usagePercent, bool $needsCompression): string
    {
        if ($usagePercent < 50) return '🟢 充足';
        if ($usagePercent < 70) return '🟡 正常';
        if ($needsCompression) return '🔴 需要压缩';
        return '🟠 接近阈值';
    }

    /**
     * TTS 语音合成
     */
    public function textToSpeech(string $text): array
    {
        $ttsConfig = $this->config->getTTSConfig();

        $ch = curl_init($ttsConfig['url'] . '/tts');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'text' => $text,
                'speaker' => $ttsConfig['speaker']
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $ttsConfig['timeout']
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException('TTS服务错误: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException('TTS服务返回: ' . $httpCode);
        }

        $result = json_decode($response, true);

        if (!isset($result['audio_base64'])) {
            throw new \RuntimeException('TTS返回格式错误');
        }

        return ['audio' => $result['audio_base64']];
    }
}
