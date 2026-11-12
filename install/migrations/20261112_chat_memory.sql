CREATE TABLE IF NOT EXISTS chat_memory (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    model_id INT NOT NULL,
    source_message_id INT NOT NULL,
    supersedes_id INT DEFAULT NULL,
    superseded_at DATETIME DEFAULT NULL,
    content VARCHAR(500) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (source_message_id),
    UNIQUE KEY (supersedes_id),
    KEY (user_id, model_id, superseded_at, id),
    FOREIGN KEY (source_message_id) REFERENCES chat_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
