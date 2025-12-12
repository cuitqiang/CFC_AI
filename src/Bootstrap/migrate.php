<?php
declare(strict_types=1);

/**
 * CFC V7.7 数据库迁移脚本
 * 
 * 用法: php src/Bootstrap/migrate.php [--seed] [--fresh]
 * 
 * 参数:
 *   --seed   执行数据填充
 *   --fresh  删除所有表后重新创建
 *   --force  强制执行（不需要确认）
 */

// 定义根目录
define('APP_ROOT', dirname(__DIR__, 2));

// 颜色输出
function info(string $msg): void { echo "\033[34m[INFO]\033[0m {$msg}\n"; }
function success(string $msg): void { echo "\033[32m[OK]\033[0m {$msg}\n"; }
function error(string $msg): void { echo "\033[31m[ERROR]\033[0m {$msg}\n"; }
function warning(string $msg): void { echo "\033[33m[WARN]\033[0m {$msg}\n"; }

echo "\n";
echo "╔══════════════════════════════════════════════════════╗\n";
echo "║       CFC V7.7 Database Migration Tool               ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";

// 解析命令行参数
$options = getopt('', ['seed', 'fresh', 'force']);
$doSeed = isset($options['seed']);
$doFresh = isset($options['fresh']);
$doForce = isset($options['force']);

// ============================================================
// 1. 加载 .env 配置
// ============================================================
info("Loading .env configuration...");

$envFile = APP_ROOT . '/.env';
if (!file_exists($envFile)) {
    error(".env file not found at: {$envFile}");
    error("Please copy .env.example to .env and configure it.");
    exit(1);
}

$envLines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$env = [];

foreach ($envLines as $line) {
    $line = trim($line);
    if (empty($line) || str_starts_with($line, '#')) {
        continue;
    }
    if (strpos($line, '=') !== false) {
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        $env[$key] = $value;
        putenv("{$key}={$value}");
    }
}

// 获取数据库配置
$dbHost = $env['DB_HOST'] ?? '127.0.0.1';
$dbPort = $env['DB_PORT'] ?? '3306';
$dbName = $env['DB_DATABASE'] ?? 'cy_cfc';
$dbUser = $env['DB_USERNAME'] ?? 'cy_cfc';
$dbPass = $env['DB_PASSWORD'] ?? '';

success("Environment loaded");
info("Database: {$dbName}@{$dbHost}:{$dbPort}");

// ============================================================
// 2. 连接数据库
// ============================================================
info("Connecting to MySQL...");

