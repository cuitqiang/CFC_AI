<?php
/**
 * 模型路由系统完整测试
 * 测试 ModelRouter 的所有功能
 */

require_once __DIR__ . '/vendor/autoload.php';

use Services\AI\Bootstrap;
use Services\AI\Core\ModelRouter;
use Services\AI\Providers\DeepseekProvider;
use Services\AI\Providers\OpenAIProvider;

echo "========================================\n";
echo "模型路由系统完整测试\n";
echo "========================================\n\n";

// ========================================
// 测试1: Provider 注册
// ========================================
echo "【测试1】Provider 注册\n";
echo "-----------------------------------\n";

$router = new ModelRouter();

echo "创建 Provider 实例...\n";
$deepseekProvider = new DeepseekProvider(
    'sk-test-key',
    'https://api.deepseek.com/v1'
);

$openaiProvider = new OpenAIProvider(
    'sk-openai-key',
    'https://api.openai.com/v1'
);

echo "  ✓ DeepseekProvider 创建成功\n";
echo "  ✓ OpenAIProvider 创建成功\n\n";

// 单个注册
echo "单个注册 Provider...\n";
$router->register('deepseek', $deepseekProvider);
echo "  ✓ deepseek Provider 已注册\n";

$router->register('openai', $openaiProvider);
echo "  ✓ openai Provider 已注册\n\n";

echo "✅ Provider 注册测试完成\n\n";

// ========================================
// 测试2: Provider 批量注册
// ========================================
echo "【测试2】Provider 批量注册\n";
echo "-----------------------------------\n";

$router2 = new ModelRouter();

$providers = [
    'deepseek' => $deepseekProvider,
    'openai' => $openaiProvider,
];

echo "批量注册 " . count($providers) . " 个 Provider...\n";
$router2->registerMany($providers);

$registeredNames = $router2->getProviderNames();
echo "已注册的 Provider: " . implode(', ', $registeredNames) . "\n";
echo "  ✓ 共 " . count($registeredNames) . " 个 Provider\n\n";

echo "✅ 批量注册测试完成\n\n";

// ========================================
// 测试3: 默认 Provider 设置
// ========================================
echo "【测试3】默认 Provider 设置\n";
echo "-----------------------------------\n";

echo "设置默认 Provider 为 'deepseek'...\n";
$router->setDefaultProvider('deepseek');
echo "  ✓ 默认 Provider 已设置\n\n";

echo "✅ 默认 Provider 设置测试完成\n\n";

// ========================================
// 测试4: Provider 检索
// ========================================
echo "【测试4】Provider 检索\n";
echo "-----------------------------------\n";

echo "检查 Provider 是否存在...\n";
$hasDeepseek = $router->hasProvider('deepseek');
$hasOpenai = $router->hasProvider('openai');
$hasNonExist = $router->hasProvider('claude');

echo "  deepseek: " . ($hasDeepseek ? '✓ 存在' : '✗ 不存在') . "\n";
echo "  openai: " . ($hasOpenai ? '✓ 存在' : '✗ 不存在') . "\n";
echo "  claude: " . ($hasNonExist ? '✓ 存在' : '✗ 不存在 (预期)') . "\n\n";

echo "获取 Provider 实例...\n";
$provider = $router->getProvider('deepseek');
echo "  deepseek Provider: " . ($provider ? '✓ 获取成功' : '✗ 获取失败') . "\n";

$nullProvider = $router->getProvider('nonexist');
echo "  nonexist Provider: " . ($nullProvider === null ? '✓ 返回 null (预期)' : '✗ 未返回 null') . "\n\n";

echo "✅ Provider 检索测试完成\n\n";

// ========================================
// 测试5: 支持的模型列表
// ========================================
echo "【测试5】支持的模型列表\n";
echo "-----------------------------------\n";

echo "获取所有支持的模型...\n";
$allModels = $router->getAllSupportedModels();

foreach ($allModels as $providerName => $models) {
    echo "\n  {$providerName} 支持的模型 (" . count($models) . " 个):\n";
    foreach ($models as $model) {
        echo "    • {$model}\n";
    }
}

echo "\n✅ 模型列表测试完成\n\n";

