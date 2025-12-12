<?php
// 最简单的 pgvector 测试
$pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=cy_cfc_pg', 'cy_cfc_pg', '123456');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 生成 512 维向量
$vec = array_fill(0, 512, 0.01);
$vecStr = '[' . implode(',', $vec) . ']';

echo "插入测试...\n";
$sql = "INSERT INTO ai_vectors (doc_hash, file_name, file_path, chunk_index, total_chunks, content, embedding) VALUES ('abc123', 'test.txt', '/tmp/t.txt', 0, 1, 'hello world', ?::vector)";
$stmt = $pdo->prepare($sql);
$stmt->execute([$vecStr]);
echo "✅ 插入成功!\n";

echo "查询测试...\n";
$rows = $pdo->query("SELECT id, doc_hash, file_name FROM ai_vectors")->fetchAll();
print_r($rows);
