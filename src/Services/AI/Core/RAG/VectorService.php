<?php
declare(strict_types=1);

namespace Services\AI\Core\RAG;

use Services\AI\Providers\EmbeddingProvider;

/**
 * RAG 向量服务
 * 
 * 负责文档切片、向量化、存储和检索
 * 支持 MySQL (JSON) 和 PostgreSQL (pgvector) 两种后端
 * 当远程 Embedding API 不可用时，自动降级使用本地 TF-IDF
 */
class VectorService
{
    private \PDO $pdo;
    private ?EmbeddingProvider $embeddingProvider;
    private LocalEmbedding $localEmbedding;
    private int $chunkSize;
    private int $chunkOverlap;
    private string $backend; // 'mysql' or 'pgsql'
    private bool $useLocalEmbedding = false;

    public function __construct(
        \PDO $pdo,
        ?EmbeddingProvider $embeddingProvider = null,
        int $chunkSize = 512,
        int $chunkOverlap = 50,
        string $backend = 'mysql',
        bool $forceLocalEmbedding = false
    ) {
        $this->pdo = $pdo;
        $this->embeddingProvider = $embeddingProvider;
        $this->localEmbedding = new LocalEmbedding(512);
        $this->chunkSize = $chunkSize;
        $this->chunkOverlap = $chunkOverlap;
        $this->backend = $backend;
        $this->useLocalEmbedding = $forceLocalEmbedding || ($embeddingProvider === null);
    }
    
    /**
     * 获取文本的向量表示
     * 先尝试远程 API，失败则使用本地算法
     */
    private function getEmbedding(string $text): array
    {
        if ($this->useLocalEmbedding) {
            return $this->localEmbedding->embed($text);
        }
        
        try {
            $response = $this->embeddingProvider->embed($text);
            if (isset($response['data'][0]['embedding'])) {
                return $response['data'][0]['embedding'];
            }
            throw new \RuntimeException('Invalid embedding response');
        } catch (\Throwable $e) {
            // 远程 API 失败，切换到本地
            error_log("Remote embedding failed, falling back to local: " . $e->getMessage());
            $this->useLocalEmbedding = true;
            return $this->localEmbedding->embed($text);
        }
    }
    
    /**
     * 检查是否正在使用本地 embedding
     */
    public function isUsingLocalEmbedding(): bool
    {
        return $this->useLocalEmbedding;
    }

    /**
     * 处理上传的文件
     * 
     * @param string $filePath 文件路径
     * @param string $fileName 原始文件名
     * @return array 处理结果
     */
    public function processFile(string $filePath, string $fileName): array
    {
        // 1. 提取文本内容
        $content = $this->extractText($filePath, $fileName);
        if (empty($content)) {
            throw new \RuntimeException('无法提取文件内容');
        }

        // 2. 计算文件哈希（去重）
        $docHash = md5_file($filePath);

        // 3. 检查是否已存在
        $existing = $this->findByHash($docHash);
        if (!empty($existing)) {
            return [
                'status' => 'exists',
                'message' => '文件已存在',
                'doc_hash' => $docHash,
                'chunks' => count($existing),
            ];
        }

        // 4. 文本切片
        $chunks = $this->splitText($content);

        // 5. 向量化并存储
        $stored = 0;
        foreach ($chunks as $index => $chunk) {
            try {
                $embedding = $this->getEmbedding($chunk);
                $this->storeChunk($docHash, $fileName, $filePath, $index, count($chunks), $chunk, $embedding);
                $stored++;
            } catch (\Throwable $e) {
                // 记录错误但继续处理
                error_log("Embedding failed for chunk {$index}: " . $e->getMessage());
            }
        }

        return [
            'status' => 'success',
            'message' => "成功处理 {$stored} 个切片",
            'doc_hash' => $docHash,
            'chunks' => $stored,
            'total_chunks' => count($chunks),
            'embedding_mode' => $this->useLocalEmbedding ? 'local' : 'api',
        ];
    }

    /**
     * 语义搜索
     * 
     * @param string $query 查询文本
     * @param int $topK 返回数量
     * @return array 搜索结果
     */
    public function search(string $query, int $topK = 5): array
    {
        // 1. 将查询文本向量化
        $queryEmbedding = $this->getEmbedding($query);

        // 2. 执行相似度搜索
        if ($this->backend === 'pgsql') {
            return $this->searchPgvector($queryEmbedding, $topK);
        } else {
            return $this->searchMysql($queryEmbedding, $topK);
        }
    }

