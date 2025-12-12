<?php
/**
 * 详细错误测试
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'http://localhost:7766/cuige_api.php?action=chat',
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'message' => '你好',
        'user_id' => 'curl_test',
        'session_id' => 'curl_session_' . time()
    ]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
echo "Error: {$error}\n";
echo "Response:\n{$response}\n";
