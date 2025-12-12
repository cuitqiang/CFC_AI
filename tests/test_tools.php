<?php
/**
 * 工具系统（Tools）完整测试
 * 测试 BaseTool、ToolRegistry、ToolSandbox
 */

require_once __DIR__ . '/vendor/autoload.php';

use Services\AI\Tools\BaseTool;
use Services\AI\Tools\ToolRegistry;
use Services\AI\Tools\ToolSandbox;
use Services\AI\Tools\System\TimeCalculator;
use Services\AI\Tools\System\DatabaseReader;

echo "========================================\n";
echo "工具系统（Tools）完整测试\n";
echo "========================================\n\n";

echo "🛠️  什么是工具系统？\n";
echo "-----------------------------------\n";
echo "工具系统让 AI 能够执行实际操作：\n";
echo "  • 查询数据库\n";
echo "  • 计算时间\n";
echo "  • 发送邮件\n";
echo "  • 搜索网络\n";
echo "  • 生成报告\n\n";

// ========================================
// 测试1: 创建自定义工具
// ========================================
echo "【测试1】创建自定义工具\n";
echo "-----------------------------------\n";

class CalculatorTool extends BaseTool
{
    public function __construct()
    {
        $this->name = 'calculator';
        $this->description = '执行基本数学运算';
        $this->parameters = [
            'type' => 'object',
            'properties' => [
                'operation' => [
                    'type' => 'string',
                    'description' => '运算类型',
                    'enum' => ['add', 'subtract', 'multiply', 'divide'],
                ],
                'a' => [
                    'type' => 'number',
                    'description' => '第一个数',
                ],
                'b' => [
                    'type' => 'number',
                    'description' => '第二个数',
                ],
            ],
            'required' => ['operation', 'a', 'b'],
        ];
    }

