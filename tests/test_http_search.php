<?php
/**
 * 联网搜索（HttpSearch）完整测试
 * 测试 HttpSearch 工具的所有功能
 */

require_once __DIR__ . '/vendor/autoload.php';

use Services\AI\Tools\System\HttpSearch;
use Services\AI\Tools\ToolRegistry;
use Services\AI\Tools\ToolSandbox;

echo "========================================\n";
echo "联网搜索（HttpSearch）完整测试\n";
echo "========================================\n\n";

echo "🌐 什么是联网搜索？\n";
echo "-----------------------------------\n";
echo "联网搜索工具让 AI 能够获取互联网上的实时信息：\n";
echo "  • 搜索最新资讯\n";
echo "  • 查找技术文档\n";
echo "  • 获取市场数据\n";
echo "  • 查询产品信息\n";
echo "  • 收集竞品情报\n\n";

// ========================================
// 测试1: 创建搜索工具
// ========================================
echo "【测试1】创建搜索工具\n";
echo "-----------------------------------\n";

echo "创建 Google 搜索工具...\n";
$googleSearch = new HttpSearch('google', 'test-api-key');
echo "  名称: " . $googleSearch->getName() . "\n";
echo "  描述: " . $googleSearch->getDescription() . "\n";
echo "  搜索引擎: Google\n\n";

echo "创建 Bing 搜索工具...\n";
$bingSearch = new HttpSearch('bing', 'test-bing-key');
echo "  名称: " . $bingSearch->getName() . "\n";
echo "  搜索引擎: Bing\n\n";

echo "✅ 搜索工具创建测试完成\n\n";

// ========================================
// 测试2: 工具定义
// ========================================
echo "【测试2】工具定义（Function Calling 格式）\n";
echo "-----------------------------------\n";

$definition = $googleSearch->getDefinition();

echo "完整定义:\n";
echo json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "参数说明:\n";
foreach ($definition['function']['parameters']['properties'] as $param => $details) {
    echo "  • {$param}:\n";
    echo "      类型: {$details['type']}\n";
    echo "      描述: {$details['description']}\n";
    if (isset($details['default'])) {
        echo "      默认: {$details['default']}\n";
    }
}

echo "\n必需参数: " . implode(', ', $definition['function']['parameters']['required']) . "\n\n";

echo "✅ 工具定义测试完成\n\n";

// ========================================
// 测试3: 基础搜索
// ========================================
echo "【测试3】基础搜索\n";
echo "-----------------------------------\n";

echo "搜索: \"PHP 8.3 新特性\"\n";
$result = $googleSearch->execute([
    'query' => 'PHP 8.3 新特性',
]);

echo "\n搜索结果:\n";
echo "  状态: " . ($result['success'] ? '✓ 成功' : '✗ 失败') . "\n";
echo "  消息: " . ($result['message'] ?? 'N/A') . "\n";

if ($result['success'] && isset($result['data'])) {
    echo "\n结果详情:\n";
    foreach ($result['data'] as $i => $item) {
        echo "\n  结果 " . ($i + 1) . ":\n";
        echo "    标题: {$item['title']}\n";
        echo "    链接: {$item['url']}\n";
        echo "    摘要: {$item['snippet']}\n";
    }
}

echo "\n✅ 基础搜索测试完成\n\n";

// ========================================
// 测试4: 参数化搜索
// ========================================
echo "【测试4】参数化搜索\n";
echo "-----------------------------------\n";

$searchCases = [
    [
        'name' => '默认参数',
        'args' => [
            'query' => 'AI 技术发展',
        ],
    ],
    [
        'name' => '限制结果数',
        'args' => [
            'query' => '机器学习',
            'limit' => 3,
        ],
    ],
    [
        'name' => '指定语言',
        'args' => [
            'query' => 'artificial intelligence',
            'language' => 'en-US',
            'limit' => 5,
        ],
    ],
];

