<?php
declare(strict_types=1);

namespace Services\AI\Core\RAG;

/**
 * 文档分块器
 * 将长文档切分为小块以便向量化
 */
class DocumentChunker
{
    private int $chunkSize;
    private int $overlap;
    private string $separator;

    public function __construct(
        int $chunkSize = 512,
        int $overlap = 50,
        string $separator = "\n\n"
    ) {
        $this->chunkSize = $chunkSize;
        $this->overlap = $overlap;
        $this->separator = $separator;
    }

    /**
     * 将文档切分为块
     *
     * @param string $document 文档内容
     * @return array 文档块数组
     */
    public function chunk(string $document): array
    {
        // 先按分隔符分段
        $segments = $this->splitBySeparator($document);

        // 再按长度分块
        $chunks = [];
        $currentChunk = '';

        foreach ($segments as $segment) {
            $segmentLength = mb_strlen($segment);

            // 如果单个段落就超过块大小，强制分割
            if ($segmentLength > $this->chunkSize) {
                if ($currentChunk) {
                    $chunks[] = trim($currentChunk);
                    $currentChunk = '';
                }

                $chunks = array_merge($chunks, $this->splitLongSegment($segment));
                continue;
            }

            // 如果加上当前段落会超过块大小，保存当前块
            if (mb_strlen($currentChunk) + $segmentLength > $this->chunkSize) {
                if ($currentChunk) {
                    $chunks[] = trim($currentChunk);
                }

                // 开始新块（带重叠）
                $currentChunk = $this->getOverlapText($currentChunk) . $segment;
            } else {
                $currentChunk .= ($currentChunk ? $this->separator : '') . $segment;
            }
        }

        // 保存最后一个块
        if ($currentChunk) {
            $chunks[] = trim($currentChunk);
        }

        return array_filter($chunks);
    }

    /**
     * 按分隔符分割文档
     */
    private function splitBySeparator(string $document): array
    {
        $segments = explode($this->separator, $document);

        return array_filter(array_map('trim', $segments));
    }

    /**
     * 分割超长段落
     */
    private function splitLongSegment(string $segment): array
    {
        $chunks = [];
        $words = preg_split('/\s+/u', $segment);
        $currentChunk = '';

        foreach ($words as $word) {
            if (mb_strlen($currentChunk) + mb_strlen($word) > $this->chunkSize) {
                if ($currentChunk) {
                    $chunks[] = trim($currentChunk);
                }

                $currentChunk = $this->getOverlapText($currentChunk) . $word;
            } else {
                $currentChunk .= ($currentChunk ? ' ' : '') . $word;
            }
        }

        if ($currentChunk) {
            $chunks[] = trim($currentChunk);
        }

        return $chunks;
    }

    /**
     * 获取重叠文本
     */
    private function getOverlapText(string $text): string
    {
        if ($this->overlap === 0) {
            return '';
        }

        $length = mb_strlen($text);

        if ($length <= $this->overlap) {
            return $text . ' ';
        }

        return mb_substr($text, -$this->overlap) . ' ';
    }

    /**
     * 智能分块（考虑句子边界）
     *
     * @param string $document 文档内容
     * @return array 文档块数组
     */
    public function chunkBySentence(string $document): array
    {
        // 按句子分割
        $sentences = preg_split('/([。！？.!?])\s*/u', $document, -1, PREG_SPLIT_DELIM_CAPTURE);

        $chunks = [];
        $currentChunk = '';

        for ($i = 0; $i < count($sentences); $i += 2) {
            $sentence = $sentences[$i] ?? '';
            $delimiter = $sentences[$i + 1] ?? '';
            $fullSentence = $sentence . $delimiter;

            if (mb_strlen($currentChunk) + mb_strlen($fullSentence) > $this->chunkSize) {
                if ($currentChunk) {
                    $chunks[] = trim($currentChunk);
                }

                $currentChunk = $this->getOverlapText($currentChunk) . $fullSentence;
            } else {
                $currentChunk .= $fullSentence;
            }
        }

        if ($currentChunk) {
            $chunks[] = trim($currentChunk);
        }

        return array_filter($chunks);
    }

    /**
     * 按代码块分割（适用于技术文档）
     *
     * @param string $document 文档内容
     * @return array 文档块数组
     */
    public function chunkByCode(string $document): array
    {
        // 识别代码块（Markdown 格式）
        $pattern = '/```[\s\S]*?```/';
        $parts = preg_split($pattern, $document, -1, PREG_SPLIT_OFFSET_CAPTURE);
        preg_match_all($pattern, $document, $codeBlocks);

        $chunks = [];
        $codeBlocks = $codeBlocks[0];
        $codeIndex = 0;

        foreach ($parts as $part) {
            $text = $part[0];

            // 处理普通文本
            if (trim($text)) {
                $textChunks = $this->chunk($text);
                $chunks = array_merge($chunks, $textChunks);
            }

            // 添加代码块
            if (isset($codeBlocks[$codeIndex])) {
                $chunks[] = $codeBlocks[$codeIndex];
                $codeIndex++;
            }
        }

        return array_filter($chunks);
    }

    /**
     * 设置块大小
     */
    public function setChunkSize(int $size): void
    {
        $this->chunkSize = $size;
    }

    /**
     * 设置重叠大小
     */
    public function setOverlap(int $overlap): void
    {
        $this->overlap = $overlap;
    }
}
