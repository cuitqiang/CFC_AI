# 🎭 AI Agent 辩论厅 - 使用说明

## 📖 简介

这是一个基于多 Agent 协作的实时辩论系统，特点：

- 🤖 **4个 AI Agent**：正方、反方、中立分析师、魔鬼代言人
- 💬 **实时对话**：Server-Sent Events (SSE) 流式输出
- 🎯 **智能总结**：自动生成辩论总结
- 🎨 **精美界面**：响应式设计，实时状态显示

## 🚀 快速开始

### 1. 启动服务器

在 WSL 终端中运行：

```bash
cd /mnt/h/Desktop/RUST/CRM_AI_V7
php -S 0.0.0.0:6666 -t public/
```

或者使用启动脚本：

```bash
wsl bash start_debate_server.sh
```

### 2. 访问界面

在浏览器中打开：

```
http://localhost:6666
```

### 3. 开始辩论

1. 输入辩论主题（或选择预设话题）
2. 点击「🚀 开始辩论」
3. 观看 4 个 Agent 实时辩论
4. 查看智能总结

## 🎯 示例主题

- AI 是否会取代人类工作？
- 远程办公 vs 现场办公
- 996 工作制的利弊
- 教育应该注重考试还是能力？
- 社交媒体的利与弊
- 电动汽车是否是未来？

## 🤖 Agent 角色

### 👍 正方辩手
支持观点，提供有力论据和数据支持

### 👎 反方辩手
质疑观点，指出潜在问题和风险

### ⚖️ 中立分析师
客观分析，提供全面视角

### 😈 魔鬼代言人
挑战常规思维，提供反直觉观点

## 🔧 技术架构

### 前端
- HTML5 + CSS3
- JavaScript (SSE)
- 响应式设计

### 后端
- PHP 8.3
- Services\AI\Bootstrap
- AIManager (多模型支持)
- Server-Sent Events (SSE)

### 流程
```
用户输入主题
    ↓
后端接收请求
    ↓
建立 SSE 连接
    ↓
Agent 1 发言（流式输出）
    ↓
Agent 2 发言（流式输出）
    ↓
Agent 3 发言（流式输出）
    ↓
Agent 4 发言（流式输出）
    ↓
生成总结（流式输出）
    ↓
辩论完成
```

## 📁 文件结构

```
CRM_AI_V7/
├── public/
│   ├── index.html          # 前端界面
│   └── debate.php          # 后端 API（SSE）
├── src/Services/AI/        # AI 核心服务
├── .env                    # 环境配置
└── start_debate_server.sh  # 启动脚本
```

## ⚙️ 配置

确保 `.env` 文件包含以下配置：

```env
DEEPSEEK_API_KEY=your_api_key_here
DEEPSEEK_BASE_URL=https://tbnx.plus7.plus/v1
DEFAULT_MODEL=deepseek-chat
```

## 🐛 故障排除

### 服务器无法启动
```bash
# 检查端口是否被占用
wsl lsof -i :6666

# 杀死占用进程
wsl lsof -ti:6666 | xargs kill -9
```

### 无法连接 SSE
- 检查防火墙设置
- 确认服务器正常运行
- 查看浏览器控制台错误信息

### AI 响应失败
- 检查 API Key 是否正确
- 查看 API 余额
- 检查网络连接

## 📊 性能优化

### 调整 Agent 响应长度
在 `debate.php` 中修改：

```php
'max_tokens' => 500,  // 调整最大 token 数
```

### 调整流式输出速度
```php
usleep(50000);  // 调整延迟（微秒）
```

### 调整温度参数
```php
'temperature' => 0.8,  // 0.0-1.0，越高越有创意
```

## 🎨 自定义

### 添加更多 Agent
在 `debate.php` 的 `$agents` 数组中添加：

```php
[
    'id' => 'expert',
    'name' => '领域专家',
    'role' => '专业分析',
    'system_prompt' => "你的提示词..."
],
```

### 修改界面样式
编辑 `public/index.html` 中的 `<style>` 部分

### 更换 AI 模型
在 `.env` 中修改：

```env
DEFAULT_MODEL=deepseek-v3
# 或
DEFAULT_MODEL=gpt-4
```

## 📝 命令速查

```bash
# 启动服务器
wsl php -S 0.0.0.0:6666 -t public/

# 后台启动
wsl bash -c "cd /mnt/h/Desktop/RUST/CRM_AI_V7 && nohup php -S 0.0.0.0:6666 -t public/ > /tmp/debate.log 2>&1 &"

# 查看日志
wsl tail -f /tmp/debate.log

# 停止服务器
wsl pkill -f "php -S.*6666"

# 检查端口
wsl netstat -tlnp | grep 6666
```

## 🌟 特色功能

- ✅ **实时流式输出**：逐句显示 Agent 发言
- ✅ **多 Agent 协作**：4 个不同角色的 Agent
- ✅ **智能总结**：自动综合各方观点
- ✅ **状态追踪**：实时显示每个 Agent 的状态
- ✅ **进度显示**：可视化辩论进度
- ✅ **错误处理**：完善的异常处理机制
- ✅ **响应式设计**：支持移动端和桌面端

## 💡 使用技巧

1. **选择好主题**：争议性强的话题效果更好
2. **耐心等待**：AI 生成需要一些时间
3. **多次尝试**：不同主题会有不同的辩论风格
4. **观察细节**：注意每个 Agent 的论证逻辑
5. **总结参考**：最后的总结往往最有价值

## 📞 技术支持

如有问题，请检查：
1. PHP 版本 >= 8.3
2. 所有依赖已安装
3. API Key 配置正确
4. 网络连接正常

---

**享受 AI Agent 辩论的乐趣吧！** 🎉
