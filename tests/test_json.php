<?php
require __DIR__ . '/vendor/autoload.php';

use Services\AI\Vision\ImageAnalyzer;

$analyzer = new ImageAnalyzer();
$result = $analyzer->analyze('/tmp/receipt.jpg');

echo "Keys: " . implode(', ', array_keys($result)) . "\n";

echo "JSON test: ";
$json = json_encode($result, JSON_UNESCAPED_UNICODE);
if ($json === false) {
    echo "FAILED: " . json_last_error_msg() . "\n";
    
    // 测试每个字段
    foreach ($result as $key => $value) {
        $test = json_encode([$key => $value], JSON_UNESCAPED_UNICODE);
        if ($test === false) {
            echo "  - Field '$key' failed: " . json_last_error_msg() . "\n";
            echo "    Type: " . gettype($value) . "\n";
        }
    }
} else {
    echo "OK, length: " . strlen($json) . "\n";
}
