# CFC V7.7 框架文档

## 简介

CFC（CRM Framework Convention）是一套用于构建 AI 驱动的企业级应用的 PHP 框架规范。

## 核心特性

1. **AI 原生支持**：内置 AI 服务提供者抽象，支持多种 AI 模型。
2. **RAG 知识库**：支持文档向量化和语义搜索。
3. **任务队列**：异步处理长时间运行的 AI 任务。
4. **多智能体架构**：支持多个 AI Agent 协作。

## 目录结构

```
src/
├── Bootstrap/     # 启动和配置
├── Config/        # 配置管理
├── Controllers/   # API 控制器
├── Core/          # 核心组件
└── Services/      # 业务服务
```

## 快速开始

### 1. 配置数据库

在 `.env` 文件中配置数据库连接：

```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cy_cfc
```

### 2. 运行迁移

```bash
php src/Bootstrap/migrate.php
```

### 3. 启动服务

使用 Nginx + PHP-FPM 或内置服务器：

```bash
php -S 0.0.0.0:5555 -t public
```

## API 文档

### 聊天接口

```
POST /api/chat
Content-Type: application/json

{
    "model": "deepseek-v3",
    "messages": [
        {"role": "user", "content": "Hello"}
    ]
}
```

### RAG 知识库

上传文档：
```
POST /api/rag/upload
Content-Type: multipart/form-data

file: <文件>
```

搜索知识库：
```
POST /api/rag/search
Content-Type: application/json

{
    "query": "搜索词",
    "top_k": 5
}
```

## 最佳实践

1. 使用本地 Embedding 时，确保文档内容足够丰富。
2. 合理设置 chunk_size 和 chunk_overlap。
3. 定期清理无用的向量数据。
