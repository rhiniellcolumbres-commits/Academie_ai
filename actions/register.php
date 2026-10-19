<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

/** Store a registration error for the next page load and redirect to the form. */
function register_error(string $message): void {
    $_SESSION['auth_error'] = $message;
    header('Location: ../register');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../register');
    exit;
}
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    register_error('Your session expired. Please submit the form again.');
}
$firstName = trim((string)($_POST['first_name'] ?? ''));
$lastName = trim((string)($_POST['last_name'] ?? ''));
$email = strtolower(trim((string)($_POST['email'] ?? '')));
$password = (string)($_POST['password'] ?? '');
$confirmPassword = (string)($_POST['confirm_password'] ?? '');
if ($firstName === '' || strlen($firstName) > 200 || $lastName === '' || strlen($lastName) > 200) {
    register_error('Enter a valid first name and last name.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
    register_error('Enter a valid email address.');
}
if (!preg_match('/^(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,}$/', $password)) {
    register_error('Your password must be at least 8 characters and include an uppercase letter, a number, and a special character.');
}
if ($password !== $confirmPassword) {
    register_error('The passwords do not match.');
}
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    register_error('An account with that email already exists. Please log in instead.');
}
$passwordHash = password_hash($password, PASSWORD_DEFAULT);
try {
    $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password) VALUES (?, ?, ?, ?)");
    $stmt->execute([$firstName, $lastName, $email, $passwordHash]);
} catch (PDOException $e) {
    // Avoid exposing database details to visitors. Handle duplicate-email races safely.
    register_error('We could not create your account. Check the details and try again.');
}
session_regenerate_id(true);
$_SESSION['user_id'] = (int)$pdo->lastInsertId();
unset($_SESSION['csrf_token'], $_SESSION['auth_error']);
header('Location: ../dashboard');
exit;
