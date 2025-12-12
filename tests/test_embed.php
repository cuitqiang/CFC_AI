<?php
require_once '/mnt/h/Desktop/RUST/CRM_AI_V7/vendor/autoload.php';
use Services\AI\Core\RAG\LocalEmbedding;

echo "测试 LocalEmbedding...\n";
$le = new LocalEmbedding(512);
$vec = $le->embed("测试文本");
echo "维度: " . count($vec) . "\n";
echo "前5个值: " . implode(', ', array_slice($vec, 0, 5)) . "\n";
echo "✅ LocalEmbedding OK\n";
