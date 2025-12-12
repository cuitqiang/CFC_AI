<?php
/**
 * RAG 上传测试脚本
 */

$url = 'http://127.0.0.1:5555/api/rag/upload';
$filePath = __DIR__ . '/test_document.md';

// 检查文件是否存在
if (!file_exists($filePath)) {
    die("测试文件不存在: {$filePath}\n");
}

echo "上传文件: {$filePath}\n";
echo "文件大小: " . filesize($filePath) . " bytes\n";

// 使用 CURLFile 上传
$ch = curl_init($url);
$cFile = new CURLFile($filePath, 'text/markdown', 'test_document.md');

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, ['file' => $cFile]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
if ($error) {
    echo "cURL Error: {$error}\n";
}
echo "Response:\n";
echo $response . "\n";
