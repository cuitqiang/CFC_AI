<?php
/**
 * 简单的AI API接口示例
 * 可以通过HTTP调用AI功能
 */

require_once __DIR__ . '/vendor/autoload.php';

use Services\AI\Bootstrap;

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// 初始化AI系统
Bootstrap::initialize();
$aiManager = Bootstrap::getAIManager();

// 获取请求参数
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // 获取POST数据
    $input = json_decode(file_get_contents('php://input'), true);

    $message = $input['message'] ?? '';
    $userId = $input['user_id'] ?? 'guest';
    $model = $input['model'] ?? 'deepseek-v3';

    if (empty($message)) {
        echo json_encode([
            'success' => false,
            'error' => '消息不能为空'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        // 调用AI
        $result = $aiManager->process($message, [
            'user_id' => $userId,
            'model' => $model,
            'timestamp' => time()
        ]);

        // 返回结果
        echo json_encode([
            'success' => true,
            'data' => [
                'response' => $result['response'] ?? $result['message'] ?? '',
                'user_id' => $userId,
                'model' => $model,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    } catch (\Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }

} elseif ($method === 'GET') {
    // GET请求返回API信息
    echo json_encode([
        'name' => 'CRM_AI_V7.6 API',
        'version' => '7.6.0',
        'status' => 'running',
        'endpoints' => [
            'POST /my_api.php' => [
                'description' => '发送消息给AI',
                'parameters' => [
                    'message' => '必填，用户消息',
                    'user_id' => '可选，用户ID',
                    'model' => '可选，模型名称（默认deepseek-v3）'
                ],
                'example' => [
                    'message' => '你好，AI',
                    'user_id' => 'user123',
                    'model' => 'deepseek-v3'
                ]
            ]
        ],
        'usage_example_curl' => 'curl -X POST http://localhost/my_api.php -H "Content-Type: application/json" -d \'{"message":"你好"}\''
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} else {
    echo json_encode([
        'success' => false,
        'error' => '不支持的请求方法'
    ], JSON_UNESCAPED_UNICODE);
}
