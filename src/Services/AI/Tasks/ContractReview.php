<?php
declare(strict_types=1);

namespace Services\AI\Tasks;

/**
 * 合同审查任务
 * 自动审查合同内容，发现风险和问题
 */
class ContractReview extends BaseTask
{
    protected function initialize(): void
    {
        $this->name = 'contract_review';
        $this->description = '自动审查合同，识别风险条款和潜在问题';
    }

    /**
     * 执行合同审查
     *
     * @param array $input ['contract_text' => '合同内容', 'contract_type' => '合同类型']
     * @return array 审查结果
     */
    public function execute(array $input): array
    {
        try {
            $this->validateInput($input, ['contract_text']);

            $contractText = $input['contract_text'];
            $contractType = $input['contract_type'] ?? 'general';

            $this->log('info', 'Starting contract review', ['type' => $contractType]);

            // 构建审查提示
            $prompt = $this->buildReviewPrompt($contractText, $contractType);

            // 调用 AI 进行审查
            $metadata = [
                'user_id' => $input['user_id'] ?? 'system',
                'model' => $this->config['model'] ?? 'deepseek-chat',
            ];

            $response = $this->callAI($prompt, $metadata);

            if (!$response['success']) {
                return $this->error($response['error'] ?? '审查失败');
            }

            // 解析审查结果
            $reviewResult = $this->parseReviewResult($response['message']);

            $this->log('info', 'Contract review completed', [
                'risk_level' => $reviewResult['risk_level'] ?? 'unknown'
            ]);

            return $this->success($reviewResult, '合同审查完成');

        } catch (\Throwable $e) {
            $this->log('error', 'Contract review failed', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * 构建审查提示
     */
    private function buildReviewPrompt(string $contractText, string $contractType): string
    {
        $typeInstructions = match ($contractType) {
            'sales' => '这是一份销售合同，请特别关注付款条款、交付时间、违约责任等。',
            'service' => '这是一份服务合同，请特别关注服务范围、服务标准、验收条件等。',
            'employment' => '这是一份劳动合同，请特别关注薪资福利、工作时间、保密条款等。',
            default => '请全面审查这份合同。',
        };

        return <<<PROMPT
你是一位专业的合同审查律师。请仔细审查以下合同内容，并提供详细的审查意见。

{$typeInstructions}

请按以下格式输出：

## 风险等级
[高/中/低]

## 主要风险点
1. [风险描述]
2. [风险描述]
...

## 不合理条款
1. [条款内容] - [问题说明]
2. [条款内容] - [问题说明]
...

## 缺失条款
1. [建议补充的条款]
2. [建议补充的条款]
...

## 修改建议
1. [具体修改建议]
2. [具体修改建议]
...

## 总体评价
[综合评价]

---

合同内容：

{$contractText}
PROMPT;
    }

    /**
     * 解析审查结果
     */
    private function parseReviewResult(string $aiResponse): array
    {
        $result = [
            'raw_response' => $aiResponse,
            'risk_level' => 'medium',
            'risk_points' => [],
            'unfair_clauses' => [],
            'missing_clauses' => [],
            'suggestions' => [],
            'summary' => '',
        ];

        // 提取风险等级
        if (preg_match('/##\s*风险等级\s*\n\s*\[?([高中低])\]?/u', $aiResponse, $matches)) {
            $result['risk_level'] = match ($matches[1]) {
                '高' => 'high',
                '中' => 'medium',
                '低' => 'low',
                default => 'medium',
            };
        }

        // 提取主要风险点
        if (preg_match('/##\s*主要风险点\s*\n(.*?)(?=##|$)/su', $aiResponse, $matches)) {
            $result['risk_points'] = $this->extractListItems($matches[1]);
        }

        // 提取不合理条款
        if (preg_match('/##\s*不合理条款\s*\n(.*?)(?=##|$)/su', $aiResponse, $matches)) {
            $result['unfair_clauses'] = $this->extractListItems($matches[1]);
        }

        // 提取缺失条款
        if (preg_match('/##\s*缺失条款\s*\n(.*?)(?=##|$)/su', $aiResponse, $matches)) {
            $result['missing_clauses'] = $this->extractListItems($matches[1]);
        }

        // 提取修改建议
        if (preg_match('/##\s*修改建议\s*\n(.*?)(?=##|$)/su', $aiResponse, $matches)) {
            $result['suggestions'] = $this->extractListItems($matches[1]);
        }

        // 提取总体评价
        if (preg_match('/##\s*总体评价\s*\n(.*?)$/su', $aiResponse, $matches)) {
            $result['summary'] = trim($matches[1]);
        }

        return $result;
    }

    /**
     * 提取列表项
     */
    private function extractListItems(string $text): array
    {
        $items = [];
        $lines = explode("\n", trim($text));

        foreach ($lines as $line) {
            $line = trim($line);

            if (preg_match('/^\d+\.\s*(.+)$/u', $line, $matches)) {
                $items[] = trim($matches[1]);
            }
        }

        return $items;
    }
}
