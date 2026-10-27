-- Run once on databases where ai_content_feedback was created before the user foreign key was added.
ALTER TABLE ai_content_feedback
    MODIFY user_id INT UNSIGNED NOT NULL,
    ADD CONSTRAINT fk_ai_content_feedback_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
