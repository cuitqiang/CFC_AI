<?php
/**
 * 测试辩论/群聊功能
 */
require_once __DIR__ . '/vendor/autoload.php';

use Services\AI\Bootstrap;
use Services\AI\Core\ModelRouter;

echo "=== 测试 AI 系统初始化 ===\n\n";

try {
    // 初始化
    Bootstrap::initialize(__DIR__ . '/.env');
    echo "✅ Bootstrap 初始化成功\n";
    
    // 获取 ModelRouter
    $router = Bootstrap::getModelRouter();
    echo "✅ ModelRouter 获取成功\n";
    
    // 测试 chat 功能
    echo "\n=== 测试 chat 功能 (deepseek-chat) ===\n";
    
    $messages = [
        ['role' => 'user', 'content' => '你好，请用一句话回复']
    ];
    
    $response = $router->chat('deepseek-chat', $messages, [
        'max_tokens' => 50,
        'temperature' => 0.8
    ]);
    
    if (isset($response['content'])) {
        echo "✅ Chat 响应: " . $response['content'] . "\n";
    } else {
        echo "❌ Chat 响应格式错误: " . json_encode($response) . "\n";
    }
    
    // 测试 streamChat 功能
    echo "\n=== 测试 streamChat 功能 ===\n";
    
    $streamContent = '';
    $router->streamChat('deepseek-chat', $messages, function($chunk) use (&$streamContent) {
        // 调试: 输出 chunk 结构
        if (isset($chunk['choices'][0]['delta']['content'])) {
            $content = $chunk['choices'][0]['delta']['content'];
            $streamContent .= $content;
            echo $content;
        }
    }, [
        'max_tokens' => 50,
        'temperature' => 0.8
    ]);
    
    echo "\n✅ Stream 完成, 内容: {$streamContent}\n";
    
} catch (\Throwable $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
    echo "位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "堆栈:\n" . $e->getTraceAsString() . "\n";
}
