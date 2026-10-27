<?php

// Starts a secure session and checks student inactivity.
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
    if (!empty($_SESSION['user_id'])) {
        if (isset($_SESSION['user_last_activity']) && time() - (int)$_SESSION['user_last_activity'] > 1800) {
            unset($_SESSION['user_id'], $_SESSION['user_last_activity'], $_SESSION['csrf_token']);
            session_regenerate_id(true);
            return;
        }
        $_SESSION['user_last_activity'] = time();
    }
}

// Sets browser security headers and hides PHP errors.
function security_headers(): void {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    if (headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cross-Origin-Opener-Policy: same-origin');
}

// Returns a session token for protecting form requests.
function csrf_token(): string {
    security_start_session();
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Checks whether a submitted form token matches the session.
function csrf_valid($token): bool {
    security_start_session();
    return is_string($token) && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

// Blocks write requests from another website.
function require_same_origin_for_write(): void {
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST','PUT','PATCH','DELETE'], true)) return;
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') return; 
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $expected = $scheme . '://' . $host;
    if (!hash_equals(strtolower($expected), strtolower($origin))) {
        http_response_code(403);
        exit('Cross-origin request blocked.');
    }
}

