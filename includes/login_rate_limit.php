<?php
/**
 * Small database-backed login rate limiter for local XAMPP development.
 * Requires security_login_attempts table from security_migration.sql.
 */
function login_rate_limit_key(string $scope, string $identity): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return hash('sha256', $scope . '|' . strtolower(trim($identity)) . '|' . $ip);
}

function login_rate_limit_is_blocked(PDO $pdo, string $key): bool
{
    $stmt = $pdo->prepare(
        'SELECT attempts, window_started_at, blocked_until
         FROM security_login_attempts WHERE limiter_key = ? LIMIT 1'
    );
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return false;
    }

    $now = time();
    $blockedUntil = !empty($row['blocked_until']) ? strtotime($row['blocked_until']) : false;
    if ($blockedUntil && $blockedUntil > $now) {
        return true;
    }

    $windowStart = strtotime((string)$row['window_started_at']);
    if (!$windowStart || ($now - $windowStart) >= 900) {
        $reset = $pdo->prepare(
            'UPDATE security_login_attempts
             SET attempts = 0, window_started_at = NOW(), blocked_until = NULL
             WHERE limiter_key = ?'
        );
        $reset->execute([$key]);
        return false;
    }

    return (int)$row['attempts'] >= 5;
}

function login_rate_limit_failed(PDO $pdo, string $key): void
{
    $stmt = $pdo->prepare(
        'SELECT attempts, window_started_at FROM security_login_attempts
         WHERE limiter_key = ? LIMIT 1'
    );
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        try {
            $insert = $pdo->prepare(
                'INSERT INTO security_login_attempts
                    (limiter_key, attempts, window_started_at, blocked_until)
                 VALUES (?, 1, NOW(), NULL)'
            );
            $insert->execute([$key]);
        } catch (PDOException $e) {
            $update = $pdo->prepare(
                'UPDATE security_login_attempts SET attempts = attempts + 1
                 WHERE limiter_key = ?'
            );
            $update->execute([$key]);
        }
        return;
    }

    $windowStart = strtotime((string)$row['window_started_at']);
    if (!$windowStart || (time() - $windowStart) >= 900) {
        $update = $pdo->prepare(
            'UPDATE security_login_attempts
             SET attempts = 1, window_started_at = NOW(), blocked_until = NULL
             WHERE limiter_key = ?'
        );
        $update->execute([$key]);
        return;
    }

    $attempts = (int)$row['attempts'] + 1;
    $blockedUntil = $attempts >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;
    $update = $pdo->prepare(
        'UPDATE security_login_attempts
         SET attempts = ?, blocked_until = ?
         WHERE limiter_key = ?'
    );
    $update->execute([$attempts, $blockedUntil, $key]);
}

function login_rate_limit_clear(PDO $pdo, string $key): void
{
    $stmt = $pdo->prepare('DELETE FROM security_login_attempts WHERE limiter_key = ?');
    $stmt->execute([$key]);
}
