<?php
/**
 * 表格数据提取器
 * 
 * CFC V7.7 规范：
 * - 职责：Excel/CSV 表格的解析与智能格式化
 * - 设计理念：参考 OpenAI/Google 的文档理解方案
 *   1. 结构化保留：保持表格的行列关系
 *   2. 语义增强：为表格添加上下文描述
 *   3. 分块优化：大表格智能分割，避免切断行
 * 
 * @package Services\AI\Document
 * @version 7.7
 * @author CFC Framework
 */
declare(strict_types=1);

namespace Services\AI\Document;

class TableExtractor
{
    // 支持的表格格式
    public const SUPPORTED_FORMATS = ['xlsx', 'xls', 'csv', 'tsv'];
    
    // 单个切片的最大行数（避免上下文过长）
    private const MAX_ROWS_PER_CHUNK = 50;
    
    // 表头缓存（跨切片保持）
    private array $headerCache = [];

    /**
     * 检测文件是否为表格类型
     */
    public static function isTableFile(string $fileName): bool
    {
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        return in_array($ext, self::SUPPORTED_FORMATS);
    }

    /**
     * 处理表格文件
     * 
     * @param string $filePath 文件路径
     * @param string $fileName 原始文件名
     * @return array ['text' => string, 'method' => string, 'sheets' => int, 'rows' => int]
     */
    public function process(string $filePath, string $fileName): array
    {
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        return match ($ext) {
            'csv' => $this->processCsv($filePath, $fileName),
            'tsv' => $this->processTsv($filePath, $fileName),
            'xlsx', 'xls' => $this->processExcel($filePath, $fileName),
            default => throw new \InvalidArgumentException("不支持的表格格式: {$ext}")
        };
    }

    /**
     * 处理 CSV 文件
     */
    private function processCsv(string $filePath, string $fileName): array
    {
        $content = file_get_contents($filePath);
        
        // 检测编码并转换
        $encoding = mb_detect_encoding($content, ['UTF-8', 'GBK', 'GB2312', 'BIG5'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }
        
        // 检测分隔符
        $delimiter = $this->detectDelimiter($content);
        
        $rows = [];
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);
        
        return $this->formatTableToText($rows, $fileName, 'csv');
    }

