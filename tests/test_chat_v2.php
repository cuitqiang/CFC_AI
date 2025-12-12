<?php
/**
 * 测试 V2 聊天 API
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Services/AI/Bootstrap.php';
Services\AI\Bootstrap::initialize();

use Services\AI\Core\ModelRouter;

// 测试数据库连接
echo "=== 数据库连接测试 ===\n";

$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port = $_ENV['DB_PORT'] ?? '3306';
$dbname = $_ENV['DB_DATABASE'] ?? 'cy_cfc';
$user = $_ENV['DB_USERNAME'] ?? 'cy_cfc';
$pass = $_ENV['DB_PASSWORD'] ?? '123456';

$dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "✓ 数据库连接成功: {$dbname}\n";
} catch (Exception $e) {
    echo "✗ 数据库连接失败: " . $e->getMessage() . "\n";
    exit(1);
}

// 测试表是否存在
echo "\n=== 检查表 ===\n";
$tables = ['cuige_sessions', 'cuige_short_memory', 'cuige_long_memory', 'cuige_user_profile', 'cuige_memory_tasks'];
foreach ($tables as $table) {
    $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
    if ($stmt->fetch()) {
        echo "✓ {$table} 存在\n";
    } else {
        echo "✗ {$table} 不存在\n";
    }
}

// 测试 AI 调用
echo "\n=== AI 调用测试 ===\n";
try {
    $router = Services\AI\Bootstrap::getModelRouter();
    $response = $router->chat('deepseek-v3-250324', [
        ['role' => 'system', 'content' => '你是崔哥，一个幽默的AI助手'],
        ['role' => 'user', 'content' => '你好，我叫张三']
    ], [
        'temperature' => 0.8,
        'max_tokens' => 500
    ]);
    
    echo "✓ AI 回复:\n";
    echo $response['content'] ?? '(无内容)';
    echo "\n";
    
} catch (Exception $e) {
    echo "✗ AI 调用失败: " . $e->getMessage() . "\n";
}

// 测试保存短期记忆
echo "\n=== 短期记忆测试 ===\n";
$sessionId = 'test_v2_' . time();
$userId = 'test001';

// 创建会话
$stmt = $pdo->prepare("INSERT INTO cuige_sessions (session_id, user_id, status) VALUES (?, ?, 'active')");
$stmt->execute([$sessionId, $userId]);
echo "✓ 创建会话: {$sessionId}\n";

// 保存消息
$stmt = $pdo->prepare("INSERT INTO cuige_short_memory (session_id, user_id, role, content) VALUES (?, ?, ?, ?)");
$stmt->execute([$sessionId, $userId, 'user', '你好，我叫张三']);
echo "✓ 保存用户消息\n";

$stmt->execute([$sessionId, $userId, 'assistant', '你好张三！很高兴认识你！']);
echo "✓ 保存助手回复\n";

// 读取消息
$stmt = $pdo->prepare("SELECT * FROM cuige_short_memory WHERE session_id = ? ORDER BY created_at");
$stmt->execute([$sessionId]);
$messages = $stmt->fetchAll();
echo "✓ 读取到 " . count($messages) . " 条消息\n";

// 测试长期记忆提取
echo "\n=== 长期记忆测试 ===\n";
$stmt = $pdo->prepare("
    INSERT INTO cuige_long_memory (user_id, key_type, key_info, importance)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE key_info = VALUES(key_info), last_mentioned_at = NOW()
");
$stmt->execute([$userId, '名字', '张三', 80]);
echo "✓ 保存长期记忆: 名字=张三\n";

// 读取长期记忆
$stmt = $pdo->prepare("SELECT * FROM cuige_long_memory WHERE user_id = ?");
$stmt->execute([$userId]);
$memories = $stmt->fetchAll();
echo "✓ 读取到 " . count($memories) . " 条长期记忆:\n";
foreach ($memories as $mem) {
    echo "  - {$mem['key_type']}: {$mem['key_info']}\n";
}

echo "\n=== 测试完成 ===\n";
