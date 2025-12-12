<?php
/**
 * 测试双 API 配置
 */

echo "=== 测试双 API 配置 ===\n\n";

// API 1: Deepseek (文本对话)
$deepseekKey = 'sk-52SOuYpRckkyfYgaGMgcgk3pxG7cinhVnzjoepRvDUNOREVa';
$deepseekUrl = 'https://tbnx.plus7.plus/v1';

echo "1. 测试 Deepseek API (deepseek-v3-250324)...\n";
$ch = curl_init($deepseekUrl . '/chat/completions');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $deepseekKey
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'model' => 'deepseek-v3-250324',
    'messages' => [['role' => 'user', 'content' => '你好']],
    'max_tokens' => 50
]));
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);
if ($httpCode === 200 && isset($data['choices'][0]['message']['content'])) {
    echo "   ✅ Deepseek 可用: " . substr($data['choices'][0]['message']['content'], 0, 50) . "\n";
} else {
    echo "   ❌ Deepseek 失败: " . ($data['error']['message'] ?? 'Unknown') . "\n";
}

// API 2: ChatAI API (多模态)
$visionKey = 'sk-s2jQO4jcRms2A9V79zx81mKTrzsjUoTjliXUT0cNL68O9k5I';
$visionUrl = 'https://www.chataiapi.com/v1';

echo "\n2. 测试 ChatAI API (gemini-2.5-pro 文本)...\n";
$ch = curl_init($visionUrl . '/chat/completions');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $visionKey
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'model' => 'gemini-2.5-pro',
    'messages' => [['role' => 'user', 'content' => 'hi']],
    'max_tokens' => 50
]));
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);
if ($httpCode === 200 && isset($data['choices'][0]['message']['content'])) {
    echo "   ✅ ChatAI 文本可用: " . substr($data['choices'][0]['message']['content'], 0, 50) . "\n";
} else {
    echo "   ❌ ChatAI 文本失败: " . ($data['error']['message'] ?? json_encode($data)) . "\n";
}

// 测试多模态
echo "\n3. 测试 ChatAI API (gemini-2.5-pro 多模态)...\n";

// 使用本地测试图片
$imagePath = '/tmp/receipt.jpg';
if (!file_exists($imagePath)) {
    echo "   ⚠️ 测试图片不存在，跳过多模态测试\n";
} else {
    $imageData = base64_encode(file_get_contents($imagePath));
    
    $ch = curl_init($visionUrl . '/chat/completions');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $visionKey
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'gemini-2.5-pro',
        'messages' => [[
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => '这张图片里有什么？简要描述。'],
                ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,' . $imageData]]
            ]
        ]],
        'max_tokens' => 200
    ]));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);
    if ($httpCode === 200 && isset($data['choices'][0]['message']['content'])) {
        echo "   ✅ 多模态识图成功!\n";
        echo "   AI 回答: " . $data['choices'][0]['message']['content'] . "\n";
    } else {
        echo "   ❌ 多模态失败: " . ($data['error']['message'] ?? json_encode($data)) . "\n";
    }
}

echo "\n=== 测试完成 ===\n";
