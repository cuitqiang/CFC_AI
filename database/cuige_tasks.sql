CREATE TABLE IF NOT EXISTS cuige_memory_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100),
    user_id VARCHAR(100),
    task_type ENUM('extract', 'compress', 'analyze') DEFAULT 'extract',
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    result TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_status (status),
    KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
