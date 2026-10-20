<?php

/**
 * Database bootstrap included by pages and API handlers.
 * Loads configured credentials, creates the shared PDO connection, and stops with
 * a generic message if MySQL cannot be reached so credentials/errors stay private.
 */

// Database settings live in config/database.php.
// To use different credentials, create config/database.local.php (see the .example file).
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed. Please check your MySQL settings and make sure the studypath_ai database exists.');
}