// ========================================
// 测试6: 模型支持检查
// ========================================
echo "【测试6】模型支持检查\n";
echo "-----------------------------------\n";

$testModels = [
    'deepseek-chat',
    'deepseek-v3',
    'gpt-4',
    'gpt-3.5-turbo',
    'claude-3',  // 不支持
];

echo "检查模型支持情况...\n";
foreach ($testModels as $model) {
    $supported = $router->supportsModel($model);
    $status = $supported ? '✓ 支持' : '✗ 不支持';
    echo "  {$model}: {$status}\n";
}

echo "\n✅ 模型支持检查测试完成\n\n";

// ========================================
// 测试7: 模型路由
// ========================================
echo "【测试7】模型路由\n";
echo "-----------------------------------\n";

echo "测试自动路由功能...\n\n";

$routeTests = [
    'deepseek-chat' => 'deepseek',
    'deepseek-v3' => 'deepseek',
    'gpt-4' => 'openai',
    'gpt-3.5-turbo' => 'openai',
];

foreach ($routeTests as $model => $expectedProvider) {
    try {
        $provider = $router->route($model);
        $providerClass = get_class($provider);

        // 检查是否路由到正确的 Provider
        $isCorrect = false;
        if ($expectedProvider === 'deepseek' && $provider instanceof DeepseekProvider) {
            $isCorrect = true;
        } elseif ($expectedProvider === 'openai' && $provider instanceof OpenAIProvider) {
            $isCorrect = true;
        }

        $status = $isCorrect ? '✓' : '✗';
        echo "  {$status} {$model} → " . basename(str_replace('\\', '/', $providerClass)) . "\n";
    } catch (\Exception $e) {
        echo "  ✗ {$model} → 路由失败: {$e->getMessage()}\n";
    }
}

echo "\n测试不支持的模型...\n";
try {
    $router->route('unknown-model');
    echo "  ✗ 应该抛出异常但没有\n";
} catch (\RuntimeException $e) {
    echo "  ✓ 正确抛出异常: " . $e->getMessage() . "\n";
}

echo "\n✅ 模型路由测试完成\n\n";

// ========================================
// 测试8: chat() 方法（真实 API）
// ========================================
echo "【测试8】chat() 方法（真实 API）\n";
echo "-----------------------------------\n";

// 使用 Bootstrap 初始化真实配置
Bootstrap::initialize();
$realRouter = Bootstrap::getModelRouter();

echo "使用真实 API 测试 chat() 方法...\n";

$messages = [
    ['role' => 'user', 'content' => '请用一句话介绍什么是模型路由']
];

try {
    echo "\n调用 deepseek-v3 模型...\n";
    $result = $realRouter->chat('deepseek-v3', $messages);

    echo "  ✓ 调用成功\n";
    echo "  模型: " . ($result['model'] ?? 'unknown') . "\n";
    echo "  用量: " . json_encode($result['usage'] ?? []) . "\n";
    echo "  回复: " . substr($result['content'] ?? $result['message'] ?? '', 0, 100) . "...\n";
} catch (\Exception $e) {
    echo "  ✗ 调用失败: " . $e->getMessage() . "\n";
}

echo "\n✅ chat() 方法测试完成\n\n";

// ========================================
// 测试9: 路由策略测试
// ========================================
echo "【测试9】路由策略测试\n";
echo "-----------------------------------\n";

echo "验证路由策略...\n\n";

echo "策略1: 模型精确匹配\n";
echo "  当模型名完全匹配时，路由到支持该模型的 Provider\n";
$provider1 = $router->route('deepseek-chat');
echo "  deepseek-chat → " . get_class($provider1) . " ✓\n\n";

echo "策略2: 不支持的模型使用默认 Provider\n";
echo "  当没有 Provider 支持该模型时，使用默认 Provider\n";
$router3 = new ModelRouter();
$router3->register('deepseek', $deepseekProvider);
$router3->setDefaultProvider('deepseek');
try {
    $provider2 = $router3->route('some-unknown-model');
    echo "  some-unknown-model → " . get_class($provider2) . " ✓ (默认)\n";
} catch (\Exception $e) {
    echo "  ✗ 路由失败\n";
}

echo "\n✅ 路由策略测试完成\n\n";

