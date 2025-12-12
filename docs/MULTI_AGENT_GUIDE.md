# CRM_AI_V7.6 多Agent协作完整指南

## 🤖 系统支持的多Agent协作模式

**是的！系统完全支持多个Agent一起工作！**

---

## ✅ 支持的5种协作模式

### 1. 并行执行模式（异步队列）

**特点**: 多个Agent同时独立工作，互不干扰

```php
use Services\AI\Queue\Jobs\RunAgentJob;

$dispatcher = Bootstrap::getDispatcher();
$aiManager = Bootstrap::getAIManager();

// 同时启动3个Agent
$job1 = new RunAgentJob($aiManager, '审查合同...', ['task' => 'contract']);
$job2 = new RunAgentJob($aiManager, '估算工时...', ['task' => 'estimate']);
$job3 = new RunAgentJob($aiManager, '分析风险...', ['task' => 'risk']);

$id1 = $dispatcher->dispatch($job1);
$id2 = $dispatcher->dispatch($job2);
$id3 = $dispatcher->dispatch($job3);

// 3个Agent并行执行！
```

**适用场景**:
- 项目多维度分析（合同、成本、风险同时评估）
- 批量任务处理
- 需要快速结果的场景

---

### 2. 顺序协作模式（Pipeline）

**特点**: Agent按顺序处理，后一个Agent使用前一个的结果

```php
$aiManager = Bootstrap::getAIManager();

// Step 1: 需求分析Agent
$result1 = $aiManager->process("分析需求：开发电商平台");

// Step 2: 工时估算Agent（基于Step 1结果）
$analysis = $result1['response'];
$result2 = $aiManager->process("基于分析估算工时：{$analysis}");

// Step 3: 报价Agent（基于Step 2结果）
$estimation = $result2['response'];
$result3 = $aiManager->process("生成报价：{$estimation}");

// 需求 → 分析 → 估算 → 报价（链式完成）
```

**适用场景**:
- 需求分析 → 技术选型 → 成本估算
- 数据采集 → 清洗 → 分析 → 报告
- 任何需要步骤依赖的流程

---

### 3. 投票/共识模式

**特点**: 多个Agent从不同角度评估，最终达成共识

```php
$aiManager = Bootstrap::getAIManager();

$proposal = "使用React+Node.js开发";

$experts = [
    'security' => '安全专家',
    'performance' => '性能专家',
    'cost' => '成本专家'
];

$votes = [];
foreach ($experts as $role => $name) {
    $result = $aiManager->process(
        "作为{$name}，评估方案（1-10分）：{$proposal}",
        ['role' => $role]
    );
    $votes[$role] = $result;
}

// 综合3个专家意见做决策
```

**适用场景**:
- 技术方案评审
- 风险评估
- 决策支持系统

---

### 4. 主从协作模式（Coordinator）

**特点**: 主Agent分解任务，协调多个子Agent执行

```php
$aiManager = Bootstrap::getAIManager();

// 主Agent：项目经理
$masterResult = $aiManager->process(
    "将'开发CRM系统'分解为3个子任务",
    ['role' => 'project_manager']
);

// 解析主Agent的分解结果
$subTasks = parseSubTasks($masterResult);

// 启动子Agent
foreach ($subTasks as $task) {
    $dispatcher->dispatch(
        new RunAgentJob($aiManager, $task['description'], $task['metadata'])
    );
}

// 主Agent管理，子Agent执行
```

**适用场景**:
- 复杂项目管理
- 任务自动分配
- 工作流编排

---

### 5. 竞争/选优模式

**特点**: 多个Agent生成不同方案，选择最优结果

```php
$aiManager = Bootstrap::getAIManager();

$product = "智能AI客服系统";
$proposals = [];

// 3个Agent生成不同风格的文案
for ($i = 1; $i <= 3; $i++) {
    $result = $aiManager->process(
        "为'{$product}'创作营销文案（风格{$i}）",
        ['style' => $i]
    );
    $proposals[$i] = $result;
}

// 评审Agent选择最优方案
$bestProposal = selectBest($proposals);
```

**适用场景**:
- 内容创作（选最好的）
- A/B测试方案生成
- 质量筛选

---

## 🎯 实际应用场景

### 场景1: 智能合同审查系统

