<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\SSEResponse;
use Services\AI\Cuige\CuigeService;
use Services\AI\Cuige\CuigeConfig;

/**
 * 崔哥 AI 控制器
 * 
 * CFC V7.7 规范：
 * - 职责：接收参数、调用 Service、返回 Response
 * - 严禁在此编写业务逻辑
 * - 使用依赖注入获取 Service
 */
class CuigeController
{
    public function __construct(
        protected CuigeService $cuigeService,
        protected CuigeConfig $config
    ) {}

    /**
     * 健康检查
     * 
     * @route GET /api/cuige/health
     */
    public function health(Request $request, array $params): Response
    {
        return Response::success([
            'message' => '崔哥在线！V3 CFC架构版',
            'time' => date('Y-m-d H:i:s'),
            'version' => '3.0.0',
            'features' => ['短期记忆', '长期记忆', '用户画像', '智能压缩', 'Token监控']
        ]);
    }

    /**
     * 聊天接口
     * 
     * @route POST /api/cuige/chat
     */
    public function chat(Request $request, array $params): Response
    {
        $message = trim($request->json('message', ''));
        $sessionId = $request->json('session_id', 'default_' . date('Ymd'));
        $userId = $request->json('user_id', 'guest');

        if (empty($message)) {
            return Response::error('消息不能为空', 400);
        }

        try {
            $result = $this->cuigeService->chat($message, $sessionId, $userId);
            return Response::success($result);
        } catch (\Throwable $e) {
            return Response::error($e->getMessage(), 500);
        }
    }

    /**
     * 流式聊天接口 (SSE)
     * 
     * @route GET /api/cuige/stream
     */
    public function stream(Request $request, array $params): Response
    {
        $message = trim($request->query('message', ''));
        $sessionId = $request->query('session_id', 'default_' . date('Ymd'));
        $userId = $request->query('user_id', 'guest');

        if (empty($message)) {
            SSEResponse::init();
            SSEResponse::error('消息不能为空');
            SSEResponse::end();
            return Response::sse();
        }

        try {
            // 初始化 SSE 连接
            SSEResponse::init();
            
            // 发送开始事件
            SSEResponse::send('start', [
                'session_id' => $sessionId,
                'timestamp' => time()
            ]);

            // 流式调用 Service
            $result = $this->cuigeService->streamChat(
                $message, 
                $sessionId, 
                $userId,
                function(array $data) {
                    // 将 Service 的回调转为 SSE 事件
                    if ($data['type'] === 'chunk') {
                        SSEResponse::send('chunk', ['content' => $data['content']]);
                    } elseif ($data['type'] === 'status') {
                        SSEResponse::send('status', ['message' => $data['message']]);
                    }
                }
            );

            // 发送完成事件
            SSEResponse::send('complete', [
                'context_info' => $result['context_info'] ?? []
            ]);
            
            SSEResponse::end();

        } catch (\Throwable $e) {
            if (!headers_sent()) {
                SSEResponse::init();
            }
            SSEResponse::error($e->getMessage());
            SSEResponse::end();
        }

        return Response::sse();
    }

    /**
     * 获取历史记录
     * 
     * @route GET /api/cuige/history
     */
    public function history(Request $request, array $params): Response
    {
        $sessionId = $request->query('session_id', '');

        if (empty($sessionId)) {
            return Response::error('需要 session_id', 400);
        }

        $limit = (int)$request->query('limit', '50');

        try {
            $result = $this->cuigeService->getHistory($sessionId, $limit);
            return Response::success($result);
        } catch (\Throwable $e) {
            return Response::error($e->getMessage(), 500);
        }
    }

    /**
     * 获取会话列表
     * 
     * @route GET /api/cuige/sessions
     */
    public function sessions(Request $request, array $params): Response
    {
        $userId = $request->query('user_id', 'default');
        $limit = (int)$request->query('limit', '20');

        try {
            $result = $this->cuigeService->getSessions($userId, $limit);
            return Response::success($result);
        } catch (\Throwable $e) {
            return Response::error($e->getMessage(), 500);
        }
    }

    /**
     * 获取用户记忆
     * 
     * @route GET /api/cuige/memories
     */
    public function memories(Request $request, array $params): Response
    {
        $userId = $request->query('user_id', 'guest');

        try {
            $result = $this->cuigeService->getMemories($userId);
            return Response::success($result);
        } catch (\Throwable $e) {
            return Response::error($e->getMessage(), 500);
        }
    }

    /**
     * 获取用户画像
     * 
     * @route GET /api/cuige/profile
     */
    public function profile(Request $request, array $params): Response
    {
        $userId = $request->query('user_id', 'guest');

        try {
            $result = $this->cuigeService->getProfile($userId);
            return Response::success($result);
        } catch (\Throwable $e) {
            return Response::error($e->getMessage(), 500);
        }
    }

    /**
     * 获取上下文状态
     * 
     * @route GET /api/cuige/context
     */
    public function contextStatus(Request $request, array $params): Response
    {
        $sessionId = $request->query('session_id', '');
        $userId = $request->query('user_id', 'guest');

        if (empty($sessionId)) {
            return Response::error('需要 session_id', 400);
        }

        try {
            $result = $this->cuigeService->getContextStatus($sessionId, $userId);
            return Response::success($result);
        } catch (\Throwable $e) {
            return Response::error($e->getMessage(), 500);
        }
    }

    /**
     * TTS 语音合成
     * 
     * @route POST /api/cuige/tts
     */
    public function tts(Request $request, array $params): Response
    {
        $text = trim($request->json('text', ''));

        if (empty($text)) {
            return Response::error('文本不能为空', 400);
        }

        try {
            $result = $this->cuigeService->textToSpeech($text);
            return Response::success($result);
        } catch (\Throwable $e) {
            return Response::error($e->getMessage(), 502);
        }
    }
}