    /**
     * 处理 TSV 文件
     */
    private function processTsv(string $filePath, string $fileName): array
    {
        $content = file_get_contents($filePath);
        
        $rows = [];
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $rows[] = explode("\t", $line);
            }
        }
        
        return $this->formatTableToText($rows, $fileName, 'tsv');
    }

    /**
     * 处理 Excel 文件
     * 使用 PhpSpreadsheet 或降级到 SimpleXLSX
     */
    private function processExcel(string $filePath, string $fileName): array
    {
        // 方法1: 使用 PhpSpreadsheet（推荐）
        if (class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            return $this->processExcelWithPhpSpreadsheet($filePath, $fileName);
        }
        
        // 方法2: 使用 SimpleXLSX（轻量级）
        if (class_exists('\SimpleXLSX')) {
            return $this->processExcelWithSimpleXLSX($filePath, $fileName);
        }
        
        // 方法3: 命令行工具 xlsx2csv
        if ($this->commandExists('xlsx2csv')) {
            return $this->processExcelWithCommand($filePath, $fileName);
        }
        
        // 方法4: Python 脚本
        if ($this->commandExists('python3')) {
            return $this->processExcelWithPython($filePath, $fileName);
        }
        
        throw new \RuntimeException(
            'Excel 解析需要安装以下任一依赖：' .
            'composer require phpoffice/phpspreadsheet 或 ' .
            'apt install xlsx2csv'
        );
    }

    /**
     * 使用 PhpSpreadsheet 处理 Excel
     */
    private function processExcelWithPhpSpreadsheet(string $filePath, string $fileName): array
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
        $allText = '';
        $totalRows = 0;
        $sheetCount = $spreadsheet->getSheetCount();
        
        foreach ($spreadsheet->getAllSheets() as $sheetIndex => $sheet) {
            $sheetName = $sheet->getTitle();
            $rows = $sheet->toArray(null, true, true, false);
            
            if (empty($rows)) continue;
            
            // 过滤空行
            $rows = array_filter($rows, fn($row) => !empty(array_filter($row, fn($cell) => $cell !== null && $cell !== '')));
            $rows = array_values($rows);
            
            if (empty($rows)) continue;
            
            $totalRows += count($rows);
            $result = $this->formatTableToText($rows, $fileName, 'excel', $sheetName);
            $allText .= $result['text'] . "\n\n";
        }
        
        return [
            'text' => trim($allText),
            'method' => 'phpspreadsheet',
            'sheets' => $sheetCount,
            'rows' => $totalRows,
        ];
    }

    /**
     * 使用 SimpleXLSX 处理 Excel
     */
    private function processExcelWithSimpleXLSX(string $filePath, string $fileName): array
    {
        $xlsx = \SimpleXLSX::parse($filePath);
        if (!$xlsx) {
            throw new \RuntimeException('Excel 解析失败: ' . \SimpleXLSX::parseError());
        }
        
        $allText = '';
        $totalRows = 0;
        $sheetNames = $xlsx->sheetNames();
        
        foreach ($sheetNames as $sheetIndex => $sheetName) {
            $rows = $xlsx->rows($sheetIndex);
            if (empty($rows)) continue;
            
            $totalRows += count($rows);
            $result = $this->formatTableToText($rows, $fileName, 'excel', $sheetName);
            $allText .= $result['text'] . "\n\n";
        }
        
        return [
            'text' => trim($allText),
            'method' => 'simplexlsx',
            'sheets' => count($sheetNames),
            'rows' => $totalRows,
        ];
    }

    /**
     * 使用命令行工具处理 Excel
     */
    private function processExcelWithCommand(string $filePath, string $fileName): array
    {
        $tempCsv = tempnam(sys_get_temp_dir(), 'xlsx_') . '.csv';
        exec("xlsx2csv " . escapeshellarg($filePath) . " " . escapeshellarg($tempCsv) . " 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0 || !file_exists($tempCsv)) {
            throw new \RuntimeException('xlsx2csv 转换失败');
        }
        
        $result = $this->processCsv($tempCsv, $fileName);
        $result['method'] = 'xlsx2csv';
        @unlink($tempCsv);
        
        return $result;
    }

    /**
     * 使用 Python 处理 Excel
     */
    private function processExcelWithPython(string $filePath, string $fileName): array
    {
        $pythonScript = <<<'PYTHON'
import sys
import pandas as pd
import json

try:
    xlsx = pd.ExcelFile(sys.argv[1])
    result = {'sheets': [], 'total_rows': 0}
    
    for sheet_name in xlsx.sheet_names:
        df = pd.read_excel(xlsx, sheet_name=sheet_name)
        df = df.dropna(how='all')
        
        if len(df) > 0:
            result['sheets'].append({
                'name': sheet_name,
                'data': df.to_csv(index=False)
            })
            result['total_rows'] += len(df)
    
    print(json.dumps(result))
except Exception as e:
    print(json.dumps({'error': str(e)}))
PYTHON;

        $tempScript = tempnam(sys_get_temp_dir(), 'xlsx_') . '.py';
        file_put_contents($tempScript, $pythonScript);
        
        exec("python3 " . escapeshellarg($tempScript) . " " . escapeshellarg($filePath) . " 2>&1", $output, $returnCode);
        @unlink($tempScript);
        
        $json = implode("\n", $output);
        $data = json_decode($json, true);
        
        if (isset($data['error'])) {
            throw new \RuntimeException('Python Excel 解析失败: ' . $data['error']);
        }
        
        $allText = '';
        foreach ($data['sheets'] ?? [] as $sheet) {
            $rows = [];
            $lines = explode("\n", $sheet['data']);
            foreach ($lines as $line) {
                if (trim($line) !== '') {
                    $rows[] = str_getcsv($line);
                }
            }
            $result = $this->formatTableToText($rows, $fileName, 'excel', $sheet['name']);
            $allText .= $result['text'] . "\n\n";
        }
        
        return [
            'text' => trim($allText),
            'method' => 'python_pandas',
            'sheets' => count($data['sheets'] ?? []),
            'rows' => $data['total_rows'] ?? 0,
        ];
    }

    /**
     * 将表格数据格式化为文本
     * 
     * 大厂设计理念：
     * 1. 使用 Markdown 表格格式（LLM 友好）
     * 2. 添加上下文元数据
     * 3. 大表格智能分块，表头重复
     */
    private function formatTableToText(array $rows, string $fileName, string $format, ?string $sheetName = null): array
    {
        if (empty($rows)) {
            return ['text' => '', 'method' => $format, 'rows' => 0];
        }
        
        $totalRows = count($rows);
        
        // 提取表头
        $header = array_shift($rows);
        $this->headerCache = $header;
        $columnCount = count($header);
        
        // 构建文档元信息
        $meta = "【表格文档】\n";
        $meta .= "文件名：{$fileName}\n";
        if ($sheetName) {
            $meta .= "工作表：{$sheetName}\n";
        }
        $meta .= "数据量：{$totalRows} 行 × {$columnCount} 列\n";
        $meta .= "列名：" . implode('、', array_filter($header)) . "\n\n";
        
        // 判断是否需要分块
        if ($totalRows <= self::MAX_ROWS_PER_CHUNK) {
            // 小表格：完整输出
            $text = $meta . $this->buildMarkdownTable($header, $rows);
        } else {
            // 大表格：分块输出，每块带表头
            $text = $meta;
            $chunks = array_chunk($rows, self::MAX_ROWS_PER_CHUNK);
            foreach ($chunks as $chunkIndex => $chunkRows) {
                $chunkNum = $chunkIndex + 1;
                $totalChunks = count($chunks);
                $startRow = $chunkIndex * self::MAX_ROWS_PER_CHUNK + 1;
                $endRow = min(($chunkIndex + 1) * self::MAX_ROWS_PER_CHUNK, $totalRows - 1);
                
                $text .= "--- 数据块 {$chunkNum}/{$totalChunks} (行 {$startRow}-{$endRow}) ---\n";
                $text .= $this->buildMarkdownTable($header, $chunkRows);
                $text .= "\n\n";
            }
        }
        
        // 添加数据摘要（帮助 LLM 理解）
        $summary = $this->generateTableSummary($header, $rows);
        if ($summary) {
            $text .= "\n【数据摘要】\n" . $summary;
        }
        
        return [
            'text' => $text,
            'method' => $format,
            'rows' => $totalRows,
        ];
    }

    /**
     * 构建 Markdown 表格
     */
    private function buildMarkdownTable(array $header, array $rows): string
    {
        $lines = [];
        
        // 表头
        $headerLine = '| ' . implode(' | ', array_map(fn($h) => $this->escapeCell($h), $header)) . ' |';
        $lines[] = $headerLine;
        
        // 分隔线
        $separator = '|' . str_repeat(' --- |', count($header));
        $lines[] = $separator;
        
        // 数据行
        foreach ($rows as $row) {
            // 补齐列数
            while (count($row) < count($header)) {
                $row[] = '';
            }
            $rowLine = '| ' . implode(' | ', array_map(fn($c) => $this->escapeCell($c), $row)) . ' |';
            $lines[] = $rowLine;
        }
        
        return implode("\n", $lines);
    }

    /**
     * 转义单元格内容
     */
    private function escapeCell($value): string
    {
        if ($value === null) return '';
        $str = (string) $value;
        $str = str_replace(['|', "\n", "\r"], ['\|', ' ', ''], $str);
        return trim($str);
    }

    /**
     * 生成表格摘要
     */
    private function generateTableSummary(array $header, array $rows): string
    {
        if (empty($rows)) return '';
        
        $summary = [];
        
        // 分析每列的数据特征
        foreach ($header as $colIndex => $colName) {
            if (empty($colName)) continue;
            
            $values = array_column($rows, $colIndex);
            $nonEmpty = array_filter($values, fn($v) => $v !== null && $v !== '');
            
            if (empty($nonEmpty)) continue;
            
            // 检测数值列
            $numericValues = array_filter($nonEmpty, fn($v) => is_numeric($v));
            if (count($numericValues) > count($nonEmpty) * 0.8) {
                $nums = array_map('floatval', $numericValues);
                $min = min($nums);
                $max = max($nums);
                $avg = array_sum($nums) / count($nums);
                $summary[] = "- {$colName}：数值范围 {$min} ~ {$max}，平均值 " . round($avg, 2);
            } else {
                // 非数值列：统计唯一值
                $unique = array_unique($nonEmpty);
                if (count($unique) <= 10) {
                    $summary[] = "- {$colName}：" . implode('、', array_slice($unique, 0, 10));
                } else {
                    $summary[] = "- {$colName}：{" . count($unique) . " 种不同值}";
                }
            }
        }
        
        return implode("\n", array_slice($summary, 0, 10));
    }

    /**
     * 检测 CSV 分隔符
     */
    private function detectDelimiter(string $content): string
    {
        $firstLine = strtok($content, "\n");
        
        $delimiters = [',', ';', "\t", '|'];
        $counts = [];
        
        foreach ($delimiters as $d) {
            $counts[$d] = substr_count($firstLine, $d);
        }
        
        arsort($counts);
        return key($counts) ?: ',';
    }

    /**
     * 检查命令是否存在
     */
    private function commandExists(string $cmd): bool
    {
        return !empty(trim(shell_exec("which {$cmd} 2>/dev/null") ?? ''));
    }

    /**
     * 获取支持的格式列表
     */
    public static function getSupportedFormats(): array
    {
        return self::SUPPORTED_FORMATS;
    }
}
