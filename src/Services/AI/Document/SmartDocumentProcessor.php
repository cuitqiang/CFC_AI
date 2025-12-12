<?php
/**
 * 智能文档处理器
 * 
 * CFC V7.7 规范：
 * - 职责：文档处理业务逻辑（文件校验、格式转换、策略选择、向量化）
 * - 依赖：通过构造函数注入 VectorService
 * - 禁止：直接读取环境变量，由 Bootstrap 统一管理
 * 
 * 处理策略：
 * - TEXT       : 纯文本直接提取
 * - PDF_TEXT   : PDF 文本层提取（pdftotext）
 * - OCR        : 图像 OCR（tesseract）
 * - MULTIMODAL : 多模态 AI 理解（deepseek-v3）
 * 
 * @package Services\AI\Document
 * @version 7.7
 * @author CFC Framework
 */
declare(strict_types=1);

namespace Services\AI\Document;

use Services\AI\Bootstrap;
use Services\AI\Core\RAG\VectorService;
use Services\AI\Core\RAG\LocalEmbedding;
use Services\AI\Document\TableExtractor;

class SmartDocumentProcessor
{
    // 处理策略常量
    public const STRATEGY_TEXT = 'text';
    public const STRATEGY_PDF_TEXT = 'pdf_text';
    public const STRATEGY_OCR = 'ocr';
    public const STRATEGY_MULTIMODAL = 'multimodal';
    public const STRATEGY_TABLE = 'table';
    public const STRATEGY_PDF_TABLE = 'pdf_table';
    
    // 支持的文件类型 - 扩展支持表格
    private const ALLOWED_EXTENSIONS = ['pdf', 'txt', 'md', 'png', 'jpg', 'jpeg', 'webp', 'xlsx', 'xls', 'csv', 'tsv'];
    private const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB (Excel 文件通常较大)
    
    // 表格提取器
    private ?TableExtractor $tableExtractor = null;
    
    /**
     * 构造函数 - 依赖注入
     * 
     * CFC V7.7 规范：服务通过构造函数注入
     * 
     * @param VectorService $vectorService 向量存储服务
     */
    public function __construct(
        protected VectorService $vectorService
    ) {
        // 初始化框架（确保 Config 已加载）
        // CFC V7.7：使用 APP_ROOT 常量确保路径正确
        if (defined('APP_ROOT') && file_exists(APP_ROOT . '/.env')) {
            Bootstrap::initialize(APP_ROOT . '/.env');
        }
        
        // 初始化表格提取器
        $this->tableExtractor = new TableExtractor();
    }
    
    /**
     * 处理上传文件（Controller 调用入口）
     * 
     * CFC V7.7 规范：
     * - 封装所有文件处理逻辑
     * - 包括：校验、移动、处理、向量化
     * - 抛出 InvalidArgumentException 表示业务错误
     * 
     * @param array $file $_FILES['file'] 数组
     * @param bool $forceMultimodal 是否强制多模态
     * @return array 处理结果
     * @throws \InvalidArgumentException 参数校验失败
     * @throws \RuntimeException 系统错误
     */
    public function handleUpload(array $file, bool $forceMultimodal = false): array
    {
        // 1. 校验上传错误
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('文件上传失败: ' . $this->getUploadError($file['error']));
        }
        
