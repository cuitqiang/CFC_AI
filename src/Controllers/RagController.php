<?php
/**
 * RAG 知识库控制器
 * 
 * CFC V7.7 规范：
 * - 职责：仅负责 HTTP 层的参数解析与响应格式化
 * - 原则：贫血模式，所有业务逻辑委托给 Service
 * - 依赖：通过构造函数注入，绝不自己 new
 * 
 * 核心接口：
 * 1. POST   /api/rag/upload        - 文档上传与向量化
 * 2. POST   /api/rag/search        - 语义搜索
 * 3. GET    /api/rag/documents     - 文档列表
 * 4. DELETE /api/rag/documents/{h} - 删除文档
 * 5. GET    /api/rag/stats         - 统计信息
 * 6. GET    /api/rag/capabilities  - 系统能力
 * 
 * @package App\Controllers
 * @version 7.7
 * @author CFC Framework
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use Services\AI\Core\RAG\VectorService;
use Services\AI\Document\SmartDocumentProcessor;

/**
 * CFC V7.7 标准控制器
 * 
 * 职责：仅负责 HTTP 层的参数解析与响应格式化
 * 禁止：直接操作数据库、读取环境变量、内部 new 服务
 */
class RagController
{
    /**
     * 构造函数 - 依赖注入
     * 
     * CFC V7.7 规范：所有服务必须通过构造函数注入
     * 
     * @param VectorService $vectorService 向量存储服务
     * @param SmartDocumentProcessor $processor 智能文档处理器
     */
    public function __construct(
        protected VectorService $vectorService,
        protected SmartDocumentProcessor $processor
    ) {}

    /**
     * 上传文档
     * 
     * CFC V7.7 规范：
     * - Controller 只负责提取参数
     * - 具体处理逻辑委托给 Processor
     * 
     * @route POST /api/rag/upload
     * @param Request $request HTTP 请求
     * @param array $params 路由参数
     * @return Response JSON 响应
     */
    public function upload(Request $request, array $params = []): Response
    {
        // 1. 获取文件（Controller 仅负责提取数据）
        $file = $_FILES['file'] ?? null;
        if (!$file) {
            return Response::error('请上传文件', 400);
        }

        // 2. 获取参数
        $forceMultimodal = $request->input('multimodal') === 'true' 
                        || $request->input('multimodal') === true;

        try {
            // 3. 转发给 Processor（脏活累活它干）
            $result = $this->processor->handleUpload($file, $forceMultimodal);

            return Response::success($result, '文档处理成功');

        } catch (\InvalidArgumentException $e) {
            // 业务错误（参数校验失败等）
            return Response::error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            // 系统错误
            return Response::error('系统内部错误: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 语义搜索
     * 
     * CFC V7.7 规范：直接调用 Service，不做额外逻辑
     * 
     * @route POST /api/rag/search
     * @param Request $request HTTP 请求
     * @param array $params 路由参数
     * @return Response JSON 响应
     */
    public function search(Request $request, array $params = []): Response
    {
        $query = trim($request->input('query', ''));
        $topK = (int) $request->input('top_k', 5);

        if (empty($query)) {
            return Response::error('查询内容不能为空', 400);
        }

        try {
            // 直接调用 Service
            $results = $this->vectorService->search($query, min(max($topK, 1), 20));

            return Response::success([
                'query' => $query,
                'count' => count($results),
                'results' => $results,
            ]);

        } catch (\Throwable $e) {
            return Response::error('搜索失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取文档列表
     * 
     * @route GET /api/rag/documents
     * @param Request $request HTTP 请求
     * @param array $params 路由参数
     * @return Response JSON 响应
     */
    public function listDocuments(Request $request, array $params = []): Response
    {
        try {
            $documents = $this->vectorService->listDocuments();
            $stats = $this->vectorService->getStats();

            return Response::success([
                'stats' => $stats,
                'documents' => $documents,
            ]);

        } catch (\Throwable $e) {
            return Response::error('获取失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 删除文档
     * 
     * @route DELETE /api/rag/documents/{hash}
     * @param Request $request HTTP 请求
     * @param array $params 路由参数（包含 hash）
     * @return Response JSON 响应
     */
    public function deleteDocument(Request $request, array $params = []): Response
    {
        $hash = $params['hash'] ?? $request->input('hash', '');

        if (empty($hash)) {
            return Response::error('缺少 hash 参数', 400);
        }

        try {
            $deleted = $this->vectorService->deleteDocument($hash);

            return $deleted > 0
                ? Response::success(['deleted_chunks' => $deleted], '删除成功')
                : Response::error('文档不存在', 404);

        } catch (\Throwable $e) {
            return Response::error('删除失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取统计信息
     * 
     * @route GET /api/rag/stats
     * @param Request $request HTTP 请求
     * @param array $params 路由参数
     * @return Response JSON 响应
     */
    public function stats(Request $request, array $params = []): Response
    {
        try {
            $stats = $this->vectorService->getStats();
            return Response::success($stats);

        } catch (\Throwable $e) {
            return Response::error('获取失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取系统能力
     * 
     * @route GET /api/rag/capabilities
     * @param Request $request HTTP 请求
     * @param array $params 路由参数
     * @return Response JSON 响应
     */
    public function capabilities(Request $request, array $params = []): Response
    {
        try {
            return Response::success($this->processor->getCapabilities());

        } catch (\Throwable $e) {
            return Response::error($e->getMessage(), 500);
        }
    }
}
