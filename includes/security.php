<?php
/**
 * Shared security helpers for the local StudyPath AI PHP application.
 */
/** Start a hardened PHP session and expire inactive student sessions. */
function security_start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    $isHttps = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    // Idle timeout for student sessions; admin sessions are checked in admin/_auth.php.
    if (!empty($_SESSION['user_id'])) {
        if (isset($_SESSION['user_last_activity']) && time() - (int)$_SESSION['user_last_activity'] > 1800) {
            unset($_SESSION['user_id'], $_SESSION['user_last_activity'], $_SESSION['csrf_token']);
            session_regenerate_id(true);
        } elseif (!isset($_SESSION['user_last_activity'])) {
            $_SESSION['user_last_activity'] = time();
        } else {
            $_SESSION['user_last_activity'] = time();
        }
    }
}
/** Hide PHP errors from visitors and send browser security headers. */
function security_headers(): void {
    // Never display internal warnings, paths, SQL errors, or stack traces to visitors.
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    if (headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    // Do not add HSTS on localhost/HTTP development.
}
/** Create or return the random token used to protect forms from CSRF. */
function csrf_token(): string {
    security_start_session();
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
/** Compare a submitted CSRF token with the one stored in the current session. */
function csrf_valid($token): bool {
    security_start_session();
    return is_string($token) && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}
/** Allow a POST only when it includes the valid session CSRF token. */
function require_csrf_post(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid($_POST['csrf_token'] ?? null)) {
        http_response_code($_SERVER['REQUEST_METHOD'] === 'POST' ? 403 : 405);
        exit('Invalid request. Refresh the page and try again.');
    }
}
/** Reject browser write requests whose Origin header points to another site. */
function require_same_origin_for_write(): void {
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST','PUT','PATCH','DELETE'], true)) return;
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') return; // Non-browser clients may omit Origin; CSRF token is still required where applicable.
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $expected = $scheme . '://' . $host;
    if (!hash_equals(strtolower($expected), strtolower($origin))) {
        http_response_code(403);
        exit('Cross-origin request blocked.');
    }
}
/** Track attempts by scope and identity; return false once the time-window limit is reached. */
function rate_limit(PDO $pdo, string $scope, string $identity, int $maxAttempts, int $windowSeconds): bool {
    // Returns true when allowed. Uses the security_rate_limits table added to database.sql.
    $key = hash('sha256', $scope . '|' . $identity);
    $now = time();
    // Keep the local rate-limit table from growing indefinitely.
    if (random_int(1, 100) === 1) {
        $cleanup = $pdo->prepare('DELETE FROM security_rate_limits WHERE window_started < ?');
        $cleanup->execute([$now - 172800]);
    }
    $stmt = $pdo->prepare('SELECT attempts, window_started FROM security_rate_limits WHERE rate_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || ($now - (int)$row['window_started']) >= $windowSeconds) {
        $upsert = $pdo->prepare('INSERT INTO security_rate_limits (rate_key, attempts, window_started) VALUES (?, 1, ?) ON DUPLICATE KEY UPDATE attempts = 1, window_started = VALUES(window_started)');
        $upsert->execute([$key, $now]);
        return true;
    }
    if ((int)$row['attempts'] >= $maxAttempts) return false;
    $up = $pdo->prepare('UPDATE security_rate_limits SET attempts = attempts + 1 WHERE rate_key = ?');
    $up->execute([$key]);
    return true;
}
/** Return the direct client address used to separate rate-limit counters. */
function rate_limit_identity(): string {
    // Do not trust X-Forwarded-For on localhost; REMOTE_ADDR is the direct peer.
    return (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}