    /**
     * MySQL 相似度搜索（暴力计算余弦相似度）
     */
    private function searchMysql(array $queryEmbedding, int $topK): array
    {
        // 获取所有向量
        $stmt = $this->pdo->query("SELECT id, doc_hash, file_name, chunk_index, content, embedding FROM ai_vectors");
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $results = [];
        foreach ($rows as $row) {
            $storedEmbedding = json_decode($row['embedding'], true);
            if (!$storedEmbedding) continue;

            $similarity = $this->cosineSimilarity($queryEmbedding, $storedEmbedding);
            $results[] = [
                'id' => $row['id'],
                'doc_hash' => $row['doc_hash'],
                'file_name' => $row['file_name'],
                'chunk_index' => $row['chunk_index'],
                'content' => $row['content'],
                'similarity' => $similarity,
            ];
        }

        // 按相似度降序排序
        usort($results, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice($results, 0, $topK);
    }

    /**
     * pgvector 相似度搜索（余弦相似度）
     */
    private function searchPgvector(array $queryEmbedding, int $topK): array
    {
        $embeddingStr = '[' . implode(',', $queryEmbedding) . ']';
        
        // pgvector 使用 <=> 运算符计算余弦距离，1 - 距离 = 相似度
        $sql = "
            SELECT id, doc_hash, file_name, chunk_index, content,
                   1 - (embedding <=> ?) as similarity
            FROM ai_vectors
            WHERE embedding IS NOT NULL
            ORDER BY embedding <=> ?
            LIMIT ?
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$embeddingStr, $embeddingStr, $topK]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * 计算余弦相似度
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = 0;
        $normA = 0;
        $normB = 0;

        $len = min(count($a), count($b));
        for ($i = 0; $i < $len; $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $normA = sqrt($normA);
        $normB = sqrt($normB);

        if ($normA == 0 || $normB == 0) {
            return 0;
        }

        return $dotProduct / ($normA * $normB);
    }

    /**
     * 提取文本内容
     * 
     * 优先使用实际文件路径的扩展名（支持临时文件处理）
     * 如果是 .txt 则直接读取，否则根据原始文件名判断
     */
    private function extractText(string $filePath, string $fileName): string
    {
        // 优先检查实际文件扩展名（支持 SmartDocumentProcessor 的临时 .txt 文件）
        $fileExt = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($fileExt === 'txt') {
            return file_get_contents($filePath);
        }
        
        // 否则根据原始文件名判断
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        switch ($ext) {
            case 'txt':
            case 'md':
                return file_get_contents($filePath);

            case 'pdf':
                return $this->extractPdfText($filePath);

            case 'docx':
                return $this->extractDocxText($filePath);

            default:
                throw new \RuntimeException("不支持的文件格式: {$ext}");
        }
    }

    /**
     * 提取 PDF 文本（使用 pdftotext 命令）
     */
    private function extractPdfText(string $filePath): string
    {
        // 方法1: 使用 pdftotext (需要安装 poppler-utils)
        $output = [];
        $returnCode = 0;
        exec("pdftotext -enc UTF-8 " . escapeshellarg($filePath) . " -", $output, $returnCode);

        if ($returnCode === 0 && !empty($output)) {
            return implode("\n", $output);
        }

        // 方法2: 使用 PHP 库 (如果安装了 smalot/pdfparser)
        if (class_exists('\Smalot\PdfParser\Parser')) {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($filePath);
            return $pdf->getText();
        }

        throw new \RuntimeException('无法解析 PDF，请安装 poppler-utils 或 smalot/pdfparser');
    }

    /**
     * 提取 DOCX 文本
     */
    private function extractDocxText(string $filePath): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new \RuntimeException('无法打开 DOCX 文件');
        }

        $content = $zip->getFromName('word/document.xml');
        $zip->close();

        if (!$content) {
            throw new \RuntimeException('无法读取 DOCX 内容');
        }

