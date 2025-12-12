<?php
declare(strict_types=1);

namespace Services\AI\Tasks;

/**
 * 通用 AI 代理
 * 处理各种常规对话和查询
 */
class GeneralAgent extends BaseTask
{
    protected function initialize(): void
    {
        $this->name = 'general_agent';
        $this->description = '通用 AI 助手，处理常规对话、问答和任务';
    }

    /**
     * 执行通用代理任务
     *
     * @param array $input ['query' => '用户问题', 'context' => [...]]
     * @return array 执行结果
     */
    public function execute(array $input): array
    {
        try {
            $this->validateInput($input, ['query']);

            $query = $input['query'];
            $context = $input['context'] ?? [];
            $userId = $input['user_id'] ?? 'anonymous';

            $this->log('info', 'Processing general query', ['query' => $query]);

            // 构建元数据
            $metadata = [
                'user_id' => $userId,
                'session_id' => $input['session_id'] ?? null,
                'model' => $this->config['model'] ?? 'deepseek-chat',
                'include_metadata' => $this->config['include_metadata'] ?? false,
            ];

            // 如果有上下文，添加到查询中
            if (!empty($context)) {
                $query = $this->enrichQueryWithContext($query, $context);
            }

            // 调用 AI
            $response = $this->callAI($query, $metadata);

            if (!$response['success']) {
                return $this->error($response['error'] ?? '处理失败');
            }

            $this->log('info', 'Query processed successfully');

            return $this->success([
                'answer' => $response['message'],
                'metadata' => $response['metadata'] ?? null,
            ], '查询成功');

        } catch (\Throwable $e) {
            $this->log('error', 'Failed to process query', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * 用上下文丰富查询
     */
    private function enrichQueryWithContext(string $query, array $context): string
    {
        if (empty($context)) {
            return $query;
        }

        $contextStr = "参考信息：\n";

        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            $contextStr .= "- {$key}: {$value}\n";
        }

        return $contextStr . "\n用户问题：" . $query;
    }

    /**
     * 批量处理查询
     *
     * @param array $queries 查询列表
     * @return array 结果列表
     */
    public function executeBatch(array $queries): array
    {
        $results = [];

        foreach ($queries as $query) {
            $results[] = $this->execute(['query' => $query]);
        }

        return $results;
    }

    /**
     * 对话模式（多轮对话）
     *
     * @param string $query 用户输入
     * @param string $sessionId 会话ID
     * @param string $userId 用户ID
     * @return array 响应
     */
    public function chat(string $query, string $sessionId, string $userId = 'anonymous'): array
    {
        return $this->execute([
            'query' => $query,
            'session_id' => $sessionId,
            'user_id' => $userId,
        ]);
    }
}