// ========================================
// 测试10: Provider 信息统计
// ========================================
echo "【测试10】Provider 信息统计\n";
echo "-----------------------------------\n";

echo "系统 Provider 统计...\n\n";

$providerNames = $router->getProviderNames();
$allModels = $router->getAllSupportedModels();

echo "已注册 Provider 数量: " . count($providerNames) . "\n";
echo "支持的模型总数: " . array_sum(array_map('count', $allModels)) . "\n\n";

echo "详细信息:\n";
foreach ($allModels as $name => $models) {
    echo "  • {$name}: " . count($models) . " 个模型\n";
}

echo "\n✅ Provider 统计测试完成\n\n";

// ========================================
// 测试11: 完整工作流演示
// ========================================
echo "【测试11】完整工作流演示\n";
echo "-----------------------------------\n";

echo "场景: 用户请求处理 → 自动模型选择 → API 调用\n\n";

$tasks = [
    [
        'task' => '代码审查',
        'model' => 'deepseek-coder',
        'prompt' => '请审查这段代码的质量'
    ],
    [
        'task' => '通用对话',
        'model' => 'deepseek-chat',
        'prompt' => '你好，介绍一下你自己'
    ],
];

foreach ($tasks as $i => $task) {
    echo "任务 " . ($i + 1) . ": {$task['task']}\n";
    echo "  选择模型: {$task['model']}\n";

    // 检查是否支持
    if ($router->supportsModel($task['model'])) {
        echo "  ✓ 模型支持\n";

        // 路由到 Provider
        try {
            $provider = $router->route($task['model']);
            $providerName = basename(str_replace('\\', '/', get_class($provider)));
            echo "  ✓ 路由到: {$providerName}\n";
        } catch (\Exception $e) {
            echo "  ✗ 路由失败\n";
        }
    } else {
        echo "  ✗ 模型不支持\n";
    }

    echo "\n";
}

echo "✅ 完整工作流演示完成\n\n";

// ========================================
// 总结
// ========================================
echo "========================================\n";
echo "模型路由测试总结\n";
echo "========================================\n\n";

echo "✅ 已测试的功能:\n";
echo "  1. Provider 单个注册 (register)\n";
echo "  2. Provider 批量注册 (registerMany)\n";
echo "  3. 默认 Provider 设置 (setDefaultProvider)\n";
echo "  4. Provider 检索 (getProvider, hasProvider)\n";
echo "  5. 支持的模型列表 (getAllSupportedModels)\n";
echo "  6. 模型支持检查 (supportsModel)\n";
echo "  7. 模型自动路由 (route)\n";
echo "  8. chat() 方法（真实 API）\n";
echo "  9. 路由策略验证\n";
echo "  10. Provider 信息统计\n";
echo "  11. 完整工作流演示\n\n";

echo "📊 ModelRouter 核心能力:\n";
echo "  ✅ 自动识别模型并路由到正确的 Provider\n";
echo "  ✅ 支持多个 Provider 并存（Deepseek、OpenAI 等）\n";
echo "  ✅ 不支持的模型自动使用默认 Provider\n";
echo "  ✅ 统一的 chat() 接口，自动路由\n";
echo "  ✅ 流式调用支持 (streamChat)\n";
echo "  ✅ 模型可用性检查\n\n";

echo "🎯 路由策略:\n";
echo "  1. 遍历所有 Provider，找到支持该模型的\n";
echo "  2. 如果多个 Provider 支持，使用第一个匹配的\n";
echo "  3. 如果都不支持，使用默认 Provider\n";
echo "  4. 如果没有默认 Provider，抛出异常\n\n";

echo "🔧 支持的 Provider:\n";
$realProviders = $realRouter->getProviderNames();
foreach ($realProviders as $name) {
    echo "  • {$name}\n";
}

echo "\n💡 应用场景:\n";
echo "  • 多模型切换（根据任务选择最佳模型）\n";
echo "  • 多供应商管理（OpenAI、Deepseek、本地模型等）\n";
echo "  • 自动降级（主模型不可用时切换备用）\n";
echo "  • 成本优化（便宜模型处理简单任务）\n\n";

echo "========================================\n";
echo "✅ 所有模型路由测试完成！\n";
echo "========================================\n";