    public function execute(array $arguments): array
    {
        try {
            $this->validateArguments($arguments);

            $a = $arguments['a'];
            $b = $arguments['b'];
            $op = $arguments['operation'];

            $result = match ($op) {
                'add' => $a + $b,
                'subtract' => $a - $b,
                'multiply' => $a * $b,
                'divide' => $b != 0 ? $a / $b : throw new \DivisionByZeroError('除数不能为0'),
                default => throw new \InvalidArgumentException('不支持的运算'),
            };

            return $this->success([
                'result' => $result,
                'operation' => "$a $op $b = $result",
            ]);

        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }
}

echo "创建 CalculatorTool...\n";
$calculator = new CalculatorTool();
echo "  名称: " . $calculator->getName() . "\n";
echo "  描述: " . $calculator->getDescription() . "\n";
echo "  参数: " . count($calculator->getParameters()['properties']) . " 个\n\n";

echo "✅ 自定义工具创建测试完成\n\n";

// ========================================
// 测试2: 工具定义（Function Calling 格式）
// ========================================
echo "【测试2】工具定义（Function Calling 格式）\n";
echo "-----------------------------------\n";

echo "获取工具定义...\n";
$definition = $calculator->getDefinition();

echo "\n定义结构:\n";
echo "  type: {$definition['type']}\n";
echo "  function.name: {$definition['function']['name']}\n";
echo "  function.description: {$definition['function']['description']}\n";
echo "  参数数量: " . count($definition['function']['parameters']['properties']) . "\n\n";

echo "完整定义（JSON格式）:\n";
echo json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "✅ 工具定义测试完成\n\n";

// ========================================
// 测试3: 工具执行
// ========================================
echo "【测试3】工具执行\n";
echo "-----------------------------------\n";

$testCases = [
    ['operation' => 'add', 'a' => 10, 'b' => 5],
    ['operation' => 'subtract', 'a' => 10, 'b' => 5],
    ['operation' => 'multiply', 'a' => 10, 'b' => 5],
    ['operation' => 'divide', 'a' => 10, 'b' => 5],
];

echo "执行计算测试...\n\n";
foreach ($testCases as $testCase) {
    $result = $calculator->execute($testCase);

    echo "  {$testCase['operation']}: {$testCase['a']} 和 {$testCase['b']}\n";
    echo "    结果: " . ($result['success'] ? $result['data']['result'] : 'ERROR') . "\n";
    echo "    状态: " . ($result['success'] ? '✓ 成功' : '✗ 失败') . "\n\n";
}

echo "✅ 工具执行测试完成\n\n";

// ========================================
// 测试4: 参数验证
// ========================================
echo "【测试4】参数验证\n";
echo "-----------------------------------\n";

echo "测试缺少必需参数...\n";
$invalidArgs = ['operation' => 'add', 'a' => 10];  // 缺少 'b'
$result = $calculator->execute($invalidArgs);

echo "  结果: " . ($result['success'] ? '✗ 应该失败但成功了' : '✓ 正确拒绝') . "\n";
echo "  错误信息: " . ($result['error'] ?? 'N/A') . "\n\n";

echo "测试除以零...\n";
$divByZero = ['operation' => 'divide', 'a' => 10, 'b' => 0];
$result = $calculator->execute($divByZero);

echo "  结果: " . ($result['success'] ? '✗ 应该失败' : '✓ 正确拒绝') . "\n";
echo "  错误信息: " . ($result['error'] ?? 'N/A') . "\n\n";

echo "✅ 参数验证测试完成\n\n";

// ========================================
// 测试5: 工具注册表
// ========================================
echo "【测试5】工具注册表\n";
echo "-----------------------------------\n";

$registry = new ToolRegistry();

echo "注册工具...\n";
$registry->register($calculator);
$registry->register(new TimeCalculator());

echo "  已注册: " . $registry->count() . " 个工具\n";
echo "  工具列表:\n";
foreach ($registry->all() as $name => $tool) {
    echo "    • {$name}\n";
}

echo "\n✅ 工具注册测试完成\n\n";

// ========================================
// 测试6: 工具查询
// ========================================
echo "【测试6】工具查询\n";
echo "-----------------------------------\n";

echo "检查工具是否存在...\n";
echo "  calculator: " . ($registry->has('calculator') ? '✓ 存在' : '✗ 不存在') . "\n";
echo "  time_calculator: " . ($registry->has('time_calculator') ? '✓ 存在' : '✗ 不存在') . "\n";
echo "  nonexistent: " . ($registry->has('nonexistent') ? '✗ 存在' : '✓ 不存在') . "\n\n";

echo "获取工具实例...\n";
$tool = $registry->get('calculator');
echo "  calculator: " . ($tool ? '✓ 获取成功' : '✗ 获取失败') . "\n";

$nullTool = $registry->get('nonexistent');
echo "  nonexistent: " . ($nullTool === null ? '✓ 返回 null' : '✗ 未返回 null') . "\n\n";

echo "✅ 工具查询测试完成\n\n";

// ========================================
// 测试7: 批量注册
// ========================================
echo "【测试7】批量注册\n";
echo "-----------------------------------\n";

$registry2 = new ToolRegistry();

class StringTool extends BaseTool
{
    public function __construct()
    {
        $this->name = 'string_tool';
        $this->description = '字符串操作';
        $this->parameters = ['type' => 'object', 'properties' => []];
    }

    public function execute(array $arguments): array
    {
        return $this->success(['result' => 'ok']);
    }
}

class DateTool extends BaseTool
{
    public function __construct()
    {
        $this->name = 'date_tool';
        $this->description = '日期操作';
        $this->parameters = ['type' => 'object', 'properties' => []];
    }

