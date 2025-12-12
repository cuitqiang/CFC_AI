<?php
declare(strict_types=1);

namespace Services\AI\Tools\System;

use Services\AI\Tools\BaseTool;

/**
 * 数据库读取工具
 * 允许 AI 查询数据库（只读）
 */
class DatabaseReader extends BaseTool
{
    private ?object $database;

    public function __construct(?object $database = null)
    {
        $this->database = $database;

        $this->name = 'database_reader';
        $this->description = '查询数据库获取数据（只读，不允许修改）';
        $this->parameters = [
            'type' => 'object',
            'properties' => [
                'table' => [
                    'type' => 'string',
                    'description' => '表名',
                ],
                'conditions' => [
                    'type' => 'object',
                    'description' => '查询条件（键值对）',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => '返回记录数限制',
                    'default' => 10,
                ],
                'fields' => [
                    'type' => 'array',
                    'description' => '要查询的字段列表',
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => ['table'],
        ];
    }

    public function execute(array $arguments): array
    {
        try {
            $this->validateArguments($arguments);

            $table = $arguments['table'];
            $conditions = $arguments['conditions'] ?? [];
            $limit = $arguments['limit'] ?? 10;
            $fields = $arguments['fields'] ?? ['*'];

            // 安全检查：防止危险操作
            if (!$this->isSafeTable($table)) {
                return $this->error('不允许访问该表');
            }

            // 执行查询
            $results = $this->query($table, $conditions, $fields, $limit);

            return $this->success($results, "查询到 " . count($results) . " 条记录");

        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    private function isSafeTable(string $table): bool
    {
        // 禁止访问敏感表
        $forbiddenTables = [
            'users_passwords',
            'admin_tokens',
            'api_keys',
            'system_config',
        ];

        return !in_array($table, $forbiddenTables, true);
    }

    private function query(string $table, array $conditions, array $fields, int $limit): array
    {
        // TODO: 实际项目中应使用真实的数据库连接
        // 现在返回模拟数据

        if ($this->database === null) {
            throw new \RuntimeException('数据库未配置');
        }

        // 模拟查询
        return [
            [
                'id' => 1,
                'name' => '示例数据',
                'created_at' => date('Y-m-d H:i:s'),
            ]
        ];
    }
}
