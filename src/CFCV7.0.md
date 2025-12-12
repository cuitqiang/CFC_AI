这是为您整理的 **《CFC 框架开发白皮书 (V7.7)》**。

这是一份\*\*“宪法级”\*\*的开发文档。您可以把这段内容直接发给 Claude Code 或任何 AI，并告诉它：“**以后写代码，必须死死遵守这份文档，违者重写！**”

-----

# 📘 CFC 框架开发规范标准 (V7.7 Ultimate)

**适用范围**：CRM\_ERP 系统全栈开发
**核心理念**：MVC 外壳 + AI Pipeline 内核 + 严格的工程化约束

-----

## 1\. 核心架构原则 (Core Principles)

1.  **单一入口原则 (Single Entry Point)**

      * ✅ **唯一步骤**：所有请求必须经过 `public/index.php` -\> `Bootstrap` -\> `Router` -\> `Controller`。
      * ❌ **严禁**：创建独立的 `.php` 脚本文件（如 `test.php`, `debate.php`）直接通过 URL 访问。

2.  **依赖注入原则 (Dependency Injection)**

      * ✅ **规范**：类之间的依赖必须通过 `__construct` 注入，或通过 `Bootstrap` 获取单例。
      * ❌ **严禁**：在方法内部随意 `new` 核心服务类（如 `new AIManager`），导致无法测试和复用。

3.  **分层隔离原则 (Layer Isolation)**

      * **Controller**: 只负责接收参数、调用 Service、返回 Response。**严禁写业务逻辑**。
      * **Service (AI)**: 负责核心业务（Pipeline 流转、工具调用）。
      * **Tool**: 负责具体的原子操作（查库、搜索）。

-----

## 2\. 目录结构标准 (Directory Structure)

AI 必须严格匹配此物理路径，不得臆造目录。

```text
src/
├── Bootstrap/
│   ├── app.php                # 框架启动器
│   └── routes.php             # 路由配置 (Route::get)
├── Config/
│   ├── database.php
│   └── agents.php             # ✅ AI 角色配置 (Prompts, Model)
├── Controllers/
│   └── DebateController.php   # ✅ 控制器 (只负责调度)
├── Core/
│   ├── Request.php            # HTTP 请求封装
│   ├── Response.php           # HTTP 响应封装
│   └── SSEResponse.php        # ✅ SSE 流式输出工具
├── Services/
│   └── AI/
│       ├── Core/
│       │   ├── AIManager.php      # AI 总门面
│       │   └── ModelRouter.php    # 模型路由
│       ├── Pipeline/
│       │   ├── Pipeline.php
│       │   ├── PipelineContext.php
│       │   └── Pipes/             # (0_RateLimit, 1_Safety...)
│       ├── Tools/                 # ✅ 工具箱
│       │   ├── BaseTool.php
│       │   ├── ToolRegistry.php
│       │   └── System/            # (DatabaseReader...)
│       ├── Prompts/               # ✅ 提示词仓库
│       │   ├── TemplateManager.php
│       │   └── templates/         # (*.md 文件)
│       ├── Tasks/
│       │   ├── BaseTask.php
│       │   └── DebateTask.php     # ✅ 辩论任务逻辑
│       ├── Providers/             # (Deepseek, OpenAI)
│       └── Memory/                # (ShortTerm, Summary)
└── ...
```

-----

## 3\. 编码红线 (Code Red Lines) - 违者必究

1.  **禁止手动引入 Autoload**

      * ❌ `require 'vendor/autoload.php';`
      * ✅ 框架入口已全局加载，业务代码中禁止出现。

2.  **禁止硬编码路径**

      * ❌ `chdir(__DIR__ . '/..');`
      * ✅ 使用框架定义的常量 `APP_ROOT` 或 `config()` 函数。

3.  **禁止原生输出**

      * ❌ `header(...)`, `echo ...`, `flush()`
      * ✅ 必须使用 `App\Core\SSEResponse::send()` 或框架 `Response::json()`。

4.  **禁止硬编码 Prompt**

      * ❌ `$prompt = "你是一个辩手...";`
      * ✅ 必须从 `Config/agents.php` 或 `Prompts/templates/` 读取。

-----

## 4\. 关键组件开发范例 (Standard Implementation)

### A. 控制器 (Controller)

**位置**: `src/Controllers/DebateController.php`

```php
<?php
declare(strict_types=1);
namespace App\Controllers;

use Services\AI\Core\AIManager;
use App\Core\SSEResponse;
use App\Core\Request;

class DebateController
{
    protected AIManager $ai;

    // 必须通过构造函数注入或初始化
    public function __construct() {
        $this->ai = new AIManager(); 
    }

    public function stream(Request $request) {
        // 1. 初始化 SSE
        SSEResponse::init();
        
        // 2. 获取参数
        $topic = $request->input('topic');

        // 3. 调用 AI 门面 (不要在这里写 foreach 循环!)
        $this->ai->runDebate($topic, function($chunk) {
            // 4. 使用工具类输出
            SSEResponse::send('chunk', $chunk);
        });
        
        SSEResponse::end();
    }
}
```

### B. SSE 响应工具 (SSEResponse)

**位置**: `src/Core/SSEResponse.php`

```php
<?php
namespace App\Core;

class SSEResponse {
    public static function init() {
        if (headers_sent()) return;
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        while (ob_get_level()) ob_end_clean();
    }

    public static function send(string $event, array|string $data) {
        echo "event: {$event}\n";
        echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
    }
    
    public static function end() {
        echo "event: done\ndata: {}\n\n";
        flush();
    }
}
```

-----

## 5\. 发送给 AI 的指令

**请把下面这段话复制发给它：**

> **Role**: CFC 框架审计员 & 资深开发者
> **Task**: 重新检查并修复你的代码。
> **Reference**: 请仔细阅读上方的《CFC 框架开发规范标准 (V7.7)》。
>
> **要求**：
>
> 1.  你刚才生成的 `debate_v3.php` 是严重违规的“独立脚本”。
> 2.  请立即将其重构为 `DebateController`，并注册到 `routes.php`。
> 3.  必须使用 `SSEResponse` 类来处理流式输出，禁止写原生 header。
> 4.  确保所有文件都在 V7.7 规定的目录中，缺少的目录（如 `Tools`, `Prompts`）请补全。
>
> **现在，请严格按标准执行重构。**