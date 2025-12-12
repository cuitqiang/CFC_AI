<?php
/**
 * 直接测试 cuige_api.php 的 chat 功能
 */

// 模拟 HTTP 环境
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['action'] = 'chat';

// 模拟 POST 数据
$input = json_encode([
    'message' => '你好，我叫李明',
    'user_id' => 'direct_test_001',
    'session_id' => 'direct_session_' . time()
]);

// 创建临时输入流
$tempFile = tmpfile();
fwrite($tempFile, $input);
fseek($tempFile, 0);
$tempPath = stream_get_meta_data($tempFile)['uri'];

// 开启输出缓冲
ob_start();

try {
    // 重定向 php://input
    // 这个方法行不通，改用直接调用函数
    
    // 加载框架
    require_once __DIR__ . '/vendor/autoload.php';
    require_once __DIR__ . '/src/Services/AI/Bootstrap.php';
    Services\AI\Bootstrap::initialize();
    
    // 数据库连接
    $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $port = $_ENV['DB_PORT'] ?? '3306';
    $dbname = $_ENV['DB_DATABASE'] ?? 'cy_cfc';
    $user = $_ENV['DB_USERNAME'] ?? 'cy_cfc';
    $pass = $_ENV['DB_PASSWORD'] ?? '123456';
    
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "✓ 数据库连接成功\n";
    
    // 解析输入
    $data = json_decode($input, true);
    $message = $data['message'];
    $userId = $data['user_id'];
    $sessionId = $data['session_id'];
    
    echo "消息: {$message}\n";
    echo "用户: {$userId}\n";
    echo "会话: {$sessionId}\n\n";
    
    // 创建会话
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO cuige_sessions (session_id, user_id, status)
        VALUES (?, ?, 'active')
    ");
    $stmt->execute([$sessionId, $userId]);
    echo "✓ 会话已创建\n";
    
    // 保存用户消息
    $stmt = $pdo->prepare("
        INSERT INTO cuige_short_memory (session_id, user_id, role, content)
        VALUES (?, ?, 'user', ?)
    ");
    $stmt->execute([$sessionId, $userId, $message]);
    echo "✓ 用户消息已保存\n";
    
    // 获取历史消息
    $stmt = $pdo->prepare("
        SELECT role, content FROM cuige_short_memory 
        WHERE session_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$sessionId]);
    $history = array_reverse($stmt->fetchAll());
    echo "✓ 历史消息: " . count($history) . " 条\n";
    
    // 构建消息
    $messages = [
        ['role' => 'system', 'content' => '你是崔哥，一个说话直接、幽默风趣的AI助手。']
    ];
    foreach ($history as $h) {
        $messages[] = ['role' => $h['role'], 'content' => $h['content']];
    }
    
    echo "\n=== 调用 AI ===\n";
    echo "消息数: " . count($messages) . "\n";
    
    // 调用 AI
    $router = Services\AI\Bootstrap::getModelRouter();
    $response = $router->chat('deepseek-v3-250324', $messages, [
        'temperature' => 0.8,
        'max_tokens' => 1000
    ]);
    
    $reply = $response['content'] ?? '';
    echo "✓ AI 回复:\n{$reply}\n\n";
    
    // 保存助手回复
    $stmt = $pdo->prepare("
        INSERT INTO cuige_short_memory (session_id, user_id, role, content)
        VALUES (?, ?, 'assistant', ?)
    ");
    $stmt->execute([$sessionId, $userId, $reply]);
    echo "✓ 助手回复已保存\n";
    
} catch (Exception $e) {
    echo "✗ 错误: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "堆栈:\n" . $e->getTraceAsString() . "\n";
}

$output = ob_get_clean();
echo $output;

fclose($tempFile);
