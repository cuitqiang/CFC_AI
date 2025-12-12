<?php
/**
 * 最简单的AI调用示例
 */

require_once __DIR__ . '/vendor/autoload.php';

use Services\AI\Bootstrap;

// 一行代码初始化整个系统
Bootstrap::initialize();

// 调用AI
$aiManager = Bootstrap::getAIManager();

// 发送消息
$result = $aiManager->process("你好，请介绍一下你自己");

// 显示结果
echo "AI回复：\n";
echo $result['response'] ?? $result['message'] ?? '无响应';
echo "\n";
