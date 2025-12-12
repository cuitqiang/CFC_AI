<?php
declare(strict_types=1);

namespace Services\AI\Tasks;

use Services\AI\Core\ModelRouter;

/**
 * 辩论 Agent 任务类
 * 继承 BaseTask，处理辩论相关的 AI 任务
 */
class DebateAgent extends BaseTask
{
    private ModelRouter $router;
    private array $agentConfig;
    private array $conversationHistory = [];

    /**
     * 初始化任务
     */
    protected function initialize(): void
    {
        $this->name = 'debate_agent';
        $this->description = '辩论Agent，支持多角色讨论和观点交流';
    }

    /**
     * 设置 ModelRouter
     *
     * @param ModelRouter $router
     * @return self
     */
    public function setRouter(ModelRouter $router): self
    {
        $this->router = $router;
        return $this;
    }

    /**
     * 设置 Agent 配置
     *
     * @param array $config Agent配置
     * @return self
     */
    public function setAgentConfig(array $config): self
    {
        $this->agentConfig = $config;
        return $this;
    }

    /**
     * 获取 Agent 配置
     *
     * @param string $agentId
     * @return array|null
     */
    public function getAgentConfig(string $agentId): ?array
    {
        return $this->agentConfig[$agentId] ?? null;
    }

    /**
     * 执行辩论任务
     *
     * @param array $input ['topic' => '辩论主题', 'agent_id' => 'Agent标识', 'context' => [...]]
     * @return array 执行结果
     */
    public function execute(array $input): array
    {
        try {
            $this->validateInput($input, ['topic', 'agent_id']);

            $topic = $input['topic'];
            $agentId = $input['agent_id'];
            $context = $input['context'] ?? [];

            // 获取 Agent 配置
            $agent = $this->getAgentConfig($agentId);
            if (!$agent) {
                return $this->error("未知的Agent: {$agentId}");
            }

            $this->log('info', 'Debate agent executing', [
                'agent' => $agentId,
                'topic' => $topic,
            ]);

            // 构建提示词
            $prompt = $this->buildDebatePrompt($topic, $context);

            // 调用 AI
            $response = $this->router->chat(
                $this->config['model'] ?? 'deepseek-chat',
                [
                    $this->buildSystemPrompt($agent['system_prompt']),
                    $this->buildUserMessage($prompt),
                ],
                [
                    'temperature' => $agent['temperature'] ?? 0.8,
                    'max_tokens' => $agent['max_tokens'] ?? 400,
                ]
            );

            $content = $response['choices'][0]['message']['content'] ?? '';

            if (empty($content)) {
                return $this->error('AI 响应为空');
            }

            // 记录到对话历史
            $this->conversationHistory[] = [
                'agent_id' => $agentId,
                'agent_name' => $agent['name'],
                'content' => $content,
                'time' => time(),
            ];

            $this->log('info', 'Debate agent completed', ['agent' => $agentId]);

            return $this->success([
                'agent_id' => $agentId,
                'agent_name' => $agent['name'],
                'content' => $content,
                'emoji' => $this->getRandomEmoji($agent),
            ], '发言成功');

        } catch (\Throwable $e) {
            $this->log('error', 'Debate agent failed', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * 流式执行辩论任务（使用 streamChat）
     *
     * @param array $input
     * @param callable $onChunk 每个chunk的回调
     * @return array
     */
    public function executeStream(array $input, callable $onChunk): array
    {
        try {
            $this->validateInput($input, ['topic', 'agent_id']);

            $topic = $input['topic'];
            $agentId = $input['agent_id'];
            $context = $input['context'] ?? [];

            $agent = $this->getAgentConfig($agentId);
            if (!$agent) {
                return $this->error("未知的Agent: {$agentId}");
            }

            $prompt = $this->buildDebatePrompt($topic, $context);
            $fullContent = '';

            // 流式调用
            $this->router->streamChat(
                $this->config['model'] ?? 'deepseek-chat',
                [
                    $this->buildSystemPrompt($agent['system_prompt']),
                    $this->buildUserMessage($prompt),
                ],
                function ($chunk) use ($agentId, $onChunk, &$fullContent) {
                    $content = $chunk['choices'][0]['delta']['content'] ?? '';
                    if ($content) {
                        $fullContent .= $content;
                        $onChunk($agentId, $content);
                    }
                },
                [
                    'temperature' => $agent['temperature'] ?? 0.8,
                    'max_tokens' => $agent['max_tokens'] ?? 400,
                ]
            );

            // 记录历史
            $this->conversationHistory[] = [
                'agent_id' => $agentId,
                'agent_name' => $agent['name'],
                'content' => $fullContent,
                'time' => time(),
            ];

            return $this->success([
                'agent_id' => $agentId,
                'agent_name' => $agent['name'],
                'content' => $fullContent,
            ], '流式发言成功');

        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 生成总结
     *
     * @param string $topic 辩论主题
     * @param array|null $history 对话历史（为空则使用内部历史）
     * @return array
     */
    public function generateSummary(string $topic, ?array $history = null): array
    {
        try {
            $history = $history ?? $this->conversationHistory;

            if (empty($history)) {
                return $this->error('没有对话记录可供总结');
            }

            $summaryAgent = $this->agentConfig['summarizer'] ?? [
                'system_prompt' => '你是辩论总结专家，擅长综合各方观点。',
                'temperature' => 0.7,
                'max_tokens' => 500,
            ];

            $prompt = "【辩论主题】{$topic}\n\n【各方观点】\n";
            foreach ($history as $item) {
                $prompt .= "\n{$item['agent_name']}：{$item['content']}\n";
            }
            $prompt .= "\n请综合总结：1.各方核心观点 2.不同视角价值 3.建设性结论（200字以内，中文）";

            $response = $this->router->chat(
                $this->config['model'] ?? 'deepseek-chat',
                [
                    $this->buildSystemPrompt($summaryAgent['system_prompt']),
                    $this->buildUserMessage($prompt),
                ],
                [
                    'temperature' => $summaryAgent['temperature'],
                    'max_tokens' => $summaryAgent['max_tokens'],
                ]
            );

            $content = $response['choices'][0]['message']['content'] ?? '';

            return $this->success([
                'summary' => $content,
            ], '总结生成成功');

        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 流式生成总结
     *
     * @param string $topic
     * @param callable $onChunk
     * @param array|null $history
     * @return array
     */
    public function generateSummaryStream(string $topic, callable $onChunk, ?array $history = null): array
    {
        try {
            $history = $history ?? $this->conversationHistory;

            if (empty($history)) {
                return $this->error('没有对话记录可供总结');
            }

            $summaryAgent = $this->agentConfig['summarizer'] ?? [
                'system_prompt' => '你是辩论总结专家，擅长综合各方观点。',
                'temperature' => 0.7,
                'max_tokens' => 500,
            ];

            $prompt = "【辩论主题】{$topic}\n\n【各方观点】\n";
            foreach ($history as $item) {
                $prompt .= "\n{$item['agent_name']}：{$item['content']}\n";
            }
            $prompt .= "\n请综合总结：1.各方核心观点 2.不同视角价值 3.建设性结论（200字以内，中文）";

            $fullContent = '';

            $this->router->streamChat(
                $this->config['model'] ?? 'deepseek-chat',
                [
                    $this->buildSystemPrompt($summaryAgent['system_prompt']),
                    $this->buildUserMessage($prompt),
                ],
                function ($chunk) use ($onChunk, &$fullContent) {
                    $content = $chunk['choices'][0]['delta']['content'] ?? '';
                    if ($content) {
                        $fullContent .= $content;
                        $onChunk('summary', $content);
                    }
                },
                [
                    'temperature' => $summaryAgent['temperature'],
                    'max_tokens' => $summaryAgent['max_tokens'],
                ]
            );

            return $this->success(['summary' => $fullContent], '总结生成成功');

        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 获取对话历史
     *
     * @return array
     */
    public function getHistory(): array
    {
        return $this->conversationHistory;
    }

    /**
     * 清空对话历史
     *
     * @return self
     */
    public function clearHistory(): self
    {
        $this->conversationHistory = [];
        return $this;
    }

    /**
     * 添加外部消息到历史
     *
     * @param string $agentId
     * @param string $agentName
     * @param string $content
     * @return self
     */
    public function addToHistory(string $agentId, string $agentName, string $content): self
    {
        $this->conversationHistory[] = [
            'agent_id' => $agentId,
            'agent_name' => $agentName,
            'content' => $content,
            'time' => time(),
        ];
        return $this;
    }

    /**
     * 构建辩论提示词
     *
     * @param string $topic
     * @param array $context
     * @return string
     */
    private function buildDebatePrompt(string $topic, array $context): string
    {
        $prompt = "【辩论主题】{$topic}\n\n";

        if (!empty($context)) {
            $prompt .= "【之前的发言】\n";
            foreach ($context as $prevAgent => $prevResponse) {
                $prompt .= "• {$prevAgent}: {$prevResponse}\n\n";
            }
        }

        $prompt .= "请从你的角色出发，发表你的观点：";

        return $prompt;
    }

    /**
     * 获取随机表情
     *
     * @param array $agent
     * @return string
     */
    private function getRandomEmoji(array $agent): string
    {
        $emojis = $agent['emoji'] ?? ['💬'];
        return $emojis[array_rand($emojis)];
    }
}