    public function execute(array $arguments): array
    {
        return $this->success(['result' => 'ok']);
    }
}

$tools = [
    new CalculatorTool(),
    new StringTool(),
    new DateTool(),
];

echo "批量注册 " . count($tools) . " 个工具...\n";
$registry2->registerMany($tools);

echo "  已注册: " . $registry2->count() . " 个\n";
echo "  工具列表:\n";
foreach ($registry2->all() as $name => $tool) {
    echo "    • {$name}\n";
}

echo "\n✅ 批量注册测试完成\n\n";

// ========================================
// 测试8: 通过注册表执行工具
// ========================================
echo "【测试8】通过注册表执行工具\n";
echo "-----------------------------------\n";

echo "通过注册表执行 calculator...\n";
$result = $registry->execute('calculator', [
    'operation' => 'multiply',
    'a' => 6,
    'b' => 7,
]);

echo "  6 × 7 = " . ($result['data']['result'] ?? 'ERROR') . "\n";
echo "  状态: " . ($result['success'] ? '✓ 成功' : '✗ 失败') . "\n\n";

echo "执行不存在的工具...\n";
$result = $registry->execute('nonexistent', []);
echo "  状态: " . ($result['success'] ? '✗ 应该失败' : '✓ 正确拒绝') . "\n";
echo "  错误: " . ($result['error'] ?? 'N/A') . "\n\n";

echo "✅ 注册表执行测试完成\n\n";

// ========================================
// 测试9: 获取所有工具定义
// ========================================
echo "【测试9】获取所有工具定义\n";
echo "-----------------------------------\n";

echo "获取所有工具的 Function Calling 定义...\n";
$allDefinitions = $registry->getAllDefinitions();

echo "  总数: " . count($allDefinitions) . " 个\n";
echo "  工具列表:\n";
foreach ($allDefinitions as $def) {
    echo "    • {$def['function']['name']}: {$def['function']['description']}\n";
}

echo "\n✅ 获取定义测试完成\n\n";

// ========================================
// 测试10: 工具沙箱
// ========================================
echo "【测试10】工具沙箱\n";
echo "-----------------------------------\n";

$sandbox = new ToolSandbox(
    allowedFunctions: [],  // 允许所有
    maxExecutionTime: 5,
    maxMemoryUsage: 50 * 1024 * 1024,
    enableLogging: true
);

echo "在沙箱中执行工具...\n";
$result = $sandbox->execute($calculator, [
    'operation' => 'add',
    'a' => 100,
    'b' => 200,
]);

echo "  结果: " . ($result['data']['result'] ?? 'ERROR') . "\n";
echo "  执行时间: " . round($result['_meta']['execution_time'] * 1000, 2) . " ms\n";
echo "  内存使用: " . round($result['_meta']['memory_used'] / 1024, 2) . " KB\n\n";

echo "✅ 工具沙箱测试完成\n\n";

// ========================================
// 测试11: 沙箱权限控制
// ========================================
echo "【测试11】沙箱权限控制\n";
echo "-----------------------------------\n";

$restrictedSandbox = new ToolSandbox(
    allowedFunctions: ['calculator'],  // 只允许 calculator
);

echo "检查工具权限...\n";
echo "  calculator: " . ($restrictedSandbox->isAllowed($calculator) ? '✓ 允许' : '✗ 禁止') . "\n";

$timeTool = new TimeCalculator();
echo "  time_calculator: " . ($restrictedSandbox->isAllowed($timeTool) ? '✗ 允许（应该禁止）' : '✓ 禁止') . "\n\n";

echo "动态添加权限...\n";
$restrictedSandbox->allow('time_calculator');
echo "  time_calculator: " . ($restrictedSandbox->isAllowed($timeTool) ? '✓ 现在允许' : '✗ 仍然禁止') . "\n\n";

echo "✅ 权限控制测试完成\n\n";

// ========================================
// 测试12: TimeCalculator 工具
// ========================================
echo "【测试12】TimeCalculator 工具\n";
echo "-----------------------------------\n";

$timeCalc = new TimeCalculator();

echo "测试1: 获取当前时间\n";
$result = $timeCalc->execute(['operation' => 'current']);
echo "  当前时间: " . $result['data']['formatted'] . "\n";
echo "  时区: " . $result['data']['timezone'] . "\n\n";

echo "测试2: 计算日期差\n";
$result = $timeCalc->execute([
    'operation' => 'diff',
    'start_date' => '2024-01-01',
    'end_date' => '2024-12-31',
]);
echo "  2024-01-01 到 2024-12-31\n";
echo "  相差: {$result['data']['days']} 天\n";
echo "  格式化: {$result['data']['formatted']}\n\n";

echo "测试3: 增加时间\n";
$result = $timeCalc->execute([
    'operation' => 'add',
    'start_date' => '2024-01-01',
    'amount' => 30,
    'unit' => 'days',
]);
echo "  2024-01-01 + 30 天\n";
echo "  结果: {$result['data']['result']}\n\n";

echo "测试4: 计算工作日\n";
$result = $timeCalc->execute([
    'operation' => 'workdays',
    'start_date' => '2024-01-01',
    'end_date' => '2024-01-31',
]);
echo "  2024年1月\n";
echo "  工作日: {$result['data']['workdays']} 天\n";
echo "  总天数: {$result['data']['total_days']} 天\n\n";

echo "✅ TimeCalculator 测试完成\n\n";

// ========================================
// 测试13: 工具移除和清空
// ========================================
echo "【测试13】工具移除和清空\n";
echo "-----------------------------------\n";

$registry3 = new ToolRegistry();
$registry3->registerMany([
    new CalculatorTool(),
    new StringTool(),
    new DateTool(),
]);

echo "初始工具数: " . $registry3->count() . "\n";

echo "\n移除 string_tool...\n";
$registry3->unregister('string_tool');
echo "  剩余: " . $registry3->count() . " 个\n";

echo "\n清空所有工具...\n";
$registry3->clear();
echo "  剩余: " . $registry3->count() . " 个\n\n";

echo "✅ 移除和清空测试完成\n\n";

// ========================================
// 测试14: 错误处理
// ========================================
echo "【测试14】错误处理\n";
echo "-----------------------------------\n";

class FailingTool extends BaseTool
{
    public function __construct()
    {
        $this->name = 'failing_tool';
        $this->description = '总是失败的工具';
        $this->parameters = ['type' => 'object', 'properties' => []];
    }

