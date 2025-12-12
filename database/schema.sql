-- ============================================================
-- CFC V7.7 数据库结构定义
-- Database: cy_cfc
-- Charset: utf8mb4
-- Engine: InnoDB
-- ============================================================

-- 创建数据库 (如果不存在)
-- CREATE DATABASE IF NOT EXISTS `cy_cfc` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE `cy_cfc`;

-- ============================================================
-- 1. 用户表 (users)
-- 基础用户信息，关联 ai_usage_logs
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL COMMENT '用户名',
    `email` varchar(255) NOT NULL COMMENT '邮箱',
    `password` varchar(255) NOT NULL COMMENT '密码(加密存储)',
    `role` enum('admin','user','guest') NOT NULL DEFAULT 'user' COMMENT '角色',
    `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态: 1=启用, 0=禁用',
    `api_quota` int(11) NOT NULL DEFAULT 1000 COMMENT 'API调用配额/天',
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_email` (`email`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表';

-- ============================================================
-- 2. AI 用量审计表 (ai_usage_logs)
-- 用于精算成本，每一分钱 Token 都要记账
-- ============================================================
CREATE TABLE IF NOT EXISTS `ai_usage_logs` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` int(11) UNSIGNED NOT NULL COMMENT '调用用户ID',
    `trace_id` varchar(64) NOT NULL COMMENT '全链路追踪ID',
    `provider` varchar(32) NOT NULL COMMENT '服务商(deepseek/openai)',
    `model` varchar(64) NOT NULL COMMENT '模型名称',
    `task_type` varchar(64) DEFAULT NULL COMMENT '任务类型(debate/chat/rag)',
    `prompt_tokens` int(11) DEFAULT 0 COMMENT '提问消耗',
    `completion_tokens` int(11) DEFAULT 0 COMMENT '回答消耗',
    `total_tokens` int(11) GENERATED ALWAYS AS (`prompt_tokens` + `completion_tokens`) STORED COMMENT '总Token',
    `total_cost` decimal(10,6) DEFAULT 0.000000 COMMENT '总成本(元)',
    `duration_ms` int(11) DEFAULT 0 COMMENT '耗时(毫秒)',
    `status` enum('success','failed','timeout') DEFAULT 'success' COMMENT '调用状态',
    `error_message` text DEFAULT NULL COMMENT '错误信息',
    `request_ip` varchar(45) DEFAULT NULL COMMENT '请求IP',
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_time` (`user_id`, `created_at`),
    KEY `idx_trace` (`trace_id`),
    KEY `idx_provider_model` (`provider`, `model`),
    KEY `idx_created` (`created_at`),
    CONSTRAINT `fk_usage_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI调用审计日志';

-- ============================================================
-- 3. RAG 向量存储表 (ai_vectors)
-- 用于"企业大脑"，存储切片后的文档知识
-- ============================================================
CREATE TABLE IF NOT EXISTS `ai_vectors` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `doc_hash` char(32) NOT NULL COMMENT '文件MD5去重',
    `file_name` varchar(255) NOT NULL COMMENT '原始文件名',
    `file_path` varchar(512) NOT NULL COMMENT '原始文件路径',
    `file_type` varchar(32) DEFAULT NULL COMMENT '文件类型(pdf/docx/txt)',
    `chunk_index` int(11) NOT NULL COMMENT '切片序号',
    `chunk_total` int(11) DEFAULT NULL COMMENT '总切片数',
    `content` text NOT NULL COMMENT '切片文本内容',
    `content_length` int(11) GENERATED ALWAYS AS (CHAR_LENGTH(`content`)) STORED COMMENT '内容长度',
    `embedding` json NOT NULL COMMENT '向量数据(1536维)',
    `embedding_model` varchar(64) DEFAULT 'text-embedding-3-small' COMMENT '向量模型',
    `metadata` json DEFAULT NULL COMMENT '额外元数据(页码/作者)',
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_hash` (`doc_hash`),
    KEY `idx_file_path` (`file_path`(255)),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='RAG向量知识库';

-- ============================================================
-- 4. 异步任务队列表 (ai_jobs)
-- 用于处理耗时任务，防止超时
-- ============================================================
CREATE TABLE IF NOT EXISTS `ai_jobs` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `queue` varchar(32) NOT NULL DEFAULT 'default' COMMENT '队列名称',
    `job_type` varchar(64) NOT NULL COMMENT '任务类型',
    `payload` longtext NOT NULL COMMENT '任务数据JSON',
    `priority` tinyint(3) UNSIGNED NOT NULL DEFAULT 5 COMMENT '优先级(1-10, 1最高)',
    `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT '已重试次数',
    `max_attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 3 COMMENT '最大重试次数',
    `status` enum('pending','processing','completed','failed','dead') DEFAULT 'pending' COMMENT '状态',
    `result` longtext DEFAULT NULL COMMENT '执行结果',
    `error` text DEFAULT NULL COMMENT '错误信息',
    `reserved_at` int(10) UNSIGNED DEFAULT NULL COMMENT '被锁定时间戳',
    `available_at` int(10) UNSIGNED NOT NULL COMMENT '何时可执行',
    `started_at` timestamp NULL DEFAULT NULL COMMENT '开始执行时间',
    `completed_at` timestamp NULL DEFAULT NULL COMMENT '完成时间',
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_queue_status` (`queue`, `status`, `available_at`),
    KEY `idx_status` (`status`),
    KEY `idx_reserved` (`reserved_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI异步任务队列';

-- ============================================================
-- 5. 死信队列表 (ai_dead_letters)
-- 存储失败超过最大重试次数的任务
-- ============================================================
CREATE TABLE IF NOT EXISTS `ai_dead_letters` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `original_job_id` bigint(20) UNSIGNED NOT NULL COMMENT '原任务ID',
    `queue` varchar(32) NOT NULL COMMENT '原队列名称',
    `job_type` varchar(64) NOT NULL COMMENT '任务类型',
    `payload` longtext NOT NULL COMMENT '任务数据',
    `attempts` tinyint(3) UNSIGNED NOT NULL COMMENT '重试次数',
    `last_error` text NOT NULL COMMENT '最后错误信息',
    `failed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '失败时间',
    `resolved_at` timestamp NULL DEFAULT NULL COMMENT '处理时间',
    `resolution` varchar(32) DEFAULT NULL COMMENT '处理方式(retry/ignore/manual)',
    PRIMARY KEY (`id`),
    KEY `idx_original_job` (`original_job_id`),
    KEY `idx_failed_at` (`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='死信队列';

-- ============================================================
-- 6. AI 对话历史表 (ai_conversations)
-- 存储对话上下文，支持多轮对话
-- ============================================================
CREATE TABLE IF NOT EXISTS `ai_conversations` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` varchar(64) NOT NULL COMMENT '会话ID',
    `user_id` int(11) UNSIGNED DEFAULT NULL COMMENT '用户ID',
    `role` enum('system','user','assistant','tool') NOT NULL COMMENT '角色',
    `content` text NOT NULL COMMENT '消息内容',
    `tokens` int(11) DEFAULT 0 COMMENT 'Token数',
    `metadata` json DEFAULT NULL COMMENT '元数据(工具调用等)',
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_session` (`session_id`),
    KEY `idx_user_session` (`user_id`, `session_id`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI对话历史';

-- ============================================================
-- 7. 系统配置表 (system_config)
-- 运行时可修改的系统配置
-- ============================================================
CREATE TABLE IF NOT EXISTS `system_config` (
    `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `key` varchar(100) NOT NULL COMMENT '配置键',
    `value` text NOT NULL COMMENT '配置值',
    `type` enum('string','int','float','bool','json') DEFAULT 'string' COMMENT '值类型',
    `description` varchar(255) DEFAULT NULL COMMENT '配置说明',
    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统配置表';

-- ============================================================
-- 初始配置数据
-- ============================================================
INSERT INTO `system_config` (`key`, `value`, `type`, `description`) VALUES
('ai_cost_limit_daily', '50.00', 'float', '每日AI消费上限(元)'),
('ai_default_provider', 'deepseek', 'string', '默认AI提供商'),
('ai_default_model', 'deepseek-chat', 'string', '默认模型'),
('ai_timeout', '120', 'int', 'AI请求超时时间(秒)'),
('maintenance_mode', 'false', 'bool', '维护模式开关')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
