<?php
$content = file_get_contents(__DIR__ . '/test_knowledge_base_quick.php');
$content = str_replace('$vectorStore->', '$docStore->', $content);
file_put_contents(__DIR__ . '/test_knowledge_base_quick.php', $content);
echo "✓ 替换完成\n";
