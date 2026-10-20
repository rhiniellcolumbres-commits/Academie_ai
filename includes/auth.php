<?php

/**
 * Student-page authentication guard.
 * Starts a PHP session when needed and redirects visitors without a student user_id
 * to the login page. Include this before output on pages that require student sign-in.
 */

// A page may already have started the session; only start one if it is not active.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// The login handler stores user_id in the session after successful authentication.
if (empty($_SESSION['user_id'])) {
    // Resolve the application root so redirects also work from nested routes.
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $baseDir = preg_replace('#/(admin|api|actions)$#', '', rtrim($scriptDir, '/'));
    $loginUrl = rtrim((string)$baseDir, '/') . '/login';
    // Redirect unauthenticated visitors and stop the protected page from running.
    header('Location: ' . $loginUrl);
    exit;
}
