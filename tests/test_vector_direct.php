<?php
/**
 * 直接测试 VectorService（绕过 HTTP）
 */

require_once '/mnt/h/Desktop/RUST/CRM_AI_V7/vendor/autoload.php';

use Services\AI\Core\RAG\VectorService;

// 加载 .env
$envFile = '/mnt/h/Desktop/RUST/CRM_AI_V7/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            putenv(trim($line));
        }
    }
}

echo "============ VectorService Direct Test ============\n\n";

// 连接 PostgreSQL
$host = getenv('PG_HOST') ?: '127.0.0.1';
$port = getenv('PG_PORT') ?: '5432';
$dbname = getenv('PG_DATABASE') ?: 'cy_cfc_pg';
$user = getenv('PG_USERNAME') ?: 'cy_cfc_pg';
$pass = getenv('PG_PASSWORD') ?: '123456';

echo "连接 PostgreSQL: {$host}:{$port}/{$dbname}\n";

try {
    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "✅ 数据库连接成功\n\n";
} catch (Throwable $e) {
    die("❌ 数据库连接失败: " . $e->getMessage() . "\n");
}

// 创建 VectorService
$vectorService = new VectorService(
    $pdo,
    null,  // 不使用远程 embedding
    512,
    50,
    'pgsql',
    true   // 强制使用本地 embedding
);

echo "VectorService 创建成功\n";
echo "Backend: pgsql\n";
echo "Local Embedding: " . ($vectorService->isUsingLocalEmbedding() ? 'Yes' : 'No') . "\n\n";

// 测试文件
$testFile = '/mnt/h/Desktop/RUST/CRM_AI_V7/tests/test_document.md';
if (!file_exists($testFile)) {
    die("❌ 测试文件不存在: {$testFile}\n");
}

echo "测试文件: {$testFile}\n";
echo "文件大小: " . filesize($testFile) . " bytes\n\n";

// 处理文件
echo "============ Processing File ============\n";
try {
    $result = $vectorService->processFile($testFile, 'test_document.md');
    echo "✅ 处理成功:\n";
    print_r($result);
} catch (Throwable $e) {
    echo "❌ 处理失败: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

// 获取统计
echo "\n============ Stats ============\n";
$stats = $vectorService->getStats();
print_r($stats);

// 测试搜索
echo "\n============ Search Test ============\n";
try {
    $results = $vectorService->search('框架 API', 3);
    echo "搜索 '框架 API' 结果:\n";
    foreach ($results as $r) {
        echo "- [{$r['similarity']}] {$r['file_name']} (chunk #{$r['chunk_index']})\n";
        echo "  " . mb_substr($r['content'], 0, 100) . "...\n\n";
    }
} catch (Throwable $e) {
    echo "❌ 搜索失败: " . $e->getMessage() . "\n";
}

echo "\n✅ All tests completed!\n";