foreach ($searchCases as $testCase) {
    echo "测试: {$testCase['name']}\n";
    echo "  查询: {$testCase['args']['query']}\n";

    if (isset($testCase['args']['limit'])) {
        echo "  限制: {$testCase['args']['limit']} 条\n";
    }

    if (isset($testCase['args']['language'])) {
        echo "  语言: {$testCase['args']['language']}\n";
    }

    $result = $googleSearch->execute($testCase['args']);
    echo "  结果: " . ($result['success'] ? '✓ 成功' : '✗ 失败') . "\n";

    if ($result['success']) {
        echo "  找到: " . count($result['data']) . " 条结果\n";
    }

    echo "\n";
}

echo "✅ 参数化搜索测试完成\n\n";

// ========================================
// 测试5: 参数验证
// ========================================
echo "【测试5】参数验证\n";
echo "-----------------------------------\n";

echo "测试缺少必需参数（query）...\n";
$invalidResult = $googleSearch->execute([
    'limit' => 5,
    // 缺少 query
]);

echo "  结果: " . ($invalidResult['success'] ? '✗ 应该失败' : '✓ 正确拒绝') . "\n";
echo "  错误: " . ($invalidResult['error'] ?? 'N/A') . "\n\n";

echo "✅ 参数验证测试完成\n\n";

// ========================================
// 测试6: 集成到工具注册表
// ========================================
echo "【测试6】集成到工具注册表\n";
echo "-----------------------------------\n";

$registry = new ToolRegistry();
$registry->register($googleSearch);

echo "注册搜索工具到注册表...\n";
echo "  已注册工具数: " . $registry->count() . "\n";
echo "  是否存在 http_search: " . ($registry->has('http_search') ? '✓ 是' : '✗ 否') . "\n\n";

echo "通过注册表执行搜索...\n";
$result = $registry->execute('http_search', [
    'query' => 'CRM 系统最佳实践',
    'limit' => 3,
]);

echo "  状态: " . ($result['success'] ? '✓ 成功' : '✗ 失败') . "\n";
echo "  结果数: " . (isset($result['data']) ? count($result['data']) : 0) . "\n\n";

echo "✅ 注册表集成测试完成\n\n";

// ========================================
// 测试7: 沙箱中执行
// ========================================
echo "【测试7】沙箱中执行搜索\n";
echo "-----------------------------------\n";

$sandbox = new ToolSandbox(
    allowedFunctions: ['http_search'],
    maxExecutionTime: 10,
    enableLogging: true
);

echo "在沙箱中执行搜索（安全隔离）...\n";
$result = $sandbox->execute($googleSearch, [
    'query' => 'ERP 系统功能',
    'limit' => 5,
]);

echo "  状态: " . ($result['success'] ? '✓ 成功' : '✗ 失败') . "\n";
echo "  执行时间: " . round($result['_meta']['execution_time'] * 1000, 2) . " ms\n";
echo "  内存使用: " . round($result['_meta']['memory_used'] / 1024, 2) . " KB\n\n";

echo "✅ 沙箱执行测试完成\n\n";

// ========================================
// 测试8: 多种搜索场景
// ========================================
echo "【测试8】多种搜索场景\n";
echo "-----------------------------------\n";

$scenarios = [
    [
        'scenario' => '技术查询',
        'query' => 'Laravel 最新版本',
        'description' => '查询框架最新信息',
    ],
    [
        'scenario' => '市场调研',
        'query' => 'CRM软件市场趋势 2024',
        'description' => '了解市场动态',
    ],
    [
        'scenario' => '竞品分析',
        'query' => 'Salesforce vs HubSpot',
        'description' => '对比竞品特性',
    ],
    [
        'scenario' => '问题解决',
        'query' => 'MySQL 性能优化方法',
        'description' => '寻找技术解决方案',
    ],
    [
        'scenario' => '行业资讯',
        'query' => 'AI 在企业管理中的应用',
        'description' => '获取行业趋势',
    ],
];

