<?php
/**
 * 测试 V3 大厂级记忆系统
 */

$baseUrl = 'http://localhost:7766/cuige_api_v3.php';
$userId = 'v3_test_' . time();
$sessionId = 'v3_session_' . time();

echo "=== 崔哥 V3 大厂级记忆系统测试 ===\n";
echo "用户ID: {$userId}\n";
echo "会话ID: {$sessionId}\n\n";

function chat($baseUrl, $userId, $sessionId, $message) {
    $ch = curl_init("{$baseUrl}?action=chat");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'message' => $message,
            'user_id' => $userId,
            'session_id' => $sessionId
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// 健康检查
echo "【健康检查】\n";
$health = json_decode(file_get_contents("{$baseUrl}?action=health"), true);
echo json_encode($health, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";

// 多轮对话测试
$conversations = [
    "你好！我叫陈小明，今年32岁，在深圳做产品经理",
    "最近项目压力很大，需求变更太频繁了",
    "周末我喜欢去爬山，深圳梧桐山很漂亮",
    "对了，我有个3岁的女儿，特别可爱",
    "你还记得我的基本信息吗？",
    "能总结一下我们聊了什么吗？",
];

foreach ($conversations as $i => $msg) {
    echo "【第" . ($i + 1) . "轮对话】\n";
    echo "用户: {$msg}\n";
    $result = chat($baseUrl, $userId, $sessionId, $msg);
    echo "崔哥: " . ($result['reply'] ?? '错误: ' . ($result['error'] ?? '')) . "\n\n";
    sleep(1);
}

// 检查语义记忆
echo "=== 语义记忆检查 ===\n";
$memories = json_decode(file_get_contents("{$baseUrl}?action=memories&user_id={$userId}"), true);
if (!empty($memories['semantic_memory'])) {
    echo "提取的用户信息:\n";
    foreach ($memories['semantic_memory'] as $mem) {
        echo "  - [{$mem['category']}] {$mem['subject']}: {$mem['content']}\n";
    }
} else {
    echo "  (暂无语义记忆)\n";
}

// 手动触发压缩
echo "\n=== 手动触发压缩 ===\n";
$ch = curl_init("{$baseUrl}?action=compress");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'session_id' => $sessionId,
        'user_id' => $userId
    ]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true
]);
$compressResult = json_decode(curl_exec($ch), true);
curl_close($ch);
echo "压缩结果: " . json_encode($compressResult, JSON_UNESCAPED_UNICODE) . "\n";

// 检查用户画像
echo "\n=== 用户画像检查 ===\n";
$profile = json_decode(file_get_contents("{$baseUrl}?action=profile&user_id={$userId}"), true);
if (!empty($profile['profile'])) {
    echo "用户画像:\n";
    foreach ($profile['profile'] as $key => $value) {
        if (!empty($value)) {
            echo "  - {$key}: {$value}\n";
        }
    }
} else {
    echo "  (暂无用户画像)\n";
}

// 检查情景记忆（摘要）
echo "\n=== 情景记忆（摘要）检查 ===\n";
if (!empty($memories['episodic_memory'])) {
    echo "对话摘要:\n";
    foreach ($memories['episodic_memory'] as $ep) {
        echo "  - {$ep['summary']} (重要性:{$ep['importance_score']})\n";
    }
} else {
    echo "  (暂无摘要)\n";
}

echo "\n=== 测试完成 ===\n";