        // 2. 校验文件类型
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS)) {
            throw new \InvalidArgumentException(
                '不支持的文件格式，支持: ' . implode(', ', self::ALLOWED_EXTENSIONS)
            );
        }
        
        // 3. 校验文件大小
        if ($file['size'] > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException('文件大小不能超过 20MB');
        }
        
        // 4. 处理文档
        return $this->process($file['tmp_name'], $file['name'], $forceMultimodal);
    }
    
    /**
     * 获取上传错误描述
     */
    private function getUploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE => '文件超过 php.ini 限制',
            UPLOAD_ERR_FORM_SIZE => '文件超过表单限制',
            UPLOAD_ERR_PARTIAL => '文件上传不完整',
            UPLOAD_ERR_NO_FILE => '没有文件被上传',
            UPLOAD_ERR_NO_TMP_DIR => '缺少临时目录',
            UPLOAD_ERR_CANT_WRITE => '无法写入磁盘',
            UPLOAD_ERR_EXTENSION => '扩展阻止了上传',
            default => '未知错误',
        };
    }
    
    /**
     * 智能处理文档
     */
    public function process(string $filePath, string $fileName, bool $forceMultimodal = false): array
    {
        $startTime = microtime(true);
        
        // 1. 分析文件
        $fileInfo = $this->analyzeFile($filePath, $fileName);
        
        // 2. 选择策略
        $strategy = $forceMultimodal ? self::STRATEGY_MULTIMODAL : $this->selectStrategy($fileInfo);
        
        // 3. 执行处理
        $result = match($strategy) {
            self::STRATEGY_TEXT => $this->processText($filePath),
            self::STRATEGY_PDF_TEXT => $this->processPdfText($filePath),
            self::STRATEGY_OCR => $this->processOcr($filePath, $fileInfo),
            self::STRATEGY_MULTIMODAL => $this->processMultimodal($filePath, $fileInfo),
            self::STRATEGY_TABLE => $this->processTable($filePath, $fileName),
            self::STRATEGY_PDF_TABLE => $this->processPdfWithTables($filePath, $fileInfo),
            default => throw new \Exception("不支持的策略: {$strategy}")
        };
        
        $result['file_name'] = $fileName;
        $result['strategy'] = $strategy;
        $result['processing_time'] = round(microtime(true) - $startTime, 2) . 's';
        
        // 4. 存入向量库
        if (!empty($result['text'])) {
            $chunks = $this->saveToVectorStore($result['text'], $fileName, $fileInfo);
            $result['chunks'] = $chunks;
        }
        
        return $result;
    }
    
    /**
     * 分析文件特征
     */
    private function analyzeFile(string $filePath, string $fileName): array
    {
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $info = [
            'extension' => $ext,
            'size' => filesize($filePath),
            'is_image' => in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp']),
            'is_pdf' => $ext === 'pdf',
            'is_text' => in_array($ext, ['txt', 'md', 'json']),
            'is_table' => TableExtractor::isTableFile($fileName),
        ];
        
        // PDF 特殊检测
        if ($info['is_pdf']) {
            $info['pdf_has_text'] = $this->pdfHasTextLayer($filePath);
            $info['pdf_pages'] = $this->getPdfPageCount($filePath);
            // 检测 PDF 是否包含表格（启发式）
            $info['pdf_has_table'] = $this->pdfHasTable($filePath);
        }
        
        return $info;
    }
    
    /**
     * 选择处理策略
     */
    private function selectStrategy(array $info): string
    {
        // 表格文件 → 专用表格解析器
        if ($info['is_table'] ?? false) {
            return self::STRATEGY_TABLE;
        }
        
        if ($info['is_text']) {
            return self::STRATEGY_TEXT;
        }
        
        if ($info['is_image']) {
            return $this->hasMultimodalCapability() ? self::STRATEGY_MULTIMODAL : self::STRATEGY_OCR;
        }
        
        if ($info['is_pdf']) {
            // 检测到表格的 PDF → 优先多模态提取表格
            if (($info['pdf_has_table'] ?? false) && $this->hasMultimodalCapability()) {
                return self::STRATEGY_PDF_TABLE;
            }
            // 有文本层 → 提取文本
            if ($info['pdf_has_text']) {
                return self::STRATEGY_PDF_TEXT;
            }
            // 无文本层（扫描版）→ 多模态 或 OCR
            return $this->hasMultimodalCapability() ? self::STRATEGY_MULTIMODAL : self::STRATEGY_OCR;
        }
        
        return self::STRATEGY_TEXT;
    }
    
    /**
     * 处理纯文本
     */
    private function processText(string $filePath): array
    {
        $content = file_get_contents($filePath);
        
        // 检测编码
        $encoding = mb_detect_encoding($content, ['UTF-8', 'GBK', 'GB2312'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }
        
        return ['text' => $content, 'method' => 'direct'];
    }
    
    /**
     * 提取 PDF 文本层
     */
    private function processPdfText(string $filePath): array
    {
        $text = '';
        
        // 使用 pdftotext
        if ($this->commandExists('pdftotext')) {
            $output = tempnam(sys_get_temp_dir(), 'pdf_');
            exec("pdftotext -enc UTF-8 " . escapeshellarg($filePath) . " " . escapeshellarg($output) . " 2>&1");
            
            if (file_exists($output)) {
                $text = file_get_contents($output);
                unlink($output);
            }
        }
        
        if (empty(trim($text))) {
            throw new \Exception('无法提取 PDF 文本，请使用多模态模式');
        }
        
        return ['text' => $text, 'method' => 'pdftotext'];
    }
    
    /**
     * OCR 处理
     */
    private function processOcr(string $filePath, array $info): array
    {
        $images = [];
        
        // PDF 转图片
        if ($info['is_pdf']) {
            $images = $this->pdfToImages($filePath);
        } else {
            $images = [$filePath];
        }
        
        $allText = '';
        foreach ($images as $img) {
            $allText .= $this->ocrImage($img) . "\n\n";
        }
        
        // 清理临时文件
        if ($info['is_pdf']) {
            foreach ($images as $img) {
                @unlink($img);
            }
        }
        
        return ['text' => trim($allText), 'method' => 'tesseract_ocr', 'pages' => count($images)];
    }
    
    /**
     * 多模态处理（使用框架内置 API）
     * 
     * CFC V7.7：使用配置的 vision_model 进行图像理解
     * 支持的多模态模型：gemini-2.5-pro, gpt-4o, gpt-4o-mini 等
     */
    private function processMultimodal(string $filePath, array $info): array
    {
        $images = [];
        
        // PDF 转图片
        if ($info['is_pdf']) {
            $images = $this->pdfToImages($filePath, 150);
        } else {
            $images = [$filePath];
        }
        
        $allText = '';
        
        // 从配置读取多模态 API（CFC V7.7：配置统一管理）
        $visionConfig = \Services\AI\Config::get('vision', []);
        $visionModel = $visionConfig['model'] ?? \Services\AI\Config::get('vision_model', 'gemini-2.5-pro');
        $visionApiKey = $visionConfig['api_key'] ?? '';
        $visionBaseUrl = $visionConfig['base_url'] ?? 'https://www.chataiapi.com/v1';
        
        foreach ($images as $index => $imagePath) {
            $pageNum = $index + 1;
            $totalPages = count($images);
            
            // 转 base64
            $imageData = base64_encode(file_get_contents($imagePath));
            $mimeType = mime_content_type($imagePath) ?: 'image/png';
            
            $prompt = <<<PROMPT
请分析这张文档图片（第 {$pageNum}/{$totalPages} 页）：

1. 提取所有文字内容，保持原有结构
2. 如果有图表，用文字描述其内容
3. 如果有表格，转换为 Markdown 格式
4. 保留标题层级和列表格式

直接输出提取的内容，不要添加说明。
PROMPT;

            try {
                // 直接调用视觉 API（独立配置）
                $response = $this->callVisionApi($visionBaseUrl, $visionApiKey, $visionModel, $prompt, $mimeType, $imageData);
                
                $pageText = $response['choices'][0]['message']['content'] ?? '';
                $allText .= "--- 第 {$pageNum} 页 ---\n{$pageText}\n\n";
                
            } catch (\Exception $e) {
                // 多模态失败，记录错误并降级到 OCR
                error_log("[SmartDocumentProcessor] Multimodal API failed: " . $e->getMessage());
                $allText .= "--- 第 {$pageNum} 页 (OCR) ---\n" . $this->ocrImage($imagePath) . "\n\n";
            }
        }
        
        // 清理临时文件
        if ($info['is_pdf']) {
            foreach ($images as $img) {
                @unlink($img);
            }
        }
        
        return ['text' => trim($allText), 'method' => 'multimodal_ai', 'pages' => count($images)];
    }
    
    /**
     * 直接调用视觉 API
     */
    private function callVisionApi(string $baseUrl, string $apiKey, string $model, string $prompt, string $mimeType, string $imageData): array
    {
        $ch = curl_init($baseUrl . '/chat/completions');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => $model,
            'messages' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $prompt],
                    ['type' => 'image_url', 'image_url' => [
                        'url' => "data:{$mimeType};base64,{$imageData}"
                    ]]
                ]
            ]],
            'max_tokens' => 4096,
            'temperature' => 0.1,
        ]));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("cURL error: $error");
        }

        $data = json_decode($response, true);
        
        if ($httpCode !== 200 || isset($data['error'])) {
            $errorMsg = $data['error']['message'] ?? "HTTP $httpCode";
            throw new \Exception("Vision API error: $errorMsg");
        }

        return $data;
    }

    /**
     * 存入向量库
     * 
     * CFC V7.7 规范：使用注入的 VectorService
     * 注意：传入的是已提取的文本，需要使用 .txt 扩展名
     */
    private function saveToVectorStore(string $text, string $fileName, array $metadata): int
    {
        // 保存到临时文件（使用 .txt 扩展名，因为内容已是纯文本）
        $tempFile = tempnam(sys_get_temp_dir(), 'doc_') . '.txt';
        file_put_contents($tempFile, $text);
        
        // 保留原始文件名作为文档标识，但使用 .txt 扩展名进行处理
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        $processFileName = $baseName . '_extracted.txt';
        
        try {
            // 使用注入的 vectorService（CFC V7.7：禁止内部 new）
            $result = $this->vectorService->processFile($tempFile, $fileName);
            return $result['chunks'] ?? 0;
        } finally {
            @unlink($tempFile);
        }
    }
    
    /**
     * PDF 转图片
     */
    private function pdfToImages(string $pdfPath, int $dpi = 150): array
    {
        $images = [];
        $tempDir = sys_get_temp_dir() . '/pdf_' . uniqid();
        @mkdir($tempDir, 0755, true);
        
        $prefix = $tempDir . '/page';
        
        // pdftoppm (推荐)
        if ($this->commandExists('pdftoppm')) {
            exec("pdftoppm -png -r {$dpi} " . escapeshellarg($pdfPath) . " " . escapeshellarg($prefix));
            $images = glob($tempDir . '/page*.png');
            sort($images);
        }
        // ImageMagick
        elseif ($this->commandExists('convert')) {
            exec("convert -density {$dpi} " . escapeshellarg($pdfPath) . " " . escapeshellarg($prefix . '-%03d.png'));
            $images = glob($tempDir . '/page*.png');
            sort($images);
        }
        
        return $images;
    }
    
    /**
     * OCR 单张图片
     */
    private function ocrImage(string $imagePath): string
    {
        if (!$this->commandExists('tesseract')) {
            throw new \Exception('Tesseract OCR 未安装');
        }
        
        $output = tempnam(sys_get_temp_dir(), 'ocr_');
        exec("tesseract " . escapeshellarg($imagePath) . " " . escapeshellarg($output) . " -l chi_sim+eng 2>&1");
        
        $text = '';
        if (file_exists($output . '.txt')) {
            $text = file_get_contents($output . '.txt');
            unlink($output . '.txt');
        }
        @unlink($output);
        
        return trim($text);
    }
    
    /**
     * 检测 PDF 是否有文本层
     */
    private function pdfHasTextLayer(string $filePath): bool
    {
        if (!$this->commandExists('pdftotext')) {
            return false;
        }
        
        $output = tempnam(sys_get_temp_dir(), 'pdf_');
        exec("pdftotext -f 1 -l 1 " . escapeshellarg($filePath) . " " . escapeshellarg($output) . " 2>&1");
        
        if (file_exists($output)) {
            $text = file_get_contents($output);
            unlink($output);
            return strlen(trim($text)) > 30;
        }
        
        return false;
    }
    
    /**
     * 获取 PDF 页数
     */
    private function getPdfPageCount(string $filePath): int
    {
        if ($this->commandExists('pdfinfo')) {
            exec("pdfinfo " . escapeshellarg($filePath) . " 2>&1", $output);
            foreach ($output as $line) {
                if (preg_match('/^Pages:\s*(\d+)/', $line, $m)) {
                    return (int)$m[1];
                }
            }
        }
        return 1;
    }
    
    /**
     * 检测 PDF 是否包含表格（启发式检测）
     */
    private function pdfHasTable(string $filePath): bool
    {
        if (!$this->commandExists('pdftotext')) {
            return false;
        }
        
        // 提取第一页文本，检测表格特征
        $output = tempnam(sys_get_temp_dir(), 'pdf_');
        exec("pdftotext -f 1 -l 1 -layout " . escapeshellarg($filePath) . " " . escapeshellarg($output) . " 2>&1");
        
        if (file_exists($output)) {
            $text = file_get_contents($output);
            unlink($output);
            
            // 启发式检测：
            // 1. 多个连续空格（表格列对齐）
            // 2. 重复的分隔符模式
            // 3. 数字和金额格式
            $hasAlignedSpaces = preg_match('/\S\s{3,}\S.*\S\s{3,}\S/', $text);
            $hasTableChars = preg_match('/[│├┤┬┴┼─|+\-]{5,}/', $text);
            $hasRepeatedPattern = preg_match('/(\d+[\.,]\d+\s+){3,}/', $text);
            
            return $hasAlignedSpaces || $hasTableChars || $hasRepeatedPattern;
        }
        
        return false;
    }
    
    /**
     * 处理表格文件（Excel/CSV）
     */
    private function processTable(string $filePath, string $fileName): array
    {
        if (!$this->tableExtractor) {
            $this->tableExtractor = new TableExtractor();
        }
        
        return $this->tableExtractor->process($filePath, $fileName);
    }
    
    /**
     * 处理包含表格的 PDF（多模态 + 表格提取）
     * 
     * 参考 Google Document AI / Azure Form Recognizer 设计
     */
    private function processPdfWithTables(string $filePath, array $info): array
    {
        $images = $this->pdfToImages($filePath, 200); // 提高 DPI 便于表格识别
        
        $allText = '';
        $tableCount = 0;
        
        // 从配置读取多模态 API
        $visionConfig = \Services\AI\Config::get('vision', []);
        $visionModel = $visionConfig['model'] ?? \Services\AI\Config::get('vision_model', 'gemini-2.5-pro');
        $visionApiKey = $visionConfig['api_key'] ?? '';
        $visionBaseUrl = $visionConfig['base_url'] ?? 'https://www.chataiapi.com/v1';
        
        foreach ($images as $index => $imagePath) {
            $pageNum = $index + 1;
            $totalPages = count($images);
            
            $imageData = base64_encode(file_get_contents($imagePath));
            $mimeType = mime_content_type($imagePath) ?: 'image/png';
            
            // 针对表格优化的 Prompt
            $prompt = <<<PROMPT
请分析这张文档图片（第 {$pageNum}/{$totalPages} 页），特别注意表格内容：

**文字提取规则：**
1. 提取所有可见文字，保持原有层级结构
2. 标题使用 Markdown 格式（# ## ###）

**表格处理规则：**
1. 识别所有表格，转换为标准 Markdown 表格格式
2. 保留表格的完整结构（表头、分隔线、数据行）
3. 数字对齐，保留小数位
4. 合并单元格用适当方式表示
5. 在表格前添加表格标题或描述

**示例格式：**
| 项目 | 数量 | 单价 | 金额 |
| --- | --- | --- | --- |
| 产品A | 100 | 50.00 | 5000.00 |

直接输出提取的内容，格式清晰，不要添加额外说明。
PROMPT;

            try {
                $response = $this->callVisionApi($visionBaseUrl, $visionApiKey, $visionModel, $prompt, $mimeType, $imageData);
                
                $pageText = $response['choices'][0]['message']['content'] ?? '';
                
                // 检测是否提取到表格
                if (preg_match('/\|.*\|.*\|/', $pageText)) {
                    $tableCount++;
                }
                
                $allText .= "--- 第 {$pageNum} 页 ---\n{$pageText}\n\n";
                
            } catch (\Exception $e) {
                error_log("[SmartDocumentProcessor] PDF Table extraction failed: " . $e->getMessage());
                // 降级到 OCR
                $allText .= "--- 第 {$pageNum} 页 (OCR) ---\n" . $this->ocrImage($imagePath) . "\n\n";
            }
        }
        
        // 清理临时文件
        foreach ($images as $img) {
            @unlink($img);
        }
        
        return [
            'text' => trim($allText),
            'method' => 'pdf_table_extraction',
            'pages' => count($images),
            'tables_detected' => $tableCount,
        ];
    }
    
    /**
     * 检查是否有多模态能力
     */
    private function hasMultimodalCapability(): bool
    {
        // 框架已配置 API，支持多模态
        return true;
    }
    
    /**
     * 检查命令是否存在
     */
    private function commandExists(string $cmd): bool
    {
        return !empty(trim(shell_exec("which {$cmd} 2>/dev/null") ?? ''));
    }
    
    /**
     * 获取系统能力
     */
    public function getCapabilities(): array
    {
        return [
            'text_extraction' => true,
            'pdf_text' => $this->commandExists('pdftotext'),
            'pdf_to_image' => $this->commandExists('pdftoppm') || $this->commandExists('convert'),
            'pdf_table' => true, // 支持 PDF 表格提取
            'ocr' => $this->commandExists('tesseract'),
            'multimodal' => true, // 使用框架内置 API
            'table_extraction' => true, // 支持 Excel/CSV
            'supported_formats' => self::ALLOWED_EXTENSIONS,
            'max_file_size' => self::MAX_FILE_SIZE,
            'tools' => [
                'pdftotext' => $this->commandExists('pdftotext'),
                'pdftoppm' => $this->commandExists('pdftoppm'),
                'pdfinfo' => $this->commandExists('pdfinfo'),
                'convert' => $this->commandExists('convert'),
                'tesseract' => $this->commandExists('tesseract'),
                'phpspreadsheet' => class_exists('\PhpOffice\PhpSpreadsheet\IOFactory'),
            ]
        ];
    }
}
