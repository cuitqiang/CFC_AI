<?php
/**
 * 图片分析服务
 * 
 * CFC V7.7 规范：
 * - 职责：使用多模态 AI 分析图片内容
 * - 输出：结构化的图片描述，供群聊 AI 使用
 * 
 * @package Services\AI\Vision
 * @version 7.7
 */
declare(strict_types=1);

namespace Services\AI\Vision;

use Services\AI\Config;
use Services\AI\Bootstrap;

class ImageAnalyzer
{
    private string $apiKey;
    private string $baseUrl;
    private string $model;
    
    public function __construct()
    {
        // 确保配置已加载
        if (defined('APP_ROOT') && file_exists(APP_ROOT . '/.env')) {
            Bootstrap::initialize(APP_ROOT . '/.env');
        }
        
        // 从配置读取多模态 API
        $visionConfig = Config::get('vision', []);
        $this->model = $visionConfig['model'] ?? Config::get('vision_model', '[Y]gemini-2.5-pro');
        $this->apiKey = $visionConfig['api_key'] ?? Config::get('openai_api_key', '');
        $this->baseUrl = $visionConfig['base_url'] ?? Config::get('openai_base_url', 'https://api.mttieeo.com/v1');
    }
    
    /**
     * 分析上传的图片
     * 
     * @param array $file $_FILES['image'] 数组
     * @return array ['success' => bool, 'description' => string, 'tags' => array]
     */
    public function analyzeUpload(array $file): array
    {
        // 校验上传
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('图片上传失败: ' . $this->getUploadError($file['error']));
        }
        
        // 校验文件类型
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedTypes = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
        if (!in_array($ext, $allowedTypes)) {
            throw new \InvalidArgumentException('不支持的图片格式，支持: ' . implode(', ', $allowedTypes));
        }
        
        // 校验大小 (最大 10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            throw new \InvalidArgumentException('图片大小不能超过 10MB');
        }
        
        return $this->analyze($file['tmp_name']);
    }
    
    /**
     * 分析本地图片文件
     * 
     * @param string $filePath 图片路径
     * @return array
     */
    public function analyze(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException('图片文件不存在');
        }
        
        // 转 base64
        $imageData = base64_encode(file_get_contents($filePath));
        $mimeType = mime_content_type($filePath) ?: 'image/png';
        
        return $this->analyzeBase64($imageData, $mimeType);
    }
    
    /**
     * 分析 base64 图片
     * 
     * @param string $base64Data base64 编码的图片
     * @param string $mimeType MIME 类型
     * @return array
     */
    public function analyzeBase64(string $base64Data, string $mimeType = 'image/png'): array
    {
        $prompt = <<<PROMPT
请详细分析这张图片，为群聊讨论提供信息：

1. **图片描述**：用2-3句话描述图片的主要内容
2. **关键元素**：列出图片中的关键元素（人物、物品、场景等）
3. **情感/氛围**：描述图片传达的情感或氛围
4. **讨论话题**：基于图片内容，提出1-2个可以讨论的话题

请用中文回答，格式如下：
【描述】...
【关键元素】...
【情感】...
【话题】...
PROMPT;

        try {
            $response = $this->callVisionApi($prompt, $mimeType, $base64Data);
            $content = $response['choices'][0]['message']['content'] ?? '';
            
            // 解析结构化内容
            $parsed = $this->parseAnalysis($content);
            
            return [
                'success' => true,
                'raw_content' => $content,
                'description' => $parsed['description'],
                'elements' => $parsed['elements'],
                'emotion' => $parsed['emotion'],
                'topics' => $parsed['topics'],
                'model' => $this->model,
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'description' => '图片分析失败',
                'elements' => [],
                'emotion' => '',
                'topics' => [],
            ];
        }
    }
    
    /**
     * 解析 AI 分析结果
     */
    private function parseAnalysis(string $content): array
    {
        // 确保 UTF-8 编码正确
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        
        $result = [
            'description' => '',
            'elements' => [],
            'emotion' => '',
            'topics' => [],
        ];
        
        // 提取描述
        if (preg_match('/【描述】(.+?)(?=【|$)/su', $content, $m)) {
            $result['description'] = $this->cleanUtf8(trim($m[1]));
        }
        
        // 提取关键元素
        if (preg_match('/【关键元素】(.+?)(?=【|$)/su', $content, $m)) {
            $elements = trim($m[1]);
            // 支持列表或逗号分隔
            if (preg_match_all('/[-•\*]\s*(.+)/mu', $elements, $items)) {
                $result['elements'] = array_map(fn($s) => $this->cleanUtf8(trim($s)), $items[1]);
            } else {
                $result['elements'] = array_map(fn($s) => $this->cleanUtf8(trim($s)), preg_split('/[,，、]/u', $elements));
            }
            // 过滤空元素
            $result['elements'] = array_values(array_filter($result['elements']));
        }
        
        // 提取情感
        if (preg_match('/【情感】(.+?)(?=【|$)/su', $content, $m)) {
            $result['emotion'] = $this->cleanUtf8(trim($m[1]));
        }
        
        // 提取话题
        if (preg_match('/【话题】(.+?)(?=【|$)/su', $content, $m)) {
            $topics = trim($m[1]);
            if (preg_match_all('/\d+[.、]\s*(.+)/mu', $topics, $items)) {
                $result['topics'] = array_map(fn($s) => $this->cleanUtf8(trim($s)), $items[1]);
            } else {
                $result['topics'] = [$this->cleanUtf8(trim($topics))];
            }
            // 过滤空元素
            $result['topics'] = array_values(array_filter($result['topics']));
        }
        
        // 如果解析失败，使用原文作为描述
        if (empty($result['description'])) {
            $result['description'] = $this->cleanUtf8($content);
        }
        
        return $result;
    }
    
    /**
     * 调用视觉 API
     */
    private function callVisionApi(string $prompt, string $mimeType, string $imageData): array
    {
        $ch = curl_init($this->baseUrl . '/chat/completions');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => $this->model,
            'messages' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $prompt],
                    ['type' => 'image_url', 'image_url' => [
                        'url' => "data:{$mimeType};base64,{$imageData}"
                    ]]
                ]
            ]],
            'max_tokens' => 1024,
            'temperature' => 0.7,
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
     * 清理 UTF-8 字符串，移除无效字符
     */
    private function cleanUtf8(string $str): string
    {
        // 移除 BOM
        $str = preg_replace('/^\xEF\xBB\xBF/', '', $str);
        
        // 移除无效的 UTF-8 字符
        $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8');
        
        // 移除控制字符（保留换行和制表符）
        $str = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $str);
        
        return $str;
    }
    
    /**
     * 获取上传错误描述
     */
    private function getUploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => '文件太大',
            UPLOAD_ERR_PARTIAL => '文件只上传了一部分',
            UPLOAD_ERR_NO_FILE => '没有选择文件',
            UPLOAD_ERR_NO_TMP_DIR => '服务器临时目录缺失',
            UPLOAD_ERR_CANT_WRITE => '写入磁盘失败',
            default => '未知上传错误',
        };
    }
    
    /**
     * 获取系统能力
     */
    public function getCapabilities(): array
    {
        return [
            'multimodal_available' => !empty($this->apiKey),
            'vision_model' => $this->model,
            'supported_formats' => ['png', 'jpg', 'jpeg', 'gif', 'webp'],
            'max_file_size' => '10MB',
        ];
    }
}
