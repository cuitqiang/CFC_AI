<?php
/**
 * HTTP 测试 V2 聊天 API
 */

$baseUrl = 'http://localhost:7766/cuige_api.php';

echo "=== 测试健康检查 ===\n";
$response = file_get_contents("{$baseUrl}?action=health");
echo $response . "\n\n";

echo "=== 测试聊天 (第1轮) ===\n";
$data = [
    'message' => '你好，我叫李明，我在北京工作',
    'user_id' => 'http_test_001',
    'session_id' => 'http_session_' . time()
];

$options = [
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => json_encode($data),
        'timeout' => 60
    ]
];

$context = stream_context_create($options);
$response = file_get_contents("{$baseUrl}?action=chat", false, $context);
$result = json_decode($response, true);
echo "回复: " . ($result['reply'] ?? '错误: ' . ($result['error'] ?? '未知')) . "\n\n";

// 保存 session_id 用于后续测试
$sessionId = $result['session_id'] ?? $data['session_id'];

echo "=== 测试聊天 (第2轮 - 测试上下文) ===\n";
$data2 = [
    'message' => '你还记得我叫什么名字吗？',
    'user_id' => 'http_test_001',
    'session_id' => $sessionId
];

$options['http']['content'] = json_encode($data2);
$context = stream_context_create($options);
$response = file_get_contents("{$baseUrl}?action=chat", false, $context);
$result = json_decode($response, true);
echo "回复: " . ($result['reply'] ?? '错误: ' . ($result['error'] ?? '未知')) . "\n\n";

echo "=== 测试聊天 (第3轮 - 测试记忆) ===\n";
$data3 = [
    'message' => '我在哪个城市工作来着？',
    'user_id' => 'http_test_001',
    'session_id' => $sessionId
];

$options['http']['content'] = json_encode($data3);
$context = stream_context_create($options);
$response = file_get_contents("{$baseUrl}?action=chat", false, $context);
$result = json_decode($response, true);
echo "回复: " . ($result['reply'] ?? '错误: ' . ($result['error'] ?? '未知')) . "\n\n";

echo "=== 测试历史记录 ===\n";
$response = file_get_contents("{$baseUrl}?action=history&session_id={$sessionId}&user_id=http_test_001");
$result = json_decode($response, true);
echo "历史消息数: " . ($result['count'] ?? 0) . "\n";
if (!empty($result['messages'])) {
    foreach ($result['messages'] as $msg) {
        echo "  [{$msg['role']}]: " . mb_substr($msg['content'], 0, 50) . "...\n";
    }
}

echo "\n=== 测试长期记忆 ===\n";
$response = file_get_contents("{$baseUrl}?action=memories&user_id=http_test_001");
$result = json_decode($response, true);
echo "长期记忆:\n";
if (!empty($result['long_term'])) {
    foreach ($result['long_term'] as $mem) {
        echo "  - {$mem['key_type']}: {$mem['key_info']}\n";
    }
} else {
    echo "  (暂无)\n";
}

echo "\n=== 测试完成 ===\n";
