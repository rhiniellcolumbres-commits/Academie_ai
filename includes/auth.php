<?php

require_once __DIR__ . '/bootstrap.php';

if (empty($_SESSION['user_id'])) {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $baseDir = preg_replace('#/(admin|api|actions)$#', '', rtrim($scriptDir, '/'));
    $loginUrl = rtrim((string)$baseDir, '/') . '/login';
    header('Location: ' . $loginUrl);
    exit;
}