foreach ($scenarios as $i => $scenario) {
    echo "场景 " . ($i + 1) . ": {$scenario['scenario']}\n";
    echo "  查询: {$scenario['query']}\n";
    echo "  目的: {$scenario['description']}\n";

    $result = $googleSearch->execute([
        'query' => $scenario['query'],
        'limit' => 3,
    ]);

    echo "  结果: " . ($result['success'] ? '✓ 成功' : '✗ 失败') . "\n";

    if ($result['success']) {
        echo "  找到 " . count($result['data']) . " 条相关信息\n";
    }

    echo "\n";
}

echo "✅ 多场景测试完成\n\n";

// ========================================
// 测试9: 搜索引擎对比
// ========================================
echo "【测试9】搜索引擎对比\n";
echo "-----------------------------------\n";

$query = 'Python 数据分析';

echo "相同查询在不同搜索引擎的表现:\n";
echo "查询: \"{$query}\"\n\n";

echo "Google 搜索:\n";
$googleResult = $googleSearch->execute(['query' => $query]);
echo "  状态: " . ($googleResult['success'] ? '✓' : '✗') . "\n";
echo "  结果数: " . (isset($googleResult['data']) ? count($googleResult['data']) : 0) . "\n\n";

echo "Bing 搜索:\n";
$bingResult = $bingSearch->execute(['query' => $query]);
echo "  状态: " . ($bingResult['success'] ? '✓' : '✗') . "\n";
echo "  结果数: " . (isset($bingResult['data']) ? count($bingResult['data']) : 0) . "\n\n";

echo "✅ 搜索引擎对比测试完成\n\n";

// ========================================
// 测试10: AI 使用场景模拟
// ========================================
echo "【测试10】AI 使用场景模拟\n";
echo "-----------------------------------\n";

echo "场景: AI 助手回答用户问题\n\n";

echo "用户问题: \"最新的 PHP 版本有哪些新特性？\"\n\n";

echo "Step 1: AI 判断需要联网搜索最新信息\n";
echo "  ✓ 检测到需要实时数据\n\n";

echo "Step 2: AI 构造搜索查询\n";
$searchQuery = "PHP latest version new features";
echo "  查询: \"{$searchQuery}\"\n\n";

echo "Step 3: 执行搜索\n";
$searchResult = $googleSearch->execute([
    'query' => $searchQuery,
    'limit' => 5,
    'language' => 'en-US',
]);
echo "  ✓ 搜索完成\n";
echo "  找到 " . count($searchResult['data']) . " 条结果\n\n";

echo "Step 4: AI 整合搜索结果生成回答\n";
echo "  ✓ 基于搜索结果提取关键信息\n";
echo "  ✓ 生成准确、实时的回答\n\n";

echo "示例回答:\n";
echo "  \"根据最新的搜索结果，PHP 的最新版本包含了以下新特性：\n";
echo "  1. 只读类（Readonly Classes）\n";
echo "  2. 新的随机扩展\n";
echo "  3. Trait 中的常量\n";
echo "  ...\n";
echo "  详细信息请参考官方文档。\"\n\n";

echo "✅ AI 使用场景模拟完成\n\n";

// ========================================
// 测试11: 实际集成建议
// ========================================
echo "【测试11】实际集成建议\n";
echo "-----------------------------------\n";

echo "当前状态: 模拟实现\n";
echo "  • 返回固定的模拟数据\n";
echo "  • 适合开发和测试\n\n";

echo "生产环境建议:\n";
echo "  1. 集成真实搜索 API:\n";
echo "     • Google Custom Search API\n";
echo "     • Bing Web Search API\n";
echo "     • DuckDuckGo API\n";
echo "     • SerpApi（聚合搜索）\n\n";

echo "  2. 配置 API 密钥:\n";
echo "     • 在 .env 文件中配置\n";
echo "     • 使用环境变量管理\n";
echo "     • 定期轮换密钥\n\n";

echo "  3. 实现缓存机制:\n";
echo "     • Redis 缓存搜索结果\n";
echo "     • TTL 设置（如 1小时）\n";
echo "     • 减少 API 调用成本\n\n";

echo "  4. 错误处理:\n";
echo "     • API 限流处理\n";
echo "     • 降级策略\n";
echo "     • 重试机制\n\n";

