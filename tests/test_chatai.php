<?php
// 测试 mttieeo.com API - 使用可用模型
$visionKey = 'sk-ups8eYhJoJF9mLGLcsLbb1EJwHoAfIqTV7ek5EA6S0u4Cuhb';
$visionUrl = 'https://api.mttieeo.com/v1';

echo "=== 测试 mttieeo.com 可用模型 ===\n\n";

// 测试几个可能支持多模态的模型
$models = [
    '[Y]gemini-2.5-pro',
    '[F]gemini-2.5-flash',
    '[IM]gemini-2.5-flash-image',  // 这个看名字应该支持图像
    'grok-4',
];

foreach ($models as $model) {
    echo "测试 $model...\n";
    $ch = curl_init($visionUrl . '/chat/completions');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $visionKey
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => $model,
        'messages' => [['role' => 'user', 'content' => 'hi']],
        'max_tokens' => 20
    ]));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);
    if ($httpCode === 200 && isset($data['choices'][0]['message']['content'])) {
        echo "   ✅ 可用: " . substr($data['choices'][0]['message']['content'], 0, 50) . "\n";
    } else {
        echo "   ❌ 失败: " . substr($data['error']['message'] ?? $response, 0, 50) . "\n";
    }
}

// 测试多模态
echo "\n=== 测试多模态识图 ===\n";
$imagePath = '/tmp/receipt.jpg';
if (file_exists($imagePath)) {
    $imageData = base64_encode(file_get_contents($imagePath));
    $testModel = '[Y]gemini-2.5-pro';
    
    echo "使用 $testModel 识图...\n";
    $ch = curl_init($visionUrl . '/chat/completions');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $visionKey
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => $testModel,
        'messages' => [[
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => '这张图片里有什么？'],
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
        echo "✅ 多模态成功!\n";
        echo "AI: " . $data['choices'][0]['message']['content'] . "\n";
    } else {
        echo "❌ 失败: " . ($data['error']['message'] ?? $response) . "\n";
    }
}

echo "\n=== 完成 ===\n";
