<?php
$tests = [
    '我叫小王',
    '我叫小王，是你老板',
    '我叫张三',
    '我的名字叫李四',
    '我是程序员',
    '我叫什么',  // 应该被排除
    '你叫什么',  // 应该不匹配
    '我叫什么名字',  // 应该被排除
    '明天我们去北京',
];

// 排除问句
function shouldSkip($msg) {
    return preg_match('/[?？]|什么|哪|吗$|呢$/', $msg);
}

$pattern = '/我(?:的名字)?叫([\x{4e00}-\x{9fa5}a-zA-Z]{2,10})/u';

echo "测试正则: $pattern\n\n";

foreach ($tests as $test) {
    $matches = [];
    echo "输入: \"$test\"\n";
    
    if (shouldSkip($test)) {
        echo "结果: 【跳过-问句】\n\n";
        continue;
    }
    
    $result = preg_match($pattern, $test, $matches);
    echo "结果: " . ($result ? "匹配成功 -> " . $matches[1] : "无匹配") . "\n\n";
}