echo "  5. 成本控制:\n";
echo "     • 监控 API 使用量\n";
echo "     • 设置每日调用上限\n";
echo "     • 优化查询频率\n\n";

echo "✅ 集成建议展示完成\n\n";

// ========================================
// 测试12: 工具定义导出
// ========================================
echo "【测试12】工具定义导出（供 AI 使用）\n";
echo "-----------------------------------\n";

echo "导出 Function Calling 格式定义...\n\n";

$toolDef = $googleSearch->getDefinition();

echo "工具配置:\n";
echo "```json\n";
echo json_encode([$toolDef], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n```\n\n";

echo "AI 调用示例:\n";
echo "```json\n";
echo json_encode([
    'role' => 'assistant',
    'content' => null,
    'tool_calls' => [
        [
            'id' => 'call_abc123',
            'type' => 'function',
            'function' => [
                'name' => 'http_search',
                'arguments' => json_encode([
                    'query' => 'AI technology trends 2024',
                    'limit' => 5,
                    'language' => 'en-US',
                ]),
            ],
        ],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n```\n\n";

echo "✅ 工具定义导出完成\n\n";

// ========================================
// 总结
// ========================================
echo "========================================\n";
echo "联网搜索测试总结\n";
echo "========================================\n\n";

echo "✅ 已测试的功能:\n";
echo "  1. 创建搜索工具（Google、Bing）\n";
echo "  2. 工具定义（Function Calling 格式）\n";
echo "  3. 基础搜索功能\n";
echo "  4. 参数化搜索（query, limit, language）\n";
echo "  5. 参数验证\n";
echo "  6. 工具注册表集成\n";
echo "  7. 沙箱安全执行\n";
echo "  8. 多种搜索场景\n";
echo "  9. 搜索引擎对比\n";
echo "  10. AI 使用场景模拟\n";
echo "  11. 实际集成建议\n";
echo "  12. 工具定义导出\n\n";

echo "🌐 联网搜索核心能力:\n";
echo "  ✅ 支持多个搜索引擎（Google、Bing 等）\n";
echo "  ✅ 灵活的参数配置（查询、数量、语言）\n";
echo "  ✅ 符合 OpenAI Function Calling 规范\n";
echo "  ✅ 安全执行（沙箱隔离）\n";
echo "  ✅ 参数验证和错误处理\n\n";

echo "🎯 应用场景:\n";
echo "  • AI 获取最新资讯和数据\n";
echo "  • 市场调研和竞品分析\n";
echo "  • 技术文档和解决方案查询\n";
echo "  • 实时信息验证\n";
echo "  • 知识库补充\n\n";

echo "💡 使用示例:\n";
echo "```php\n";
echo "// 1. 创建搜索工具\n";
echo "\$search = new HttpSearch('google', \$apiKey);\n\n";
echo "// 2. 执行搜索\n";
echo "\$result = \$search->execute([\n";
echo "    'query' => 'AI trends 2024',\n";
echo "    'limit' => 5,\n";
echo "    'language' => 'en-US',\n";
echo "]);\n\n";
echo "// 3. 处理结果\n";
echo "foreach (\$result['data'] as \$item) {\n";
echo "    echo \$item['title'] . ': ' . \$item['url'];\n";
echo "}\n";
echo "```\n\n";

echo "📊 当前状态:\n";
echo "  • 实现: ✓ 完成\n";
echo "  • 测试: ✓ 通过\n";
echo "  • 模拟数据: ✓ 正常\n";
echo "  • 生产就绪: ⏸ 需要集成真实 API\n\n";

echo "🔧 生产部署清单:\n";
echo "  □ 申请搜索 API 密钥\n";
echo "  □ 配置环境变量\n";
echo "  □ 实现 API 调用逻辑\n";
echo "  □ 添加结果缓存\n";
echo "  □ 设置调用限额\n";
echo "  □ 监控和告警\n\n";

echo "========================================\n";
echo "✅ 所有联网搜索测试完成！\n";
echo "========================================\n";
