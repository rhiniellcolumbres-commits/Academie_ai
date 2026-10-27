<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

header('Content-Type: application/json; charset=UTF-8');

// Sends a JSON response with the given HTTP status and ends the request.
function feedback_response(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    feedback_response(405, ['success' => false, 'message' => 'Method not allowed.']);
}

if (empty($_SESSION['user_id'])) {
    feedback_response(401, ['success' => false, 'message' => 'Please sign in again.']);
}

require_same_origin_for_write();
if (!csrf_valid($_POST['csrf_token'] ?? null)) {
    feedback_response(403, ['success' => false, 'message' => 'Your session expired. Refresh the page and try again.']);
}

$type = (string)($_POST['content_type'] ?? '');
$rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT);
$reason = trim((string)($_POST['reason'] ?? ''));
$allowedReasons = ['incorrect', 'unclear', 'too_difficult', 'other'];
$userId = (int)$_SESSION['user_id'];
$contentKey = '';
$contextLabel = '';

if (!in_array($type, ['roadmap', 'lesson'], true) || !in_array($rating, [1, -1], true)) {
    feedback_response(400, ['success' => false, 'message' => 'Choose a rating to submit feedback.']);
}

if ($rating === 1) {
    $reason = '';
} elseif (!in_array($reason, $allowedReasons, true)) {
    feedback_response(400, ['success' => false, 'message' => 'Choose a reason for your feedback.']);
}

if ($type === 'roadmap') {
    $pathId = filter_var($_POST['study_path_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$pathId || $pathId < 1) {
        feedback_response(400, ['success' => false, 'message' => 'Invalid roadmap.']);
    }
    $stmt = $pdo->prepare('SELECT id FROM study_paths WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$pathId, $userId]);
    if (!$stmt->fetchColumn()) {
        feedback_response(404, ['success' => false, 'message' => 'Roadmap not found.']);
    }
    $contentKey = (string)$pathId;
    $contextLabel = 'Learning roadmap';
} else {
    $topicId = filter_var($_POST['topic_id'] ?? null, FILTER_VALIDATE_INT);
    $activityIndex = filter_var($_POST['activity_index'] ?? null, FILTER_VALIDATE_INT);
    if (!$topicId || $topicId < 1 || $activityIndex === false || $activityIndex < 0) {
        feedback_response(400, ['success' => false, 'message' => 'Invalid lesson.']);
    }
    $stmt = $pdo->prepare(
        'SELECT st.id, st.title, st.activity
         FROM study_topics st
         INNER JOIN study_paths sp ON sp.id = st.study_path_id
         WHERE st.id = ? AND sp.user_id = ? LIMIT 1'
    );
    $stmt->execute([$topicId, $userId]);
    $topic = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$topic) {
        feedback_response(404, ['success' => false, 'message' => 'Lesson not found.']);
    }
    $activities = getActivities($topic['activity'] ?? '');
    if (!isset($activities[$activityIndex])) {
        feedback_response(404, ['success' => false, 'message' => 'Lesson not found.']);
    }
    $contentKey = $topicId . ':' . $activityIndex;
    $contextLabel = trim((string)$topic['title'] . ' — ' . (string)$activities[$activityIndex]);
}

$existingFeedbackStmt = $pdo->prepare(
    'SELECT 1
     FROM ai_content_feedback
     WHERE user_id = ? AND content_type = ? AND content_key = ?
     LIMIT 1'
);
$existingFeedbackStmt->execute([$userId, $type, $contentKey]);

if ($existingFeedbackStmt->fetchColumn()) {
    feedback_response(409, [
        'success' => false,
        'message' => 'You have already submitted feedback for this item.',
    ]);
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO ai_content_feedback (user_id, content_type, content_key, rating, reason, context_label)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $userId,
        $type,
        $contentKey,
        $rating,
        $reason !== '' ? $reason : null,
        substr($contextLabel, 0, 255),
    ]);
} catch (PDOException $exception) {
    // The unique key prevents the same user from rating the same content twice.
    if ((int)($exception->errorInfo[1] ?? 0) === 1062) {
        feedback_response(409, [
            'success' => false,
            'message' => 'You have already submitted feedback for this item.',
        ]);
    }

    throw $exception;
}

feedback_response(200, ['success' => true, 'message' => 'Thanks for your feedback.']);
