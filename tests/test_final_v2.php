<?php
/**
 * 完整 V2 API 测试
 */

$baseUrl = 'http://localhost:7766/cuige_api.php';
$userId = 'final_test_' . time();
$sessionId = 'final_session_' . time();

echo "=== 崔哥 V2 智能记忆系统测试 ===\n";
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

// 第1轮：自我介绍
echo "【第1轮对话】\n";
echo "用户: 你好，我叫张伟，我今年28岁，在上海做程序员\n";
$result = chat($baseUrl, $userId, $sessionId, "你好，我叫张伟，我今年28岁，在上海做程序员");
echo "崔哥: " . ($result['reply'] ?? '错误: ' . ($result['error'] ?? '')) . "\n\n";

sleep(1);

// 第2轮：聊工作
echo "【第2轮对话】\n";
echo "用户: 最近工作压力好大，天天加班\n";
$result = chat($baseUrl, $userId, $sessionId, "最近工作压力好大，天天加班");
echo "崔哥: " . ($result['reply'] ?? '错误') . "\n\n";

sleep(1);

// 第3轮：测试记忆
echo "【第3轮对话】\n";
echo "用户: 你还记得我叫什么、在哪工作吗？\n";
$result = chat($baseUrl, $userId, $sessionId, "你还记得我叫什么、在哪工作吗？");
echo "崔哥: " . ($result['reply'] ?? '错误') . "\n\n";

sleep(1);

// 第4轮：更多测试
echo "【第4轮对话】\n";
echo "用户: 对了，我喜欢打篮球，周末经常去打球\n";
$result = chat($baseUrl, $userId, $sessionId, "对了，我喜欢打篮球，周末经常去打球");
echo "崔哥: " . ($result['reply'] ?? '错误') . "\n\n";

sleep(1);

// 第5轮：总结测试
echo "【第5轮对话】\n";
echo "用户: 总结一下你对我了解多少\n";
$result = chat($baseUrl, $userId, $sessionId, "总结一下你对我了解多少");
echo "崔哥: " . ($result['reply'] ?? '错误') . "\n\n";

// 检查历史记录
echo "=== 历史记录检查 ===\n";
$historyUrl = "{$baseUrl}?action=history&session_id={$sessionId}&user_id={$userId}";
$historyResult = json_decode(file_get_contents($historyUrl), true);
echo "保存的消息数: " . ($historyResult['count'] ?? 0) . "\n";

// 检查长期记忆
echo "\n=== 长期记忆检查 ===\n";
$memoriesUrl = "{$baseUrl}?action=memories&user_id={$userId}";
$memoriesResult = json_decode(file_get_contents($memoriesUrl), true);
if (!empty($memoriesResult['long_term'])) {
    foreach ($memoriesResult['long_term'] as $mem) {
        echo "  - {$mem['key_type']}: {$mem['key_info']}\n";
    }
} else {
    echo "  (暂无长期记忆)\n";
}

echo "\n=== 测试完成 ===\n";
