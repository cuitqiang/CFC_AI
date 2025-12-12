<?php
/**
 * PostgreSQL 向量数据库连接测试
 */

echo "=== PostgreSQL Vector Database Test ===\n\n";

$config = [
    'host' => '127.0.0.1',
    'port' => '5432',
    'dbname' => 'cy_cfc_pg',
    'user' => 'cy_cfc_pg',
    'password' => '123456',
];

try {
    $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['dbname']}";
    $pdo = new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    echo "[OK] PostgreSQL Connected!\n";
    
    // 检查版本
    $version = $pdo->query("SELECT version()")->fetchColumn();
    echo "[INFO] Version: " . substr($version, 0, 50) . "...\n";
    
    // 检查 pgvector 扩展
    echo "\n--- Checking pgvector extension ---\n";
    $extensions = $pdo->query("SELECT extname, extversion FROM pg_extension")->fetchAll(PDO::FETCH_ASSOC);
    
    $hasVector = false;
    foreach ($extensions as $ext) {
        echo "  - {$ext['extname']} v{$ext['extversion']}\n";
        if ($ext['extname'] === 'vector') {
            $hasVector = true;
        }
    }
    
    if (!$hasVector) {
        echo "\n[WARN] pgvector not installed, trying to create...\n";
        try {
            $pdo->exec("CREATE EXTENSION IF NOT EXISTS vector");
            echo "[OK] pgvector extension created!\n";
        } catch (Exception $e) {
            echo "[ERROR] Failed to create pgvector: " . $e->getMessage() . "\n";
            echo "[INFO] You may need to install pgvector first:\n";
            echo "       sudo apt install postgresql-16-pgvector\n";
        }
    } else {
        echo "\n[OK] pgvector is installed!\n";
    }
    
    // 测试向量操作
    echo "\n--- Testing vector operations ---\n";
    
    // 创建测试表
    $pdo->exec("DROP TABLE IF EXISTS test_vectors");
    $pdo->exec("
        CREATE TABLE test_vectors (
            id SERIAL PRIMARY KEY,
            content TEXT,
            embedding vector(3)
        )
    ");
    echo "[OK] Created test_vectors table\n";
    
    // 插入测试数据
    $pdo->exec("INSERT INTO test_vectors (content, embedding) VALUES ('Hello', '[1,2,3]')");
    $pdo->exec("INSERT INTO test_vectors (content, embedding) VALUES ('World', '[4,5,6]')");
    $pdo->exec("INSERT INTO test_vectors (content, embedding) VALUES ('Test', '[1,2,4]')");
    echo "[OK] Inserted test data\n";
    
    // 向量相似度查询
    $result = $pdo->query("
        SELECT content, embedding, embedding <-> '[1,2,3]' as distance 
        FROM test_vectors 
        ORDER BY distance 
        LIMIT 3
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "[OK] Vector similarity search result:\n";
    foreach ($result as $row) {
        echo "     Content: {$row['content']}, Distance: " . round($row['distance'], 4) . "\n";
    }
    
    // 清理测试表
    $pdo->exec("DROP TABLE test_vectors");
    echo "[OK] Cleaned up test table\n";
    
    echo "\n=== All tests passed! ===\n";
    
} catch (PDOException $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
