# CRM_ERP_V7.6 AI Agent System

企业级 AI Agent 系统 - 完整实现版

> **版本**: V7.6 Enterprise
> **构建日期**: 2025-12-11
> **架构师**: 小小（高级程序员）

---

## 特性

✅ **管道式架构** - 8个可扩展的处理阶段
✅ **多模型支持** - Deepseek、OpenAI及自定义模型
✅ **工具调用** - Function Calling + 沙箱执行
✅ **RAG系统** - 文档向量化 + 语义搜索
✅ **记忆管理** - 短期/摘要/向量三层存储
✅ **任务队列** - 异步处理 + 死信队列
✅ **成本追踪** - 实时计算 + 趋势分析
✅ **企业级** - PSR-4 + 依赖注入 + 完整文档

---

## 系统要求

- PHP >= 8.3
- Composer
- ext-curl, ext-json, ext-mbstring

---

## 快速开始

### 1. 安装

```bash
composer install
```

### 2. 配置

```bash
cp .env.example .env
# 编辑 .env 填入 API Keys
```

### 3. 运行

```php
php examples/00_quickstart.php
```

---

## 基础使用

```php
use Services\AI\Bootstrap;

// 一键初始化
Bootstrap::initialize();

// 获取 AIManager
$ai = Bootstrap::getAIManager();

// 发送请求
$response = $ai->process("你好", [
    'user_id' => 'user_001',
    'model' => 'deepseek-chat',
]);

echo $response['message'];
```

---

## 架构概览

```
用户输入
  ↓
Pipeline 流水线
├─ 0_RateLimit      → 频率限制
├─ 1_SafetyCheck    → 安全检查
├─ 2_LoadMemory     → 加载记忆
├─ 3_PlanTools      → 规划工具
├─ 4_CallModel      → 调用模型
├─ 5_ExecuteTool    → 执行工具
├─ 6_SaveMemory     → 保存记忆
└─ 7_FormatOutput   → 格式化输出
  ↓
返回结果
```

---

## 目录结构

```
src/Services/AI/
├── Pipeline/          # 管道系统 (10 文件)
├── Providers/         # AI模型提供者 (5 文件)
├── Tools/             # 工具系统 (9 文件)
├── Core/              # 核心组件 (7 文件)
├── Memory/            # 记忆管理 (4 文件)
├── Tasks/             # 任务类 (4 文件)
├── Queue/             # 队列系统 (5 文件)
└── Analytics/         # 分析统计 (2 文件)

总计：49 个 PHP 文件
```

---

## 示例文件

| 文件 | 说明 |
|------|------|
| `00_quickstart.php` | 快速开始 |
| `01_basic_usage.php` | 基础对话 |
| `02_tool_usage.php` | 工具调用 |
| `03_task_execution.php` | 任务执行 |
| `04_queue_processing.php` | 队列处理 |
| `05_cost_tracking.php` | 成本追踪 |

---

## 可用工具

### System 工具
- **DatabaseReader** - 数据库查询（只读）
- **HttpSearch** - 网络搜索
- **TimeCalculator** - 时间计算

### Business 工具
- **ContractFinder** - 合同查找
- **EmailSender** - 邮件发送
- **ReportBuilder** - 报表生成

---

## 任务类型

- **GeneralAgent** - 通用对话
- **ContractReview** - 合同审查
- **WorktimeEstimate** - 工时估算

---

## 配置选项

| 配置项 | 默认值 |
|--------|--------|
| `DEFAULT_MODEL` | deepseek-chat |
| `RATE_LIMIT_PER_MINUTE` | 10 |
| `RATE_LIMIT_PER_HOUR` | 100 |
| `MAX_INPUT_LENGTH` | 10000 |
| `MAX_TOOL_ITERATIONS` | 5 |
| `MAX_SHORT_TERM_MESSAGES` | 20 |

---

## 成本优化

### 模型定价 (per 1M tokens)

| 模型 | 输入 | 输出 |
|------|------|------|
| deepseek-chat | $0.14 | $0.28 |
| gpt-4o-mini | $0.15 | $0.60 |
| gpt-4o | $2.50 | $10.00 |

### 使用建议

```php
// 计算成本
$cost = Bootstrap::getCostCalculator()
    ->calculateCost('deepseek-chat', 1000, 500);

// 预测月度成本
$prediction = Bootstrap::getCostCalculator()
    ->predictMonthlyCost('deepseek-chat', 1000, 500, 200);
```

---

## 扩展开发

### 添加新工具

```php
use Services\AI\Tools\BaseTool;

class MyTool extends BaseTool
{
    protected function initialize(): void
    {
        $this->name = 'my_tool';
        $this->description = '我的工具';
        $this->parameters = [/* JSON Schema */];
    }

    public function execute(array $arguments): array
    {
        return $this->success($result);
    }
}
```

### 添加新任务

```php
use Services\AI\Tasks\BaseTask;

class MyTask extends BaseTask
{
    protected function initialize(): void
    {
        $this->name = 'my_task';
        $this->description = '我的任务';
    }

    public function execute(array $input): array
    {
        return $this->success($result);
    }
}
```

---

## 性能优化

1. ✅ 启用 OPcache
2. ✅ 使用队列处理长任务
3. ✅ 合理配置内存限制
4. ✅ 选择成本更低的模型
5. ✅ 启用 Prompt Caching（Deepseek）

---

## 安全建议

1. ✅ 妥善保管 API Keys
2. ✅ 使用环境变量存储敏感信息
3. ✅ 启用频率限制
4. ✅ 验证用户输入
5. ✅ 在沙箱中执行工具
6. ✅ 限制数据库访问权限

---

## 故障排查

### API 调用失败
- 检查 API Key
- 检查网络连接
- 查看错误日志

### 工具未执行
- 确认工具已注册
- 检查工具定义格式
- 查看 Pipe 日志

### 内存溢出
- 减少 `MAX_SHORT_TERM_MESSAGES`
- 清理旧记忆数据
- 使用队列处理大任务

---

## 许可证

MIT License

---

## 技术支持

- 文档：本 README
- 示例：`examples/` 目录
- 架构：`CLAUDE.md`

---

**构建者：小小（高级程序员）| CRM_ERP_V7.6 首席后端架构师**
