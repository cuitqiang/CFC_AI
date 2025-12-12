<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\SSEResponse;
use App\Bootstrap\App;
use Services\AI\Debate\DebateService;
use Services\AI\Vision\ImageAnalyzer;

/**
 * 辩论控制器
 * 
 * CFC V7.7 规范：
 * - 职责：接收参数、调用 Service、返回 Response
 * - 严禁在此编写业务逻辑
 * - 使用 SSEResponse 处理流式输出
 */
class DebateController
{
    /**
     * 流式辩论接口
     * 
     * @route GET /api/debate/stream?topic=xxx&mode=chat
     */
    public function stream(Request $request, array $params): Response
    {
        // 获取并验证参数
        $topic = trim($request->query('topic', ''));
        $mode = $request->query('mode', 'chat');

        if (empty($topic)) {
            return Response::error('参数错误：topic 不能为空', 400);
        }

        // 验证 mode
        if (!in_array($mode, ['chat', 'debate'], true)) {
            $mode = 'chat';
        }

        // SSE 响应不通过 Response 类，而是直接使用 SSEResponse
        try {
            // 初始化 SSE
            SSEResponse::init();

            // 调用 Service 处理业务逻辑
            $service = new DebateService($mode);
            $service->run($topic);

            // 结束 SSE
            SSEResponse::end();

        } catch (\Throwable $e) {
            SSEResponse::error('系统错误: ' . $e->getMessage());
            SSEResponse::end();
        }

        // 返回空响应（SSE 已直接输出）
        return Response::sse();
    }

    /**
     * 用户发言接口
     * 
     * @route POST /api/debate/speak
     */
    public function speak(Request $request, array $params): Response
    {
        $sessionId = $request->json('session_id', '');
        $message = $request->json('message', '');

        if (empty($sessionId) || empty($message)) {
            return Response::error('参数不完整：需要 session_id 和 message', 400);
        }

        try {
            // TODO: 实现用户消息队列
            return Response::success(null, '消息已接收');
        } catch (\Throwable $e) {
            return Response::error($e->getMessage(), 500);
        }
    }

    /**
     * 获取可用 Agents 列表
     * 
     * @route GET /api/debate/agents?mode=chat
     */
    public function listAgents(Request $request, array $params): Response
    {
        $mode = $request->query('mode', 'chat');

        try {
            $agents = App::config("agents.{$mode}", []);

            return Response::success([
                'mode' => $mode,
                'agents' => array_map(fn($a) => [
                    'id' => $a['id'] ?? '',
                    'name' => $a['name'] ?? '',
                    'role' => $a['role'] ?? '',
                ], $agents),
            ]);
        } catch (\Throwable $e) {
            return Response::error($e->getMessage(), 500);
        }
    }

    /**
     * 图片分析接口 - 用于群聊前的图片识别
     * 
     * @route POST /api/debate/analyze-image
     */
    public function analyzeImage(Request $request, array $params): Response
    {
        // 检查是否有上传文件
        if (!isset($_FILES['image'])) {
            return Response::error('请上传图片文件', 400);
        }

        try {
            $analyzer = new ImageAnalyzer();
            $result = $analyzer->analyzeUpload($_FILES['image']);
            
            if (!$result['success']) {
                return Response::error($result['error'] ?? '图片分析失败', 500);
            }

            return Response::success([
                'description' => $result['description'] ?? '',
                'elements' => $result['elements'] ?? [],
                'emotion' => $result['emotion'] ?? '',
                'topics' => $result['topics'] ?? [],
                'raw_content' => $result['raw_content'] ?? '',
                'model' => $result['model'] ?? '',
            ], '图片分析成功');

        } catch (\InvalidArgumentException $e) {
            return Response::error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            return Response::error('系统错误: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 带图片的流式辩论接口
     * 
     * @route POST /api/debate/stream-with-image
     */
    public function streamWithImage(Request $request, array $params): Response
    {
        $mode = $request->post('mode') ?? $request->query('mode', 'chat');
        
        // 验证 mode
        if (!in_array($mode, ['chat', 'debate'], true)) {
            $mode = 'chat';
        }

        // 检查是否有图片
        $imageDescription = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            try {
                $analyzer = new ImageAnalyzer();
                $result = $analyzer->analyzeUpload($_FILES['image']);
                
                if ($result['success']) {
                    // 构建图片上下文
                    $imageDescription = $this->buildImageContext($result);
                }
            } catch (\Throwable $e) {
                // 图片分析失败不阻塞，记录日志
                error_log("[DebateController] Image analysis failed: " . $e->getMessage());
            }
        }

        // 获取文字话题（可选）
        $textTopic = trim($request->post('topic') ?? $request->query('topic', ''));
        
        // 组合话题
        $topic = $this->buildCombinedTopic($textTopic, $imageDescription);
        
        if (empty($topic)) {
            return Response::error('请提供话题或图片', 400);
        }

        // SSE 响应
        try {
            SSEResponse::init();

            // 发送图片分析结果（如果有）
            if (!empty($imageDescription)) {
                SSEResponse::send('image_analyzed', [
                    'description' => $imageDescription,
                ]);
            }

            $service = new DebateService($mode);
            $service->run($topic);

            SSEResponse::end();

        } catch (\Throwable $e) {
            SSEResponse::error('系统错误: ' . $e->getMessage());
            SSEResponse::end();
        }

        return Response::sse();
    }

    /**
     * 构建图片上下文描述
     */
    private function buildImageContext(array $analysis): string
    {
        $parts = [];
        
        if (!empty($analysis['description'])) {
            $parts[] = "【图片内容】" . $analysis['description'];
        }
        
        if (!empty($analysis['elements'])) {
            $parts[] = "【关键元素】" . implode('、', array_slice($analysis['elements'], 0, 5));
        }
        
        if (!empty($analysis['emotion'])) {
            $parts[] = "【情感氛围】" . $analysis['emotion'];
        }
        
        return implode("\n", $parts);
    }

    /**
     * 组合文字话题和图片描述
     */
    private function buildCombinedTopic(string $textTopic, string $imageDescription): string
    {
        if (!empty($textTopic) && !empty($imageDescription)) {
            return "用户发起话题：{$textTopic}\n\n用户同时分享了一张图片：\n{$imageDescription}\n\n请大家围绕这个话题和图片进行讨论。";
        }
        
        if (!empty($imageDescription)) {
            return "用户分享了一张图片：\n{$imageDescription}\n\n请大家看看这张图片，分享你们的看法和感受。";
        }
        
        return $textTopic;
    }
}
