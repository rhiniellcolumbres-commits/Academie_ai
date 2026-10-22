<?php
// Records a student login or logout event.
function log_user_activity(PDO $pdo, int $userId, string $event): void
{
    if (!in_array($event, ['login', 'logout'], true)) {
        return;
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO user_activity_logs (user_id, event_type, ip_address, user_agent)
             VALUES (?, ?, ?, ?)'
        );
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $stmt->execute([$userId, $event, $ip, $agent ?: null]);
    } catch (Throwable $e) {
        
    }
}

// Records the result of an AI request.
function log_ai_usage(PDO $pdo, int $userId, string $type, string $status = 'requested', ?string $details = null): void
{
    if (!in_array($type, ['assessment_roadmap', 'ai_lesson'], true)) {
        return;
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO ai_usage_logs (user_id, usage_type, status, details) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $type, substr($status, 0, 30), $details ? substr($details, 0, 255) : null]);
    } catch (Throwable $e) {
        
    }
}
