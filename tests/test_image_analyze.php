<?php
/**
 * 测试图片分析功能
 */
require_once __DIR__ . '/vendor/autoload.php';

use Services\AI\Vision\ImageAnalyzer;

echo "=== 测试图片分析 ===\n\n";

try {
    $analyzer = new ImageAnalyzer();
    
    echo "配置信息:\n";
    $caps = $analyzer->getCapabilities();
    print_r($caps);
    
    // 测试分析
    $testImage = '/tmp/receipt.jpg';
    if (!file_exists($testImage)) {
        die("测试图片不存在: $testImage\n");
    }
    
    echo "\n分析图片: $testImage\n";
    echo "文件大小: " . filesize($testImage) . " bytes\n\n";
    
    $result = $analyzer->analyze($testImage);
    
    echo "分析结果:\n";
    echo "成功: " . ($result['success'] ? 'Yes' : 'No') . "\n";
    
    if ($result['success']) {
        echo "描述: " . ($result['description'] ?? 'N/A') . "\n";
        echo "元素: " . json_encode($result['elements'] ?? [], JSON_UNESCAPED_UNICODE) . "\n";
        echo "情感: " . ($result['emotion'] ?? 'N/A') . "\n";
        echo "话题: " . json_encode($result['topics'] ?? [], JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "错误: " . ($result['error'] ?? 'Unknown') . "\n";
    }
    
} catch (\Throwable $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
    echo "位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "堆栈:\n" . $e->getTraceAsString() . "\n";
}
