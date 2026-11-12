CREATE TABLE IF NOT EXISTS chat_memory_jobs (
    message_id INT NOT NULL PRIMARY KEY,
    status VARCHAR(16) NOT NULL DEFAULT 'pending',
    attempts INT NOT NULL DEFAULT 0,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    proposal LONGTEXT NULL,
    last_error VARCHAR(500) NULL,
    KEY memory_jobs_ready (status, available_at, message_id),
    FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
