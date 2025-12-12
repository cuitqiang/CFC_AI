<?php
declare(strict_types=1);

namespace Services\AI\Debate;

use Services\AI\Bootstrap;
use Services\AI\Core\ModelRouter;
use App\Core\SSEResponse;
use Services\AI\Tasks\DebateAgent;
use App\Bootstrap\App;

/**
 * 辩论服务类
 * 
 * CFC V7.7 规范：
 * - 负责辩论业务逻辑：Agent 调度、对话管理、AI 调用
 * - 使用 SSEResponse 静态类处理流式输出
 * - 配置从 Config 目录加载（禁止硬编码）
 */
class DebateService
{
    private ModelRouter $router;
    private DebateAgent $debateAgent;
    private array $agentsConfig;
    private array $defaults;
    private string $mode;

    /**
     * 构造函数
     *
     * @param string $mode 模式：debate | chat
     */
    public function __construct(string $mode = 'chat')
    {
        $this->mode = $mode;
        $this->loadConfig();
        $this->initializeComponents();
    }

    /**
     * 加载配置（从 Config 目录，禁止硬编码）
     */
    private function loadConfig(): void
    {
        // 优先使用 App::config（如果 Bootstrap 已初始化）
        if (class_exists(App::class) && defined('APP_ROOT')) {
            $config = App::config('agents', []);
            if (!empty($config)) {
                $this->agentsConfig = $config[$this->mode] ?? $config['chat'] ?? [];
                $this->defaults = $config['defaults'] ?? [];
                
                // 合并特殊角色
                if (isset($config['special'])) {
                    $this->agentsConfig = array_merge($this->agentsConfig, $config['special']);
                }
                return;
            }
        }

        // 回退：查找配置文件
        $possiblePaths = [
            (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 4)) . '/src/Config/agents.php',
            dirname(__DIR__, 3) . '/Config/agents.php',
            dirname(__DIR__, 4) . '/config/debate_agents.php',
        ];

        $configFile = null;
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $configFile = $path;
                break;
            }
        }

        if (!$configFile) {
            throw new \RuntimeException("配置文件不存在，尝试的路径: " . implode(', ', $possiblePaths));
        }

        $config = require $configFile;
        $this->agentsConfig = $config[$this->mode] ?? $config['chat'] ?? [];
        $this->defaults = $config['defaults'] ?? [];

        // 合并特殊角色
        if (isset($config['special'])) {
            $this->agentsConfig = array_merge($this->agentsConfig, $config['special']);
        }
    }

    /**
     * 初始化组件
     */
    private function initializeComponents(): void
    {
        // 初始化 AI 系统
        Bootstrap::initialize();
        $this->router = Bootstrap::getModelRouter();

        // 初始化 DebateAgent
        $this->debateAgent = new DebateAgent(Bootstrap::getAIManager(), [
            'model' => $this->defaults['model'] ?? 'deepseek-chat',
        ]);
        $this->debateAgent->setRouter($this->router);
        $this->debateAgent->setAgentConfig($this->agentsConfig);
    }

    /**
     * 获取所有 Agent ID 列表（排除主持人和总结专家）
     */
    public function getAgentIds(): array
    {
        return array_keys(array_filter($this->agentsConfig, function ($agent) {
            return isset($agent['id']) 
                && ($agent['role'] ?? '') !== 'moderator' 
                && ($agent['role'] ?? '') !== 'summarizer';
        }));
    }

    /**
     * 获取 Agent 配置
     */
    public function getAgent(string $agentId): ?array
    {
        return $this->agentsConfig[$agentId] ?? null;
    }

    /**
     * 运行辩论（主入口）
     * 
     * @param string $topic 辩论主题
     * @param array|null $agentIds 指定的 Agent（为空则使用全部）
     */
    public function run(string $topic, ?array $agentIds = null): void
    {
        // 获取参与的 Agents
        $agentIds = $agentIds ?? $this->getAgentIds();
        $agentCount = count($agentIds);

        // 发送开始事件
        SSEResponse::send('start', [
            'topic' => $topic,
            'agent_count' => $agentCount,
            'mode' => $this->mode,
        ]);

        $allResponses = [];

        // 逐个 Agent 发言
        foreach ($agentIds as $index => $agentId) {
            if (SSEResponse::isDisconnected()) {
                break;
            }

            $agent = $this->getAgent($agentId);
            if (!$agent) {
                continue;
            }

            $progress = (int) ((($index + 1) / ($agentCount + 1)) * 80);

            // 发送思考状态
            SSEResponse::thinking($agentId, $agent['name']);

            // 流式执行
            $result = $this->debateAgent->executeStream(
                [
                    'topic' => $topic,
                    'agent_id' => $agentId,
                    'context' => $allResponses,
                ],
                function ($aid, $content) {
                    SSEResponse::chunk($aid, htmlspecialchars($content, ENT_QUOTES, 'UTF-8'));
                }
            );

            if ($result['success']) {
                $allResponses[$agent['name']] = $result['data']['content'];
                SSEResponse::complete($agentId);
            } else {
                SSEResponse::error("Agent {$agent['name']} 发言失败");
            }

            // 短暂停顿
            usleep(300000);
        }

        // 生成总结
        if (!SSEResponse::isDisconnected() && !empty($allResponses)) {
            $this->generateSummary($topic);
        }
    }

    /**
     * 生成总结（流式）
     */
    private function generateSummary(string $topic): void
    {
        SSEResponse::thinking('summary', '总结专家');

        $this->debateAgent->generateSummaryStream(
            $topic,
            function ($agentId, $content) {
                SSEResponse::chunk('summary', htmlspecialchars($content, ENT_QUOTES, 'UTF-8'));
            }
        );

        SSEResponse::complete('summary');
    }

    /**
     * 添加用户消息（预留接口）
     */
    public function addUserMessage(string $sessionId, string $message): void
    {
        // TODO: 实现用户消息队列
        // 可以使用 Redis 或文件队列存储用户消息
    }
}