```php
// 3个专家Agent同时审查
$legalAgent = new RunAgentJob($ai, $contract, ['role' => 'legal']);
$financeAgent = new RunAgentJob($ai, $contract, ['role' => 'finance']);
$riskAgent = new RunAgentJob($ai, $contract, ['role' => 'risk']);

$dispatcher->dispatch($legalAgent);
$dispatcher->dispatch($financeAgent);
$dispatcher->dispatch($riskAgent);

// 法务、财务、风控三个维度同时分析
```

### 场景2: 自动化项目管理

```php
// 主Agent分解任务
$pm = $aiManager->process("分解项目任务", ['role' => 'pm']);

// 子Agent执行
$devAgent = new RunAgentJob($ai, "开发{$task1}");
$qaAgent = new RunAgentJob($ai, "测试{$task2}");
$docAgent = new RunAgentJob($ai, "文档{$task3}");

// 自动任务分配和跟踪
```

### 场景3: 智能客服系统

```php
// 客户问题分类Agent
$category = $aiManager->process($question, ['task' => 'classify']);

// 根据分类，路由到专业Agent
switch ($category) {
    case 'technical':
        $techAgent->handle($question);
        break;
    case 'billing':
        $billingAgent->handle($question);
        break;
    case 'general':
        $generalAgent->handle($question);
        break;
}
```

---

## 🔧 核心实现机制

### 1. 异步队列系统

```php
// AIJobDispatcher - 任务分发器
- dispatch($job): 提交任务到队列
- getStatus($jobId): 查询任务状态
- getResult($jobId): 获取执行结果

// AIJobWorker - 任务消费者
- process(): 从队列中取任务并执行
- retry(): 失败重试机制
```

### 2. 任务优先级

```php
// 高优先级任务优先执行
$urgentJob = new RunAgentJob($ai, $task, ['priority' => 10]);
$normalJob = new RunAgentJob($ai, $task, ['priority' => 5]);

$dispatcher->dispatch($urgentJob);  // 先执行
$dispatcher->dispatch($normalJob);  // 后执行
```

### 3. 任务状态追踪

```php
$jobId = $dispatcher->dispatch($job);

// 检查状态
$status = $dispatcher->getStatus($jobId);
// pending → processing → completed/failed

// 获取结果
if ($status === 'completed') {
    $result = $dispatcher->getResult($jobId);
}
```

---

## 📊 性能和资源管理

### 并发控制

```php
// 同时运行的Agent数量限制
$dispatcher->setMaxConcurrentJobs(5);

// 超过限制的任务会排队等待
```

### 超时控制

```php
// 单个Agent最长执行时间
$job = new RunAgentJob($ai, $task, ['timeout' => 60]);
```

### 错误处理

```php
// 失败重试
$job = new RunAgentJob($ai, $task, [
    'max_retries' => 3,
    'retry_delay' => 5  // 秒
]);

// 死信队列（彻底失败的任务）
$deadLetters = $dispatcher->getDeadLetterQueue();
```

---

## 🚀 快速开始

### 示例1: 最简单的并行执行

```php
<?php
require 'vendor/autoload.php';
use Services\AI\Bootstrap;

Bootstrap::initialize();
$ai = Bootstrap::getAIManager();
$dispatcher = Bootstrap::getDispatcher();

// 3个Agent并行工作
$jobs = [
    new RunAgentJob($ai, "任务1"),
    new RunAgentJob($ai, "任务2"),
    new RunAgentJob($ai, "任务3"),
];

foreach ($jobs as $job) {
    $dispatcher->dispatch($job);
}

echo "3个Agent已并行启动！\n";
```

### 示例2: Agent链式协作

```php
$result1 = $ai->process("第一步");
$result2 = $ai->process("第二步：{$result1['response']}");
$result3 = $ai->process("第三步：{$result2['response']}");
```

---

## 📝 运行完整演示

```bash
# 运行多Agent协作演示
wsl php demo_multi_agent.php
```

---

## ✅ 总结

**CRM_AI_V7.6 完全支持多Agent协作！**

支持的模式：
- ✅ 并行执行（异步队列）
- ✅ 顺序协作（Pipeline）
- ✅ 投票决策（共识机制）
- ✅ 主从协作（Coordinator）
- ✅ 竞争选优（多方案）

核心能力：
- ✅ 任务分发和调度
- ✅ 优先级控制
- ✅ 状态追踪
- ✅ 错误重试
- ✅ 资源管理

**可以灵活组合这些模式，构建强大的AI Agent协作系统！**

---

**生成日期**: 2025-12-10
**系统版本**: CRM_AI_V7.6
