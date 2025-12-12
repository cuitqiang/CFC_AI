<?php
require_once '/mnt/h/Desktop/RUST/CRM_AI_V7/vendor/autoload.php';
use Services\AI\Core\RAG\VectorService;

// 连接 PostgreSQL
$pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=cy_cfc_pg', 'cy_cfc_pg', '123456');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$vs = new VectorService($pdo, null, 512, 50, 'pgsql', true);

echo "处理文件...\n";
$result = $vs->processFile('/mnt/h/Desktop/RUST/CRM_AI_V7/tests/tiny.txt', 'tiny.txt');
print_r($result);

echo "\n搜索测试...\n";
$search = $vs->search('向量', 3);
print_r($search);