        // 移除 XML 标签，保留文本
        $content = strip_tags($content);
        return trim($content);
    }

    /**
     * 文本切片
     */
    private function splitText(string $text): array
    {
        $chunks = [];
        $text = preg_replace('/\s+/', ' ', trim($text));
        $length = mb_strlen($text);

        // 如果文本太短，直接返回
        if ($length <= $this->chunkSize) {
            return [trim($text)];
        }

        $start = 0;
        while ($start < $length) {
            $chunk = mb_substr($text, $start, $this->chunkSize);
            $chunkLen = mb_strlen($chunk);
            
            // 尝试在句号或换行处断开
            if ($start + $this->chunkSize < $length) {
                $lastPeriod = mb_strrpos($chunk, '。');
                $lastNewline = mb_strrpos($chunk, "\n");
                $breakPoint = max($lastPeriod ?: 0, $lastNewline ?: 0);
                
                if ($breakPoint > $this->chunkSize * 0.5) {
                    $chunk = mb_substr($chunk, 0, $breakPoint + 1);
                    $chunkLen = mb_strlen($chunk);
                }
            }

            if (!empty(trim($chunk))) {
                $chunks[] = trim($chunk);
            }

            // 防止死循环：确保每次至少前进 1 个字符
            $advance = max(1, $chunkLen - $this->chunkOverlap);
            $start += $advance;
        }

        return $chunks;
    }

    /**
     * 存储切片
     */
    private function storeChunk(
        string $docHash,
        string $fileName,
        string $filePath,
        int $chunkIndex,
        int $chunkTotal,
        string $content,
        array $embedding
    ): void {
        if ($this->backend === 'pgsql') {
            // PostgreSQL pgvector 格式
            $embeddingStr = '[' . implode(',', $embedding) . ']';
            $sql = "
                INSERT INTO ai_vectors 
                (doc_hash, file_name, file_path, chunk_index, total_chunks, content, embedding, metadata)
                VALUES (?, ?, ?, ?, ?, ?, ?::vector, ?::jsonb)
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $docHash,
                $fileName,
                $filePath,
                $chunkIndex,
                $chunkTotal,
                $content,
                $embeddingStr,
                json_encode(['embedding_mode' => $this->useLocalEmbedding ? 'local' : 'api']),
            ]);
        } else {
            // MySQL JSON 格式
            $sql = "
                INSERT INTO ai_vectors 
                (doc_hash, file_name, file_path, chunk_index, total_chunks, content, embedding, metadata)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $docHash,
                $fileName,
                $filePath,
                $chunkIndex,
                $chunkTotal,
                $content,
                json_encode($embedding),
                json_encode(['embedding_mode' => $this->useLocalEmbedding ? 'local' : 'api']),
            ]);
        }
    }

    /**
     * 根据哈希查找文档
     */
    private function findByHash(string $docHash): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM ai_vectors WHERE doc_hash = ?");
        $stmt->execute([$docHash]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * 获取所有文档
     */
    public function listDocuments(): array
    {
        $sql = "
            SELECT doc_hash, file_name, COUNT(*) as chunks, MIN(created_at) as uploaded_at
            FROM ai_vectors
            GROUP BY doc_hash, file_name
            ORDER BY uploaded_at DESC
        ";
        return $this->pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * 删除文档
     */
    public function deleteDocument(string $docHash): int
    {
        $stmt = $this->pdo->prepare("DELETE FROM ai_vectors WHERE doc_hash = ?");
        $stmt->execute([$docHash]);
        return $stmt->rowCount();
    }

    /**
     * 获取统计信息
     */
    public function getStats(): array
    {
        $stats = [];
        
        $stats['total_documents'] = (int)$this->pdo->query(
            "SELECT COUNT(DISTINCT doc_hash) FROM ai_vectors"
        )->fetchColumn();
        
        $stats['total_chunks'] = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM ai_vectors"
        )->fetchColumn();
        
        // PostgreSQL 使用 LENGTH(), MySQL 使用 CHAR_LENGTH()
        $lenFunc = $this->backend === 'pgsql' ? 'LENGTH' : 'CHAR_LENGTH';
        $stats['total_characters'] = (int)$this->pdo->query(
            "SELECT COALESCE(SUM({$lenFunc}(content)), 0) FROM ai_vectors"
        )->fetchColumn();
        
        $stats['backend'] = $this->backend;
        $stats['embedding_mode'] = $this->useLocalEmbedding ? 'local' : 'api';

        return $stats;
    }
}