    public function execute(array $arguments): array
    {
        throw new \RuntimeException('模拟工具执行失败');
    }
}

$failingTool = new FailingTool();
$registry4 = new ToolRegistry();
$registry4->register($failingTool);

echo "执行会失败的工具...\n";
$result = $registry4->execute('failing_tool', []);

echo "  成功状态: " . ($result['success'] ? 'true' : 'false') . "\n";
echo "  错误信息: " . ($result['error'] ?? 'N/A') . "\n\n";

echo "沙箱中执行失败工具...\n";
$result = $sandbox->execute($failingTool, []);

echo "  成功状态: " . ($result['success'] ? 'true' : 'false') . "\n";
echo "  错误信息: " . ($result['error'] ?? 'N/A') . "\n";
echo "  执行时间: " . round($result['_meta']['execution_time'] * 1000, 2) . " ms\n\n";

echo "✅ 错误处理测试完成\n\n";

// ========================================
// 测试15: 完整工作流
// ========================================
echo "【测试15】完整工作流演示\n";
echo "-----------------------------------\n";

echo "场景: AI 使用工具解决问题\n\n";

// 1. 注册工具
$registry5 = new ToolRegistry();
$registry5->registerMany([
    new CalculatorTool(),
    new TimeCalculator(),
]);

// 2. AI 获取可用工具列表
$availableTools = $registry5->getAllDefinitions();
echo "Step 1: 获取可用工具\n";
echo "  可用工具数: " . count($availableTools) . "\n\n";

// 3. AI 决定使用计算器
echo "Step 2: AI 决定使用 calculator 计算 15 × 8\n";
$calculationResult = $registry5->execute('calculator', [
    'operation' => 'multiply',
    'a' => 15,
    'b' => 8,
]);
echo "  计算结果: " . $calculationResult['data']['result'] . "\n\n";

// 4. AI 决定使用时间计算器
echo "Step 3: AI 使用 time_calculator 计算项目工期\n";
$workdaysResult = $registry5->execute('time_calculator', [
    'operation' => 'workdays',
    'start_date' => '2024-12-01',
    'end_date' => '2024-12-31',
]);
echo "  项目工期: " . $workdaysResult['data']['workdays'] . " 个工作日\n\n";

// 5. 在沙箱中安全执行
echo "Step 4: 在沙箱中执行（安全保障）\n";
$sandbox2 = new ToolSandbox();
$safeResult = $sandbox2->execute($calculator, [
    'operation' => 'divide',
    'a' => 1000,
    'b' => 8,
]);
echo "  结果: " . $safeResult['data']['result'] . "\n";
echo "  执行时间: " . round($safeResult['_meta']['execution_time'] * 1000, 2) . " ms\n";
echo "  内存使用: " . round($safeResult['_meta']['memory_used'] / 1024, 2) . " KB\n\n";

echo "✅ 完整工作流演示完成\n\n";

// ========================================
// 总结
// ========================================
echo "========================================\n";
echo "工具系统测试总结\n";
echo "========================================\n\n";

echo "✅ 已测试的功能:\n";
echo "  1. 创建自定义工具（继承 BaseTool）\n";
echo "  2. 工具定义（Function Calling 格式）\n";
echo "  3. 工具执行（execute）\n";
echo "  4. 参数验证（validateArguments）\n";
echo "  5. 工具注册（ToolRegistry.register）\n";
echo "  6. 工具查询（has, get）\n";
echo "  7. 批量注册（registerMany）\n";
echo "  8. 通过注册表执行（execute）\n";
echo "  9. 获取所有定义（getAllDefinitions）\n";
echo "  10. 工具沙箱（ToolSandbox）\n";
echo "  11. 权限控制（isAllowed, allow）\n";
echo "  12. TimeCalculator 工具测试\n";
echo "  13. 工具移除和清空\n";
echo "  14. 错误处理\n";
echo "  15. 完整工作流\n\n";

echo "🛠️  工具系统核心能力:\n";
echo "  ✅ 工具定义（符合 OpenAI Function Calling 规范）\n";
echo "  ✅ 工具注册和管理\n";
echo "  ✅ 安全执行（沙箱隔离）\n";
echo "  ✅ 参数验证\n";
echo "  ✅ 错误处理\n";
echo "  ✅ 权限控制\n";
echo "  ✅ 资源监控（时间、内存）\n\n";

echo "🏗️  工具系统架构:\n";
echo "  ┌─────────────────────────────┐\n";
echo "  │      ToolRegistry           │\n";
echo "  │    (工具注册中心)            │\n";
echo "  └──────────┬──────────────────┘\n";
echo "             │\n";
echo "     ┌───────┴────────┐\n";
echo "     ▼                ▼\n";
echo "  BaseTool        ToolSandbox\n";
echo "  (工具基类)      (安全沙箱)\n";
echo "     │                │\n";
echo "     ├─ Calculator    ├─ 执行时间限制\n";
echo "     ├─ TimeCalc      ├─ 内存限制\n";
echo "     ├─ DatabaseReader├─ 权限检查\n";
echo "     └─ Custom...     └─ 日志记录\n\n";

echo "💡 内置工具:\n";
echo "  • TimeCalculator - 时间计算（日期差、工作日等）\n";
echo "  • DatabaseReader - 数据库查询（只读）\n";
echo "  • HttpSearch - 网络搜索\n";
echo "  • ContractFinder - 合同查询\n";
echo "  • EmailSender - 邮件发送\n";
echo "  • ReportBuilder - 报告生成\n\n";

echo "🎯 应用场景:\n";
echo "  • AI 查询数据库获取信息\n";
echo "  • AI 执行数学计算\n";
echo "  • AI 计算时间和日期\n";
echo "  • AI 发送邮件通知\n";
echo "  • AI 生成数据报告\n\n";

echo "📝 使用示例:\n";
echo "```php\n";
echo "// 1. 创建工具\n";
echo "\$tool = new CalculatorTool();\n\n";
echo "// 2. 注册工具\n";
echo "\$registry = new ToolRegistry();\n";
echo "\$registry->register(\$tool);\n\n";
echo "// 3. 执行工具\n";
echo "\$result = \$registry->execute('calculator', [\n";
echo "    'operation' => 'add',\n";
echo "    'a' => 10,\n";
echo "    'b' => 5,\n";
echo "]);\n\n";
echo "// 4. 沙箱执行（更安全）\n";
echo "\$sandbox = new ToolSandbox();\n";
echo "\$result = \$sandbox->execute(\$tool, \$args);\n";
echo "```\n\n";

echo "========================================\n";
echo "✅ 所有工具系统测试完成！\n";
echo "========================================\n";
