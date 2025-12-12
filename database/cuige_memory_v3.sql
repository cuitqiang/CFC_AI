-- 崔哥智能记忆系统 V3 - 大厂级分层记忆架构
-- 参考 Claude/ChatGPT 的记忆压缩机制

-- 1. 对话摘要表（情景记忆）
CREATE TABLE IF NOT EXISTS cuige_conversation_summaries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL,
    user_id VARCHAR(64) NOT NULL DEFAULT 'default',
    sequence_num INT NOT NULL DEFAULT 0 COMMENT '摘要序号',
    
    -- 摘要内容
    summary TEXT NOT NULL COMMENT '对话摘要',
    key_points JSON COMMENT '关键点列表',
    user_intent VARCHAR(255) COMMENT '用户意图',
    emotional_tone VARCHAR(32) DEFAULT 'neutral' COMMENT '情绪基调',
    action_items JSON COMMENT '待办事项/承诺',
    
    -- 元数据
    importance_score TINYINT DEFAULT 5 COMMENT '重要性 1-10',
    message_count INT DEFAULT 0 COMMENT '包含的消息数',
    start_time TIMESTAMP NULL COMMENT '对话开始时间',
    end_time TIMESTAMP NULL COMMENT '对话结束时间',
    
    -- 状态
    is_archived TINYINT(1) DEFAULT 0 COMMENT '是否已归档',
    is_super_summary TINYINT(1) DEFAULT 0 COMMENT '是否为超级摘要',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    KEY idx_session (session_id),
    KEY idx_user (user_id),
    KEY idx_archived (is_archived),
    KEY idx_importance (importance_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='对话摘要（情景记忆）';

-- 2. 更新短期记忆表，添加压缩标记
ALTER TABLE cuige_short_memory 
    ADD COLUMN IF NOT EXISTS is_summarized TINYINT(1) DEFAULT 0 COMMENT '是否已被摘要';

-- 3. 更新长期记忆表，添加置信度
ALTER TABLE cuige_long_memory 
    MODIFY COLUMN importance TINYINT DEFAULT 5 COMMENT '重要性 1-10',
    ADD COLUMN IF NOT EXISTS confidence TINYINT DEFAULT 75 COMMENT '置信度 0-100';

-- 4. 更新用户画像表
ALTER TABLE cuige_user_profile 
    ADD COLUMN IF NOT EXISTS personality_summary VARCHAR(100) COMMENT '性格总结',
    ADD COLUMN IF NOT EXISTS key_topics VARCHAR(255) COMMENT '常见话题',
    ADD COLUMN IF NOT EXISTS emotional_baseline VARCHAR(32) DEFAULT 'neutral' COMMENT '情绪基线';

-- 5. 创建索引优化查询
CREATE INDEX IF NOT EXISTS idx_short_summarized ON cuige_short_memory(is_summarized);
CREATE INDEX IF NOT EXISTS idx_long_confidence ON cuige_long_memory(confidence);

-- 6. 创建任务优先级索引
ALTER TABLE cuige_memory_tasks 
    ADD COLUMN IF NOT EXISTS priority TINYINT DEFAULT 5 COMMENT '优先级 1-10';
CREATE INDEX IF NOT EXISTS idx_task_priority ON cuige_memory_tasks(priority DESC, created_at);
