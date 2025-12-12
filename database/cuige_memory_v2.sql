-- ============================================================
-- 崔哥智能记忆系统 V2
-- 模仿人类大脑的记忆机制
-- ============================================================

-- 1. 短期记忆（工作记忆）- 当前对话的完整记录
-- 保留最近的所有对话，超出限制后触发压缩
DROP TABLE IF EXISTS cuige_short_memory;
CREATE TABLE cuige_short_memory (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(64) NOT NULL DEFAULT 'default',
    session_id VARCHAR(64) NOT NULL,
    role ENUM('user', 'assistant') NOT NULL,
    content TEXT NOT NULL,
    tokens INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session (session_id),
    INDEX idx_user_time (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. 长期记忆（语义记忆）- 压缩后的重要信息
-- 由 AI 自动提取和更新
DROP TABLE IF EXISTS cuige_long_memory;
CREATE TABLE cuige_long_memory (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(64) NOT NULL DEFAULT 'default',
    category VARCHAR(32) NOT NULL COMMENT '类别: identity/preference/event/relationship/knowledge',
    subject VARCHAR(128) NOT NULL COMMENT '主题，如：姓名、工作、喜好',
    content TEXT NOT NULL COMMENT '具体内容',
    confidence TINYINT DEFAULT 80 COMMENT '置信度 0-100',
    importance TINYINT DEFAULT 5 COMMENT '重要性 1-10',
    mention_count INT DEFAULT 1 COMMENT '被提及次数',
    source_summary TEXT COMMENT '来源摘要（哪次对话提取的）',
    first_mentioned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_mentioned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_verified_at TIMESTAMP NULL COMMENT '最后验证时间',
    is_active TINYINT(1) DEFAULT 1 COMMENT '是否有效（用户可能更正信息）',
    INDEX idx_user_category (user_id, category),
    INDEX idx_user_importance (user_id, importance DESC),
    INDEX idx_active (is_active),
    UNIQUE KEY uk_user_subject (user_id, subject)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. 情景记忆（事件记忆）- 重要事件和对话摘要
DROP TABLE IF EXISTS cuige_episode_memory;
CREATE TABLE cuige_episode_memory (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(64) NOT NULL DEFAULT 'default',
    session_id VARCHAR(64) NOT NULL,
    episode_type VARCHAR(32) NOT NULL COMMENT '类型: conversation/event/milestone',
    title VARCHAR(255) NOT NULL COMMENT '事件标题',
    summary TEXT NOT NULL COMMENT 'AI 生成的摘要',
    key_points JSON COMMENT '关键点列表',
    emotional_tone VARCHAR(32) COMMENT '情感基调: positive/negative/neutral',
    message_count INT DEFAULT 0 COMMENT '原始消息数',
    start_time TIMESTAMP NOT NULL,
    end_time TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_time (user_id, start_time DESC),
    INDEX idx_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. 记忆处理队列 - 后台 AI 任务
DROP TABLE IF EXISTS cuige_memory_queue;
CREATE TABLE cuige_memory_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(64) NOT NULL,
    task_type VARCHAR(32) NOT NULL COMMENT '任务类型: compress/extract/verify/merge',
    payload JSON NOT NULL COMMENT '任务数据',
    priority TINYINT DEFAULT 5 COMMENT '优先级 1-10',
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    result JSON COMMENT '处理结果',
    retry_count TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    INDEX idx_status_priority (status, priority DESC),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. 用户画像（AI 持续更新）
DROP TABLE IF EXISTS cuige_user_profile;
CREATE TABLE cuige_user_profile (
    user_id VARCHAR(64) PRIMARY KEY,
    nickname VARCHAR(64) COMMENT '用户昵称',
    personality_summary TEXT COMMENT 'AI 分析的性格特点',
    interests JSON COMMENT '兴趣爱好列表',
    communication_style VARCHAR(64) COMMENT '沟通风格: formal/casual/humorous',
    topics_of_interest JSON COMMENT '感兴趣的话题',
    interaction_stats JSON COMMENT '互动统计',
    last_interaction_at TIMESTAMP NULL,
    profile_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 初始化配置
INSERT INTO cuige_user_profile (user_id, nickname, communication_style) 
VALUES ('default', '朋友', 'casual')
ON DUPLICATE KEY UPDATE user_id = user_id;

-- 迁移旧数据（如果存在）
-- INSERT IGNORE INTO cuige_short_memory (user_id, session_id, role, content, created_at)
-- SELECT 'default', session_id, role, content, created_at FROM cuige_messages;
