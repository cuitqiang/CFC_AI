<?php
/**
 * 详细的 RAG 上传测试脚本
 */

$url = 'http://127.0.0.1:5555/api/rag/upload';
$filePath = __DIR__ . '/test_document.md';

// 检查文件是否存在
if (!file_exists($filePath)) {
    die("测试文件不存在: {$filePath}\n");
}

echo "============ RAG Upload Test ============\n";
echo "上传文件: {$filePath}\n";
echo "文件大小: " . filesize($filePath) . " bytes\n";
echo "目标 URL: {$url}\n\n";

// 使用 CURLFile 上传
$ch = curl_init($url);
$cFile = new CURLFile($filePath, 'text/markdown', basename($filePath));

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => ['file' => $cFile],
    CURLOPT_VERBOSE => true, // 启用详细输出
    CURLOPT_STDERR => fopen('php://output', 'w'),
]);

echo "========== cURL Verbose Output ==========\n";
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
$info = curl_getinfo($ch);
curl_close($ch);

echo "\n============== Response Info ============\n";
echo "HTTP Code: {$httpCode}\n";
echo "Content-Type: " . ($info['content_type'] ?? 'N/A') . "\n";
echo "Total Time: {$info['total_time']}s\n";

if ($error) {
    echo "cURL Error: {$error}\n";
}

echo "\n================ Response ===============\n";
echo $response . "\n";

// 尝试解析 JSON
$decoded = json_decode($response, true);
if ($decoded) {
    echo "\n============ Parsed Response ============\n";
    print_r($decoded);
}
