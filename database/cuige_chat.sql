-- ============================================================
-- 崔哥语音聊天 AI 数据库结构
-- 存储所有对话历史，支持长期记忆
-- ============================================================

-- 对话会话表
CREATE TABLE IF NOT EXISTS `cuige_sessions` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` varchar(64) NOT NULL COMMENT '会话唯一ID',
    `user_id` varchar(64) DEFAULT 'default' COMMENT '用户标识',
    `title` varchar(255) DEFAULT NULL COMMENT '会话标题（自动生成）',
    `summary` text DEFAULT NULL COMMENT '会话摘要',
    `total_messages` int(11) DEFAULT 0 COMMENT '消息总数',
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_session_id` (`session_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='崔哥聊天会话表';

-- 对话消息表
CREATE TABLE IF NOT EXISTS `cuige_messages` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` varchar(64) NOT NULL COMMENT '会话ID',
    `role` enum('user','assistant','system') NOT NULL COMMENT '角色',
    `content` text NOT NULL COMMENT '消息内容',
    `audio_duration` float DEFAULT NULL COMMENT '语音时长（秒）',
    `tokens_used` int(11) DEFAULT 0 COMMENT '消耗Token数',
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_session_id` (`session_id`),
    KEY `idx_created_at` (`created_at`),
    CONSTRAINT `fk_cuige_messages_session` FOREIGN KEY (`session_id`) 
        REFERENCES `cuige_sessions` (`session_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='崔哥聊天消息表';

-- 用户偏好/记忆表（存储重要信息）
CREATE TABLE IF NOT EXISTS `cuige_memories` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` varchar(64) NOT NULL DEFAULT 'default' COMMENT '用户标识',
    `category` varchar(64) NOT NULL COMMENT '记忆类别(personal/preference/fact)',
    `key_info` varchar(255) NOT NULL COMMENT '关键信息',
    `value` text NOT NULL COMMENT '详细内容',
    `importance` tinyint(1) DEFAULT 5 COMMENT '重要性 1-10',
    `mention_count` int(11) DEFAULT 1 COMMENT '提及次数',
    `last_mentioned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_category` (`user_id`, `category`),
    KEY `idx_importance` (`importance` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='崔哥记忆库';

-- 插入一些初始记忆（崔哥了解的用户信息）
-- INSERT INTO `cuige_memories` (`user_id`, `category`, `key_info`, `value`, `importance`) VALUES
-- ('default', 'personal', '姓名', '老板', 8),
-- ('default', 'preference', '说话风格', '喜欢直接、幽默的回复', 7);
