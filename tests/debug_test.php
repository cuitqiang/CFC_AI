<?php
require 'vendor/autoload.php';

use Services\AI\Bootstrap;

Bootstrap::initialize();

// 测试 CostCalculator
echo "测试 CostCalculator:\n";
$calc = Bootstrap::getCostCalculator();
$cost1 = $calc->calculateCost('deepseek-v3', 1000, 500);
echo "deepseek-v3 cost: $cost1\n";
echo "cost > 0: " . ($cost1 > 0 ? 'true' : 'false') . "\n\n";

$cost2 = $calc->calculateCost('deepseek-chat', 1000, 500);
echo "deepseek-chat cost: $cost2\n";
echo "cost > 0: " . ($cost2 > 0 ? 'true' : 'false') . "\n\n";

// 测试 UsageTracker
echo "测试 UsageTracker:\n";
$tracker = Bootstrap::getUsageTracker();
$tracker->track('deepseek-v3', 100, 50, 1.5, true);
$stats = $tracker->getStats();
echo "Stats:\n";
print_r($stats);
echo "isset total_requests: " . (isset($stats['total_requests']) ? 'true' : 'false') . "\n";
