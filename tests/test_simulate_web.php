<?php
/**
 * 模拟 Web 请求测试
 */

// 模拟环境
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['action'] = 'chat';

// 创建输入数据
$inputData = json_encode([
    'message' => '你好，我叫王五',
    'user_id' => 'sim_test_001',
    'session_id' => 'sim_session_' . time()
]);

// 创建临时流存储输入
class InputStreamWrapper {
    private static $data = '';
    private $position = 0;
    
    public static function setData($data) {
        self::$data = $data;
    }
    
    public function stream_open($path, $mode, $options, &$opened_path) {
        $this->position = 0;
        return true;
    }
    
    public function stream_read($count) {
        $ret = substr(self::$data, $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }
    
    public function stream_eof() {
        return $this->position >= strlen(self::$data);
    }
    
    public function stream_stat() {
        return [];
    }
}

// 注册自定义流
stream_wrapper_unregister('php');
stream_wrapper_register('php', 'InputStreamWrapper');
InputStreamWrapper::setData($inputData);

// 开启错误显示
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 加载 API
echo "=== 加载 API ===\n";
try {
    require_once __DIR__ . '/public/cuige_api.php';
} catch (Throwable $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "堆栈:\n" . $e->getTraceAsString() . "\n";
}

// 恢复流
stream_wrapper_restore('php');
