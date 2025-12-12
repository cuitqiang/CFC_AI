<?php
/**
 * 测试中转 API - 检查所有可用模型
 */

$apiKey = 'sk-52SOuYpRckkyfYgaGMgcgk3pxG7cinhVnzjoepRvDUNOREVa';
$baseUrl = 'https://tbnx.plus7.plus/v1';

// 测试所有可能的模型
$models = [
    'deepseek-chat',
    'deepseek-r1',
    'deepseek-r1-250528',
    'deepseek-reasoner',
    'deepseek-reasoner-all',
    'deepseek-v3',
    'deepseek-v3-250324',
    'gemini-2.0-flash',
];

echo "=== 测试中转 API 模型可用性 ===\n\n";

foreach ($models as $model) {
    $ch = curl_init($baseUrl . '/chat/completions');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => $model,
        'messages' => [['role' => 'user', 'content' => 'hi']],
        'max_tokens' => 10
    ]));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);
    
    if ($httpCode === 200 && isset($data['choices'][0]['message']['content'])) {
        echo "✅ $model - 可用\n";
    } else {
        $errorMsg = $data['error']['message'] ?? 'Unknown error';
        // 截取关键错误信息
        if (strpos($errorMsg, '未配置') !== false) {
            echo "❌ $model - 未配置价格\n";
        } else {
            echo "❌ $model - " . substr($errorMsg, 0, 50) . "...\n";
        }
    }
}

echo "\n=== 测试完成 ===\n";
