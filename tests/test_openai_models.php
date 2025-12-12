<?php
/**
 * 测试 OpenAI 系列模型
 */

$apiKey = 'sk-52SOuYpRckkyfYgaGMgcgk3pxG7cinhVnzjoepRvDUNOREVa';
$baseUrl = 'https://tbnx.plus7.plus/v1';

$models = [
    'gpt-4o-mini',
    'gpt-4o',
    'gpt-4-turbo',
    'gpt-4',
    'gpt-3.5-turbo',
    'claude-3-haiku',
    'claude-3-sonnet',
];

echo "=== 测试 OpenAI/Claude 模型 ===\n\n";

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
        $errorMsg = $data['error']['message'] ?? 'Unknown';
        if (strpos($errorMsg, '未配置') !== false) {
            echo "❌ $model - 未配置价格\n";
        } elseif (strpos($errorMsg, 'Illegal') !== false) {
            echo "❌ $model - 不支持\n";
        } else {
            echo "❌ $model - " . substr($errorMsg, 0, 40) . "\n";
        }
    }
}

echo "\n=== 测试完成 ===\n";
