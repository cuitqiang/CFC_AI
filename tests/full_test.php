<?php
require_once '/mnt/h/Desktop/RUST/CRM_AI_V7/vendor/autoload.php';
use Services\AI\Core\RAG\LocalEmbedding;

$pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=cy_cfc_pg', 'cy_cfc_pg', '123456');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$le = new LocalEmbedding(512);

// 读取文件
$content = file_get_contents('/mnt/h/Desktop/RUST/CRM_AI_V7/tests/tiny.txt');
echo "内容: " . mb_substr($content, 0, 50) . "...\n";

// 生成 embedding
echo "生成向量...\n";
$embedding = $le->embed($content);
echo "维度: " . count($embedding) . "\n";

// 插入
echo "插入数据库...\n";
$embeddingStr = '[' . implode(',', $embedding) . ']';
$sql = "INSERT INTO ai_vectors (doc_hash, file_name, file_path, chunk_index, total_chunks, content, embedding, metadata) VALUES (?, ?, ?, ?, ?, ?, ?::vector, ?::jsonb)";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    md5($content),
    'tiny.txt',
    '/tmp/tiny.txt',
    0,
    1,
    $content,
    $embeddingStr,
    '{"mode":"local"}'
]);
echo "✅ 插入成功!\n";

// 搜索
echo "\n搜索测试...\n";
$queryEmb = $le->embed("向量搜索");
$queryStr = '[' . implode(',', $queryEmb) . ']';
$sql = "SELECT id, file_name, content, 1 - (embedding <=> ?::vector) as similarity FROM ai_vectors ORDER BY embedding <=> ?::vector LIMIT 3";
$stmt = $pdo->prepare($sql);
$stmt->execute([$queryStr, $queryStr]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($results);
