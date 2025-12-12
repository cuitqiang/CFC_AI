<?php
declare(strict_types=1);

namespace Services\AI\Core\RAG;

/**
 * 本地 TF-IDF Embedding 实现
 * 
 * 当远程 Embedding API 不可用时，使用本地 TF-IDF 算法进行文本向量化
 * 支持中英文混合文本
 */
class LocalEmbedding
{
    private int $dimensions;
    private array $vocabulary = [];
    private array $idf = [];
    
    public function __construct(int $dimensions = 512)
    {
        $this->dimensions = $dimensions;
    }
    
    /**
     * 对单个文本进行向量化
     */
    public function embed(string $text): array
    {
        // 分词
        $tokens = $this->tokenize($text);
        
        // 计算词频 (TF)
        $tf = array_count_values($tokens);
        $totalTokens = count($tokens);
        
        // 归一化 TF
        foreach ($tf as $word => $count) {
            $tf[$word] = $count / max(1, $totalTokens);
        }
        
        // 使用哈希技巧将词映射到固定维度
        $vector = array_fill(0, $this->dimensions, 0.0);
        
        foreach ($tf as $word => $frequency) {
            // 使用多个哈希函数减少碰撞
            $hash1 = crc32($word) % $this->dimensions;
            $hash2 = crc32(strrev($word)) % $this->dimensions;
            $sign = (crc32($word . '_sign') % 2) * 2 - 1; // +1 或 -1
            
            $vector[$hash1] += $frequency * $sign;
            $vector[($hash1 + $hash2) % $this->dimensions] += $frequency * 0.5 * $sign;
        }
        
        // L2 归一化
        return $this->normalize($vector);
    }
    
    /**
     * 批量向量化
     */
    public function batchEmbed(array $texts): array
    {
        return array_map(fn($text) => $this->embed($text), $texts);
    }
    
    /**
     * 计算两个向量的余弦相似度
     */
    public function similarity(array $vec1, array $vec2): float
    {
        $dot = 0.0;
        $norm1 = 0.0;
        $norm2 = 0.0;
        
        $len = min(count($vec1), count($vec2));
        
        for ($i = 0; $i < $len; $i++) {
            $dot += $vec1[$i] * $vec2[$i];
            $norm1 += $vec1[$i] * $vec1[$i];
            $norm2 += $vec2[$i] * $vec2[$i];
        }
        
        $divisor = sqrt($norm1) * sqrt($norm2);
        
        return $divisor > 0 ? $dot / $divisor : 0.0;
    }
    
    /**
     * 中英文混合分词
     */
    private function tokenize(string $text): array
    {
        // 转小写
        $text = mb_strtolower($text);
        
        // 移除特殊字符，保留中文、英文、数字
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        
        $tokens = [];
        
        // 处理英文单词
        if (preg_match_all('/[a-z][a-z0-9]{1,}/i', $text, $matches)) {
            $tokens = array_merge($tokens, $matches[0]);
        }
        
        // 处理中文 - 使用简单的 n-gram (2-gram)
        if (preg_match_all('/[\x{4e00}-\x{9fff}]+/u', $text, $matches)) {
            foreach ($matches[0] as $chinese) {
                $len = mb_strlen($chinese);
                // 单字
                for ($i = 0; $i < $len; $i++) {
                    $tokens[] = mb_substr($chinese, $i, 1);
                }
                // 双字词
                for ($i = 0; $i < $len - 1; $i++) {
                    $tokens[] = mb_substr($chinese, $i, 2);
                }
            }
        }
        
        // 移除停用词
        $stopwords = $this->getStopwords();
        $tokens = array_filter($tokens, fn($t) => !isset($stopwords[$t]) && mb_strlen($t) > 0);
        
        return array_values($tokens);
    }
    
    /**
     * L2 归一化
     */
    private function normalize(array $vector): array
    {
        $norm = 0.0;
        foreach ($vector as $v) {
            $norm += $v * $v;
        }
        $norm = sqrt($norm);
        
        if ($norm > 0) {
            foreach ($vector as &$v) {
                $v /= $norm;
            }
        }
        
        return $vector;
    }
    
    /**
     * 常用停用词
     */
    private function getStopwords(): array
    {
        static $stopwords = null;
        
        if ($stopwords === null) {
            $stopwords = array_flip([
                // 英文
                'the', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
                'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could',
                'should', 'may', 'might', 'must', 'shall', 'can', 'need', 'dare',
                'to', 'of', 'in', 'for', 'on', 'with', 'at', 'by', 'from', 'as',
                'into', 'through', 'during', 'before', 'after', 'above', 'below',
                'between', 'under', 'again', 'further', 'then', 'once', 'here',
                'there', 'when', 'where', 'why', 'how', 'all', 'each', 'few',
                'more', 'most', 'other', 'some', 'such', 'no', 'nor', 'not',
                'only', 'own', 'same', 'so', 'than', 'too', 'very', 'just',
                'and', 'but', 'if', 'or', 'because', 'until', 'while', 'about',
                'this', 'that', 'these', 'those', 'it', 'its',
                // 中文
                '的', '了', '是', '在', '我', '有', '和', '就', '不', '人', '都',
                '一', '一个', '上', '也', '很', '到', '说', '要', '去', '你', '会',
                '着', '没有', '看', '好', '自己', '这', '那', '她', '他', '它',
                '们', '来', '为', '以', '及', '等', '或', '但', '与', '而', '从',
            ]);
        }
        
        return $stopwords;
    }
}
