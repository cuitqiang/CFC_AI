<?php
/**
 * 崔哥记忆压缩 Worker V3
 * 
 * 后台运行，执行：
 * 1. 递归摘要压缩
 * 2. 超级摘要生成
 * 3. 用户画像更新
 * 4. 记忆衰减
 * 
 * 运行: php memory_worker_v3.php
 */

declare(strict_types=1);

// 加载框架
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/AI/Bootstrap.php';
Services\AI\Bootstrap::initialize();

require_once __DIR__ . '/../src/Services/AI/Memory/CuigeMemoryEngine.php';

use Services\AI\Memory\CuigeMemoryEngine;

echo "=== 崔哥记忆压缩 Worker V3 启动 ===\n";
echo "时间: " . date('Y-m-d H:i:s') . "\n";

// 数据库连接
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $dbname = $_ENV['DB_DATABASE'] ?? 'cy_cfc';
        $user = $_ENV['DB_USERNAME'] ?? 'cy_cfc';
        $pass = $_ENV['DB_PASSWORD'] ?? '123456';
        
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }
    return $pdo;
}

$pdo = getDB();
$engine = new CuigeMemoryEngine($pdo);

// 主循环
$loopCount = 0;
while (true) {
    $loopCount++;
    
    try {
        // 1. 处理压缩任务
        $stmt = $pdo->prepare("
            SELECT id, session_id, user_id, task_type
            FROM cuige_memory_tasks
            WHERE status = 'pending'
            ORDER BY priority DESC, created_at ASC
            LIMIT 5
        ");
        $stmt->execute();
        $tasks = $stmt->fetchAll();
        
        foreach ($tasks as $task) {
            echo "[" . date('H:i:s') . "] 处理任务 #{$task['id']}: {$task['task_type']}\n";
            
            // 标记为处理中
            $stmt = $pdo->prepare("UPDATE cuige_memory_tasks SET status = 'processing' WHERE id = ?");
            $stmt->execute([$task['id']]);
            
            try {
                switch ($task['task_type']) {
                    case 'compress':
                        $engine->compressMessages($task['session_id'], $task['user_id']);
                        break;
                    case 'analyze':
                        $engine->updateUserProfile($task['user_id']);
                        break;
                }
                
                // 标记完成
                $stmt = $pdo->prepare("UPDATE cuige_memory_tasks SET status = 'completed' WHERE id = ?");
                $stmt->execute([$task['id']]);
                echo "  ✓ 完成\n";
                
            } catch (Exception $e) {
                // 标记失败
                $stmt = $pdo->prepare("UPDATE cuige_memory_tasks SET status = 'failed', result = ? WHERE id = ?");
                $stmt->execute([$e->getMessage(), $task['id']]);
                echo "  ✗ 失败: {$e->getMessage()}\n";
            }
        }
        
        // 2. 每10轮检查超级摘要
        if ($loopCount % 10 === 0) {
            echo "[" . date('H:i:s') . "] 检查超级摘要...\n";
            
            // 获取有大量摘要的用户
            $stmt = $pdo->query("
                SELECT user_id, COUNT(*) as cnt
                FROM cuige_conversation_summaries
                WHERE is_archived = 0 AND is_super_summary = 0
                GROUP BY user_id
                HAVING cnt >= 10
            ");
            $users = $stmt->fetchAll();
            
            foreach ($users as $user) {
                echo "  压缩用户 {$user['user_id']} 的 {$user['cnt']} 个摘要...\n";
                $engine->compressSummaries($user['user_id']);
            }
        }
        
        // 3. 每100轮执行记忆衰减
        if ($loopCount % 100 === 0) {
            echo "[" . date('H:i:s') . "] 执行记忆衰减...\n";
            $engine->decayMemories();
        }
        
        // 4. 每50轮更新活跃用户画像
        if ($loopCount % 50 === 0) {
            echo "[" . date('H:i:s') . "] 更新用户画像...\n";
            
            // 获取最近活跃的用户
            $stmt = $pdo->query("
                SELECT DISTINCT user_id
                FROM cuige_short_memory
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $activeUsers = $stmt->fetchAll();
            
            foreach ($activeUsers as $user) {
                $engine->updateUserProfile($user['user_id']);
            }
        }
        
    } catch (Exception $e) {
        echo "[ERROR] " . $e->getMessage() . "\n";
    }
    
    // 休眠
    sleep(5);
}
