-- 崔哥智能记忆系统 V3 - 大厂级分层记忆架构

-- 1. 对话摘要表（情景记忆）
CREATE TABLE IF NOT EXISTS cuige_conversation_summaries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL,
    user_id VARCHAR(64) NOT NULL DEFAULT 'default',
    sequence_num INT NOT NULL DEFAULT 0 COMMENT '摘要序号',
    
    summary TEXT NOT NULL COMMENT '对话摘要',
    key_points JSON COMMENT '关键点列表',
    user_intent VARCHAR(255) COMMENT '用户意图',
    emotional_tone VARCHAR(32) DEFAULT 'neutral' COMMENT '情绪基调',
    action_items JSON COMMENT '待办事项',
    
    importance_score TINYINT DEFAULT 5 COMMENT '重要性 1-10',
    message_count INT DEFAULT 0 COMMENT '包含的消息数',
    start_time TIMESTAMP NULL COMMENT '对话开始时间',
    end_time TIMESTAMP NULL COMMENT '对话结束时间',
    
    is_archived TINYINT(1) DEFAULT 0 COMMENT '是否已归档',
    is_super_summary TINYINT(1) DEFAULT 0 COMMENT '是否为超级摘要',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    KEY idx_session (session_id),
    KEY idx_user (user_id),
    KEY idx_archived (is_archived),
    KEY idx_importance (importance_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='对话摘要';

-- 2. 安全添加列的存储过程
DELIMITER //

DROP PROCEDURE IF EXISTS add_column_if_not_exists//
CREATE PROCEDURE add_column_if_not_exists(
    IN table_name VARCHAR(64),
    IN column_name VARCHAR(64),
    IN column_definition VARCHAR(255)
)
BEGIN
    SET @table_name = table_name;
    SET @column_name = column_name;
    SET @column_definition = column_definition;
    
    SELECT COUNT(*) INTO @exists 
    FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
    AND table_name = @table_name 
    AND column_name = @column_name;
    
    IF @exists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE ', @table_name, ' ADD COLUMN ', @column_name, ' ', @column_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END//

DELIMITER ;

-- 3. 添加列
CALL add_column_if_not_exists('cuige_short_memory', 'is_summarized', 'TINYINT(1) DEFAULT 0 COMMENT "是否已摘要"');
CALL add_column_if_not_exists('cuige_long_memory', 'confidence', 'TINYINT DEFAULT 75 COMMENT "置信度"');
CALL add_column_if_not_exists('cuige_user_profile', 'personality_summary', 'VARCHAR(100) COMMENT "性格总结"');
CALL add_column_if_not_exists('cuige_user_profile', 'key_topics', 'VARCHAR(255) COMMENT "常见话题"');
CALL add_column_if_not_exists('cuige_user_profile', 'emotional_baseline', 'VARCHAR(32) DEFAULT "neutral" COMMENT "情绪基线"');
CALL add_column_if_not_exists('cuige_memory_tasks', 'priority', 'TINYINT DEFAULT 5 COMMENT "优先级"');

-- 4. 清理
DROP PROCEDURE IF EXISTS add_column_if_not_exists;
