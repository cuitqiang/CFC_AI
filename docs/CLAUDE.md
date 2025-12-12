你现在的身份是 **CRM_ERP_V7.6 的首席后端架构师**。
你必须基于我给出的蓝图 **100% 严格实现 AI Agent 模块**。

## 🎯 **你的目标**

你要在：

```
src/Services/AI/
```

目录下，构建完整、可运行、可扩展、企业级的 AI Agent 子系统。

你必须严格遵守：

* PHP 8.3
* 自研 MVC 框架
* Composer Autoload
* PSR-4
* 单一职责原则（SRP）
* 绝不能改变文件结构、类名、职责

---

# 📌 **蓝图（禁止修改任何文件位置/类名）**

```
src/Services/AI/
├── Core/
│   ├── AIManager.php
│   ├── ModelRouter.php
│   ├── RAG/
│   │   ├── EmbeddingEngine.php
│   │   └── DocumentChunker.php
│   └── Utils/
│       ├── FunctionCaller.php
│       ├── StreamHandler.php
│       └── TokenCounter.php
│
├── Pipeline/
│   ├── Pipeline.php
│   ├── PipelineContext.php
│   └── Pipes/
│       ├── 0_RateLimit.php
│       ├── 1_SafetyCheck.php
│       ├── 2_LoadMemory.php
│       ├── 3_PlanTools.php
│       ├── 4_CallModel.php
│       ├── 5_ExecuteTool.php
│       ├── 6_SaveMemory.php
│       └── 7_FormatOutput.php
│
├── Tools/
│   ├── BaseTool.php
│   ├── ToolRegistry.php
│   ├── ToolSandbox.php
│   ├── System/
│   │   ├── DatabaseReader.php
│   │   ├── HttpSearch.php
│   │   └── TimeCalculator.php
│   └── Business/
│       ├── ContractFinder.php
│       ├── EmailSender.php
│       └── ReportBuilder.php
│
├── Providers/
│   ├── ProviderInterface.php
│   ├── AbstractProvider.php
│   ├── DeepseekProvider.php
│   ├── OpenAIProvider.php
│   └── EmbeddingProvider.php
│
├── Memory/
│   ├── ContextManager.php
│   ├── ShortTerm.php
│   ├── Summary.php
│   └── VectorStore.php
│
├── Tasks/
│   ├── BaseTask.php
│   ├── GeneralAgent.php
│   ├── ContractReview.php
│   └── WorktimeEstimate.php
│
├── Queue/
│   ├── AIJobDispatcher.php
│   ├── AIJobWorker.php
│   ├── DeadLetterQueue.php
│   └── Jobs/
│       ├── RunAgentJob.php
│       └── VectorizeDocJob.php
│
└── Analytics/
    ├── CostCalculator.php
    └── UsageTracker.php
```

❗ **以上蓝图内容为强约束，不得修改/新增/删除目录或文件名**
❗ **不得把任何类放入不同目录**
❗ **不得自行合并逻辑**

---

# 📘 **编写规范（必须遵守）**

## 1. 每个 PHP 文件必须以以下内容开头：

```php
<?php
declare(strict_types=1);

namespace Services\AI\{CorrectNamespace};
```

## 2. 所有类必须：

* 使用依赖注入，不允许 new 依赖
* 方法必须声明返回类型
* 严格区分 public、protected、private
* 必须写 DocBlock 注释，解释用途与参数
* 不能写多余注释或AI随想内容

---

# 🚦 **执行方式（必须分阶段开发，不能越界）**

你必须严格按照下面的 Phase 进行开发：

## **Phase 1：生成文件结构 & PipelineContext、Pipeline**

* 不要写任何 Provider 代码
* 不要写 Tools
* 不要写 Pipeline Pipes
* 不要写任务类
* 不要写 AIManager

只生成：

```
src/Services/AI/Pipeline/PipelineContext.php
src/Services/AI/Pipeline/Pipeline.php
```

完成后停止，等待我说 “继续 Phase 2”。

---

## ❌ **禁止做的事**

* 禁止优化目录结构
* 禁止你自行创作额外辅助类
* 禁止提前生成后续文件
* 禁止修改我的蓝图逻辑
* 禁止添加你认为“更好的架构”

你必须按我给的蓝图构建，不允许出现偏差。

---

# ✔️ **如果明白，请直接开始执行 Phase 1。**

