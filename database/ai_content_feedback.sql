CREATE TABLE IF NOT EXISTS ai_content_feedback (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    content_type ENUM('roadmap', 'lesson') NOT NULL,
    content_key VARCHAR(80) NOT NULL,
    rating TINYINT NOT NULL,
    reason VARCHAR(32) NULL,
    context_label VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ai_feedback_item (user_id, content_type, content_key),
    KEY idx_ai_feedback_created (created_at),
    KEY idx_ai_feedback_type_rating (content_type, rating),
    KEY idx_ai_feedback_user (user_id),
    CONSTRAINT fk_ai_content_feedback_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
