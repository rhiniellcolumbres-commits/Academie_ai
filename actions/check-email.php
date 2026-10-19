<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=UTF-8');

// Only accept requests from the registration form with its session token.
if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    http_response_code($_SERVER['REQUEST_METHOD'] === 'POST' ? 403 : 405);
    echo json_encode(['success' => false, 'message' => 'Invalid request. Refresh the page and try again.']);
    exit;
}

// Validate and normalize the submitted address before checking the unique email field.
$email = strtolower(trim((string)($_POST['email'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Enter a valid email address.']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);

    echo json_encode([
        'success' => true,
        'available' => !$stmt->fetchColumn(),
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Email availability could not be checked.']);
}
