# CRM_AI_V7.6 安装运行指南

## 快速开始（3步）

### 方法 1：使用安装脚本（推荐）

1. **双击运行** `install.bat`
2. **编辑** `.env` 文件，填入 API Keys
3. **运行测试** `test.bat`

### 方法 2：手动安装

请按照下面的详细步骤操作。

---

## 详细安装步骤

### 步骤 1: 安装 PHP 8.3

#### Windows 安装：

1. 访问 https://windows.php.net/download/
2. 下载 **PHP 8.3 Thread Safe (x64)** 版本
3. 解压到 `C:\PHP`
4. 添加到系统 PATH：
   - 右键"此电脑" → 属性 → 高级系统设置
   - 环境变量 → 系统变量 → Path → 编辑 → 新建
   - 添加 `C:\PHP`
5. 验证安装：
   ```cmd
   php -v
   ```

#### 启用必需扩展：

编辑 `C:\PHP\php.ini`，取消以下行的注释（删除前面的分号）：

```ini
extension=curl
extension=mbstring
extension=openssl
```

---

### 步骤 2: 安装 Composer

1. 访问 https://getcomposer.org/download/
2. 下载 **Composer-Setup.exe**
3. 运行安装程序
4. 验证安装：
   ```cmd
   composer --version
   ```

---

### 步骤 3: 安装项目依赖

```cmd
cd H:\Desktop\RUST\CRM_AI_V7
composer install
```

---

### 步骤 4: 配置环境变量

1. 复制配置文件：
   ```cmd
   copy .env.example .env
   ```

2. 编辑 `.env` 文件，填入你的 API Keys：
   ```env
   # Deepseek API
   DEEPSEEK_API_KEY=sk-xxxxxxxxxxxxx
   DEEPSEEK_BASE_URL=https://api.deepseek.com/v1

   # OpenAI API (可选)
   OPENAI_API_KEY=sk-xxxxxxxxxxxxx
   OPENAI_BASE_URL=https://api.openai.com/v1
   ```

#### 如何获取 API Keys：

**Deepseek（推荐，性价比高）：**
1. 访问 https://platform.deepseek.com/
2. 注册账号
3. API Keys → 创建新密钥

**OpenAI（可选）：**
1. 访问 https://platform.openai.com/
2. 注册账号
3. API Keys → Create new secret key

---

### 步骤 5: 测试系统

#### 方法 1：运行组件测试

```cmd
php test_system.php
```

这将测试所有核心组件，不需要 API Key。

#### 方法 2：运行快速示例

```cmd
php examples\00_quickstart.php
```

需要配置 API Key。

---

## 运行示例

### 1. 基础对话

```cmd
php examples\01_basic_usage.php
```

### 2. 工具调用

```cmd
php examples\02_tool_usage.php
```

### 3. 任务执行

```cmd
php examples\03_task_execution.php
```

### 4. 队列处理

```cmd
php examples\04_queue_processing.php
```

### 5. 成本追踪

```cmd
php examples\05_cost_tracking.php
```

---

## 故障排查

### 问题 1: php 命令未找到

**解决方案：**
- 确认 PHP 已添加到系统 PATH
- 重启命令行窗口
- 使用完整路径：`C:\PHP\php.exe test_system.php`

### 问题 2: composer 命令未找到

**解决方案：**
- 重新安装 Composer
- 使用完整路径：`C:\ProgramData\ComposerSetup\bin\composer.bat install`

### 问题 3: API 调用失败

**解决方案：**
- 检查 `.env` 文件中的 API Key 是否正确
- 确认网络连接正常
- 检查 API 额度是否充足

### 问题 4: Class not found 错误

**解决方案：**
```cmd
composer dump-autoload
```

---

## 验证安装

运行以下命令，如果全部显示 ✅，说明安装成功：

```cmd
php test_system.php
```

预期输出：

```
=== CRM_AI_V7.6 系统测试 ===

1. 测试配置加载...
   ✅ 配置加载成功

2. 测试 PipelineContext...
   ✅ PipelineContext 创建成功

3. 测试 Pipeline...
   ✅ Pipeline 执行成功

...

=== ✅ 所有测试通过！===
```

---

## 下一步

1. ✅ 系统测试通过
2. ✅ 配置 API Keys
3. ✅ 运行示例代码
4. 📖 阅读 `README_V7.6.md` 了解更多功能
5. 🚀 开始集成到你的项目

---

## 需要帮助？

- 文档：`README_V7.6.md`
- 示例：`examples/` 目录
- 架构：`CLAUDE.md`

---

**快速启动命令汇总：**

```cmd
# 安装
install.bat

# 测试
test.bat

# 或手动运行
php test_system.php
php examples\00_quickstart.php
```