try {
    // 先连接不指定数据库（用于创建数据库）
    $dsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ]);
    
    // 创建数据库（如果不存在）
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$dbName}`");
    
    success("Connected to MySQL");
    
} catch (PDOException $e) {
    error("Database connection failed: " . $e->getMessage());
    exit(1);
}

// ============================================================
// 3. Fresh 模式：删除所有表
// ============================================================
if ($doFresh) {
    warning("FRESH mode: All tables will be dropped!");
    
    if (!$doForce) {
        echo "Are you sure? (yes/no): ";
        $confirm = trim(fgets(STDIN));
        if ($confirm !== 'yes') {
            info("Migration cancelled.");
            exit(0);
        }
    }
    
    info("Dropping all tables...");
    
    // 禁用外键检查
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        info("  Dropped: {$table}");
    }
    
    // 重新启用外键检查
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    success("All tables dropped");
}

// ============================================================
// 4. 读取并执行 schema.sql
// ============================================================
info("Reading schema.sql...");

$schemaFile = APP_ROOT . '/database/schema.sql';
if (!file_exists($schemaFile)) {
    error("Schema file not found: {$schemaFile}");
    exit(1);
}

$sql = file_get_contents($schemaFile);

// 移除注释行
$sql = preg_replace('/^--.*$/m', '', $sql);

// 按分号分割 SQL 语句
$statements = array_filter(
    array_map('trim', explode(';', $sql)),
    fn($s) => !empty($s) && strlen($s) > 5
);

info("Executing " . count($statements) . " SQL statements...");

$created = 0;
$errors = 0;

foreach ($statements as $statement) {
    if (empty($statement)) continue;
    
    try {
        $pdo->exec($statement);
        
        // 提取表名用于显示
        if (preg_match('/CREATE TABLE.*?`(\w+)`/i', $statement, $matches)) {
            success("  Created table: {$matches[1]}");
            $created++;
        } elseif (preg_match('/INSERT INTO.*?`(\w+)`/i', $statement, $matches)) {
            info("  Inserted into: {$matches[1]}");
        }
    } catch (PDOException $e) {
        // 忽略 "表已存在" 错误
        if ($e->getCode() == '42S01' || strpos($e->getMessage(), 'already exists') !== false) {
            warning("  Table already exists, skipped");
        } else {
            error("  SQL Error: " . $e->getMessage());
            $errors++;
        }
    }
}

echo "\n";
success("Schema migration completed ({$created} tables created, {$errors} errors)");

// ============================================================
// 5. Seeding: 插入测试数据
// ============================================================
if ($doSeed) {
    echo "\n";
    info("Running database seeder...");
    
    // 检查 users 表是否为空
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    
    if ($userCount == 0) {
        info("Users table is empty, creating admin user...");
        
        // 创建管理员账号
        $adminPassword = password_hash('123456', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password, role, api_quota) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute(['admin', 'admin@cfc.local', $adminPassword, 'admin', 10000]);
        
        success("  Created admin user: admin@cfc.local / 123456");
        
        // 创建测试用户
        $testPassword = password_hash('test123', PASSWORD_DEFAULT);
        $stmt->execute(['测试用户', 'test@cfc.local', $testPassword, 'user', 1000]);
        
        success("  Created test user: test@cfc.local / test123");
        
    } else {
        warning("Users table not empty ({$userCount} records), skipping seed");
    }
    
    // 插入一些测试数据到 ai_usage_logs
    $logCount = $pdo->query("SELECT COUNT(*) FROM ai_usage_logs")->fetchColumn();
    
    if ($logCount == 0) {
        info("Creating sample AI usage logs...");
        
        $stmt = $pdo->prepare("
            INSERT INTO ai_usage_logs 
            (user_id, trace_id, provider, model, task_type, prompt_tokens, completion_tokens, total_cost, duration_ms, status, request_ip) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        // 生成一些示例数据
        $providers = ['deepseek', 'openai'];
        $models = ['deepseek-chat', 'gpt-4-turbo', 'gpt-3.5-turbo'];
        $tasks = ['debate', 'chat', 'rag', 'contract_review'];
        
        for ($i = 0; $i < 10; $i++) {
            $traceId = 'trace_' . bin2hex(random_bytes(8));
            $provider = $providers[array_rand($providers)];
            $model = $models[array_rand($models)];
            $task = $tasks[array_rand($tasks)];
            $promptTokens = rand(100, 2000);
            $completionTokens = rand(50, 1000);
            $cost = ($promptTokens * 0.00001) + ($completionTokens * 0.00002); // 示例成本计算
            $duration = rand(500, 5000);
            
            $stmt->execute([
                1, // admin user
                $traceId,
                $provider,
                $model,
                $task,
                $promptTokens,
                $completionTokens,
                $cost,
                $duration,
                'success',
                '127.0.0.1'
            ]);
        }
        
        success("  Created 10 sample usage logs");
    }
    
    success("Seeding completed");
}

// ============================================================
// 6. 输出统计信息
// ============================================================
echo "\n";
echo "╔══════════════════════════════════════════════════════╗\n";
echo "║                  Migration Summary                   ║\n";
echo "╠══════════════════════════════════════════════════════╣\n";

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "║  Tables in database: " . str_pad((string)count($tables), 30) . "  ║\n";

foreach ($tables as $table) {
    $count = $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    echo "║    - {$table}: " . str_pad("{$count} rows", 35 - strlen($table)) . "  ║\n";
}

echo "╚══════════════════════════════════════════════════════╝\n";
echo "\n";

success("Database initialization completed!");
info("You can now start the application.");
echo "\n";
