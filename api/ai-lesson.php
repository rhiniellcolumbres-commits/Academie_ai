<?php

session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/audit.php';

header('Content-Type: application/json; charset=UTF-8');


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {

    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Authentication required.'
    ]);

    exit;
}


$userId = (int) $_SESSION['user_id'];

$user = getUserById(
    $pdo,
    $userId
);


if (!$user) {

    http_response_code(404);

    echo json_encode([
        'success' => false,
        'message' => 'User not found.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Validate Request
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed.'
    ]);

    exit;
}


$topicId = filter_input(
    INPUT_POST,
    'topic_id',
    FILTER_VALIDATE_INT
);

$activityIndex = filter_input(
    INPUT_POST,
    'activity_index',
    FILTER_VALIDATE_INT
);


if (!$topicId || $activityIndex === false) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid activity.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Get Topic
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    'SELECT st.*
     FROM study_topics st
     INNER JOIN study_paths sp ON sp.id = st.study_path_id
     WHERE st.id = ?
     AND sp.user_id = ?
     LIMIT 1'
);

$stmt->execute([
    $topicId,
    $userId
]);

$topic = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$topic) {

    http_response_code(404);

    echo json_encode([
        'success' => false,
        'message' => 'Topic not found.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Get Activity
|--------------------------------------------------------------------------
*/

$activities = getActivities(
    $topic['activity'] ?? ''
);


if (!isset($activities[$activityIndex])) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Activity not found.'
    ]);

    exit;
}


$activity = $activities[$activityIndex];


/*
|--------------------------------------------------------------------------
| Check Saved Lesson
|--------------------------------------------------------------------------
| Older versions stored short or generic lesson text. Only reuse saved
| lessons when all five sections contain enough topic-specific content.
*/

if (!empty($topic['ai_lessons'])) {
    $savedLessons = json_decode($topic['ai_lessons'], true);

    if (is_array($savedLessons) && isset($savedLessons[$activityIndex])) {
        $savedLesson = $savedLessons[$activityIndex];

        if (is_array($savedLesson)) {
            $savedLearn = trim((string)($savedLesson['lesson'] ?? ''));
            $savedExample = trim((string)($savedLesson['example'] ?? ''));
            $savedPractice = trim((string)($savedLesson['practice'] ?? ''));
            $savedChecklist = $savedLesson['checklist'] ?? [];
            $savedResources = $savedLesson['resources'] ?? [];

            $cacheIsComplete = function_exists('mb_strlen')
                ? mb_strlen($savedLearn) >= 700
                : strlen($savedLearn) >= 700;
            $cacheIsComplete = $cacheIsComplete
                && strlen($savedExample) >= 80
                && strlen($savedPractice) >= 80
                && is_array($savedChecklist) && count($savedChecklist) >= 4
                && is_array($savedResources) && count($savedResources) >= 2;

            if ($cacheIsComplete) {
                echo json_encode([
                    'success' => true,
                    'source' => 'cache',
                    'lesson' => $savedLearn,
                    'example' => $savedExample,
                    'practice' => $savedPractice,
                    'checklist' => array_values($savedChecklist),
                    'resources' => array_values($savedResources)
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
        // Incomplete legacy lessons are deliberately ignored and regenerated.
    }
}


/*
|--------------------------------------------------------------------------
| Generate Lesson
|--------------------------------------------------------------------------
*/

$result = ai_activity_lesson(
    $user,
    $topic,
    $activity
);

if (empty($result['success'])) {
    log_ai_usage($pdo, (int)$_SESSION['user_id'], 'ai_lesson', 'failed', substr((string)($topic['title'] ?? ''), 0, 200));
    $httpCode = (int)($result['http_code'] ?? 0);
    if ($httpCode >= 400 && $httpCode <= 599) {
        http_response_code($httpCode === 429 ? 429 : 502);
    } else {
        http_response_code(502);
    }

    $errorMessage = $result['message'] ?? 'The AI learning guide could not be generated. Please try again.';
    if ($httpCode === 429) {
        $errorMessage = 'The Gemini AI quota has been reached. Please try again after the quota resets.';
    }

    echo json_encode([
        'success' => false,
        'message' => $errorMessage
    ], JSON_UNESCAPED_UNICODE);
    exit;
}


log_ai_usage($pdo, (int)$_SESSION['user_id'], 'ai_lesson', (($result['source'] ?? '') === 'gemini') ? 'success' : 'fallback', substr((string)($topic['title'] ?? ''), 0, 200));

/*
|--------------------------------------------------------------------------
| Save Successful Lesson
|--------------------------------------------------------------------------
*/

if (
    !empty($result['success'])
    && isset($result['lesson'])
    && ($result['source'] ?? '') === 'gemini'
) {

    $savedLessons = [];

    if (!empty($topic['ai_lessons'])) {

        $decoded = json_decode(
            $topic['ai_lessons'],
            true
        );

        if (is_array($decoded)) {
            $savedLessons = $decoded;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Save Complete Lesson
    |--------------------------------------------------------------------------
    */

    $savedLessons[$activityIndex] = [
        'lesson' => $result['lesson'],
        'example' => $result['example'] ?? '',
        'practice' => $result['practice'] ?? '',
        'checklist' => $result['checklist'] ?? [],
        'resources' => $result['resources'] ?? []
    ];


    $stmt = $pdo->prepare(
        'UPDATE study_topics
         SET ai_lessons = ?
         WHERE id = ?'
    );


    $stmt->execute([
        json_encode(
            $savedLessons,
            JSON_UNESCAPED_UNICODE
        ),
        $topicId
    ]);
}


/*
|--------------------------------------------------------------------------
| Gemini Quota Error
|--------------------------------------------------------------------------
*/

if (
    isset($result['http_code'])
    && (int) $result['http_code'] === 429
) {

    http_response_code(429);

    echo json_encode([
        'success' => false,
        'message' =>
            'AI learning guide is temporarily unavailable because the Gemini quota has been reached. Please try again after the quota resets.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


/*
|--------------------------------------------------------------------------
| Return Result
|--------------------------------------------------------------------------
*/

echo json_encode(
    $result,
    JSON_UNESCAPED_UNICODE
);