<?php
declare(strict_types=1);

namespace Services\AI\Tools\Business;

use Services\AI\Tools\BaseTool;

/**
 * 合同查找工具
 * 在 CRM 系统中查找合同信息
 */
class ContractFinder extends BaseTool
{
    private ?object $contractRepository;

    public function __construct(?object $contractRepository = null)
    {
        $this->contractRepository = $contractRepository;

        $this->name = 'contract_finder';
        $this->description = '在 CRM 系统中查找合同';
        $this->parameters = [
            'type' => 'object',
            'properties' => [
                'query_type' => [
                    'type' => 'string',
                    'description' => '查询类型',
                    'enum' => ['by_id', 'by_client', 'by_status', 'by_date_range'],
                ],
                'contract_id' => [
                    'type' => 'string',
                    'description' => '合同编号',
                ],
                'client_name' => [
                    'type' => 'string',
                    'description' => '客户名称',
                ],
                'status' => [
                    'type' => 'string',
                    'description' => '合同状态',
                    'enum' => ['draft', 'active', 'expired', 'terminated'],
                ],
                'start_date' => [
                    'type' => 'string',
                    'description' => '开始日期',
                ],
                'end_date' => [
                    'type' => 'string',
                    'description' => '结束日期',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => '返回数量限制',
                    'default' => 10,
                ],
            ],
            'required' => ['query_type'],
        ];
    }

    public function execute(array $arguments): array
    {
        try {
            $this->validateArguments($arguments);

            $queryType = $arguments['query_type'];

            $contracts = match ($queryType) {
                'by_id' => $this->findById($arguments),
                'by_client' => $this->findByClient($arguments),
                'by_status' => $this->findByStatus($arguments),
                'by_date_range' => $this->findByDateRange($arguments),
                default => throw new \InvalidArgumentException('不支持的查询类型'),
            };

            return $this->success($contracts, "找到 " . count($contracts) . " 份合同");

        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    private function findById(array $arguments): array
    {
        // TODO: 实际项目中从数据库查询
        $contractId = $arguments['contract_id'] ?? null;

        if (!$contractId) {
            throw new \InvalidArgumentException('缺少合同编号');
        }

        return [
            [
                'id' => $contractId,
                'client_name' => '示例客户',
                'amount' => 100000,
                'status' => 'active',
                'start_date' => '2024-01-01',
                'end_date' => '2024-12-31',
            ]
        ];
    }

    private function findByClient(array $arguments): array
    {
        // TODO: 实际项目中从数据库查询
        $clientName = $arguments['client_name'] ?? null;
        $limit = $arguments['limit'] ?? 10;

        return [];
    }

    private function findByStatus(array $arguments): array
    {
        // TODO: 实际项目中从数据库查询
        $status = $arguments['status'] ?? null;
        $limit = $arguments['limit'] ?? 10;

        return [];
    }

    private function findByDateRange(array $arguments): array
    {
        // TODO: 实际项目中从数据库查询
        $startDate = $arguments['start_date'] ?? null;
        $endDate = $arguments['end_date'] ?? null;
        $limit = $arguments['limit'] ?? 10;

        return [];
    }
}
