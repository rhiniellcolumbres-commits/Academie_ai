<?php

/**
 * API endpoint for loading one lesson in JSON format.
 * The browser sends a POST request containing topic_id and activity_index.
 * This file checks the signed-in student, verifies access to the roadmap,
 * reuses a complete saved lesson when possible, otherwise asks the AI helper,
 * saves successful Gemini lessons, and returns JSON to the browser.
 */

// Start or resume this visitor's PHP session so we can identify the signed-in student.
// The browser sends the session cookie automatically; the user ID comes from that session.
// CSRF note: this endpoint currently does not validate a CSRF token. A session proves who
// the student is, but POST plus a session is not itself a CSRF defense; add token checking
// here if this endpoint is changed to use the site's shared CSRF protection.
session_start();

// Load the shared database connection, common helpers, and AI-use logging function.
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/audit.php';

// Tell the browser that every response from this endpoint is JSON encoded as UTF-8.
header('Content-Type: application/json; charset=UTF-8');


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

// Require a logged-in session before this endpoint can return a student's lesson.
if (!isset($_SESSION['user_id'])) {

    // 401 means the request has no authenticated student session.
    http_response_code(401);

    // Return a predictable JSON error so the front end can display a message.
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required.'
    ]);

    // Stop here so unauthenticated requests cannot continue to the database or AI.
    exit;
}


// Cast the session value to an integer before using it as a database ID.
$userId = (int) $_SESSION['user_id'];

// Fetch the student's profile because it is used to personalize the generated lesson.
$user = getUserById(
    $pdo,
    $userId
);


// The session can outlive an account; return an error if the account no longer exists.
if (!$user) {

    // 404 means the session points to a user record that cannot be found.
    http_response_code(404);

    echo json_encode([
        'success' => false,
        'message' => 'User not found.'
    ]);

    // End the request after returning the error response.
    exit;
}


/*
|--------------------------------------------------------------------------
| Validate Request
|--------------------------------------------------------------------------
*/

// This endpoint accepts POST only, so lesson IDs are sent in the request body.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    // 405 tells the browser that this HTTP method is not supported here.
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed.'
    ]);

    // Do not read request data or generate a lesson for any other method.
    exit;
}


// Read the topic ID from POST data and reject values that are not whole numbers.
$topicId = filter_input(
    INPUT_POST,
    'topic_id',
    FILTER_VALIDATE_INT
);

// Read the zero-based activity position in the topic and validate it as an integer.
$activityIndex = filter_input(
    INPUT_POST,
    'activity_index',
    FILTER_VALIDATE_INT
);


// A topic ID must be a positive ID; the activity index may be zero (the first activity).
if (!$topicId || $activityIndex === false) {

    // 400 means the request did not contain valid activity identifiers.
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid activity.'
    ]);

    // Stop before using invalid input in a database query.
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Topic
|--------------------------------------------------------------------------
*/

// Prepare a parameterized query to find the requested topic owned by this student.
// The JOIN ensures the roadmap itself belongs to this user, not another student.
$stmt = $pdo->prepare(
    'SELECT st.*
     FROM study_topics st
     INNER JOIN study_paths sp ON sp.id = st.study_path_id
     WHERE st.id = ?
     AND sp.user_id = ?
     LIMIT 1'
);

// Bind the supplied topic ID and the session's user ID to the query placeholders.
$stmt->execute([
    $topicId,
    $userId
]);

// Fetch one matching topic as an associative array, or false if none is found.
$topic = $stmt->fetch(PDO::FETCH_ASSOC);


// Do not reveal or generate lessons for a topic that does not belong to this user.
if (!$topic) {

    // 404 avoids disclosing whether another student's topic ID exists.
    http_response_code(404);

    echo json_encode([
        'success' => false,
        'message' => 'Topic not found.'
    ]);

    // Stop because all later checks require a valid topic record.
    exit;
}

// Require the student to have opened the stage before viewing any lesson in it.
$stageNumber = (int)$topic['order_number'];
if ((int)($topic['is_read'] ?? 0) !== 1) {
    // 403 means the student is signed in but has not started this stage yet.
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Open this roadmap stage before viewing its lessons.'
    ]);
    // Stop so direct requests cannot bypass the stage's Start Stage action.
    exit;
}

// Every stage after the first stays locked until the immediately previous stage is complete.
if ($stageNumber > 1) {
    // Find the previous stage within the same roadmap.
    $previousStageStmt = $pdo->prepare(
        'SELECT completed
         FROM study_topics
         WHERE study_path_id = ? AND order_number = ?
         LIMIT 1'
    );
    // Use the roadmap ID and previous stage number, not values from the browser.
    $previousStageStmt->execute([(int)$topic['study_path_id'], $stageNumber - 1]);
    // Read the previous stage's completion flag.
    $previousStage = $previousStageStmt->fetch(PDO::FETCH_ASSOC);

    // Reject this request if the previous stage is missing or unfinished.
    if (!$previousStage || (int)$previousStage['completed'] !== 1) {
        // 403 means access is forbidden until the learning prerequisite is met.
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'This stage is locked. Complete the previous stage first.'
        ]);
        // Stop before exposing lesson content or using the AI service.
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Get Activity
|--------------------------------------------------------------------------
*/

// Convert the activity field from the database into a normalized PHP list.
$activities = getActivities(
    $topic['activity'] ?? ''
);


// Confirm the requested zero-based position points to a real activity.
if (!isset($activities[$activityIndex])) {

    // 400 means the topic exists, but the requested activity does not.
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Activity not found.'
    ]);

    // Stop before generating a lesson for an unknown activity.
    exit;
}


// Save the activity text because it is included in the prompt sent to the AI.
$activity = $activities[$activityIndex];


/*
|--------------------------------------------------------------------------
| Check Saved Lesson
|--------------------------------------------------------------------------
| Older versions stored short or generic lesson text. Only reuse saved
| lessons when all five sections contain enough topic-specific content.
*/

// ai_lessons stores generated lesson sections as JSON in the topic database row.
if (!empty($topic['ai_lessons'])) {
    // Decode the saved JSON string into PHP arrays; invalid JSON becomes null.
    $savedLessons = json_decode($topic['ai_lessons'], true);

    // Look for a previously generated lesson at this activity's index.
    if (is_array($savedLessons) && isset($savedLessons[$activityIndex])) {
        // The cached value should itself be an array containing each lesson section.
        $savedLesson = $savedLessons[$activityIndex];

        if (is_array($savedLesson)) {
            // Convert lesson text to trimmed strings so old or malformed values are safe to check.
            $savedLearn = trim((string)($savedLesson['lesson'] ?? ''));
            $savedExample = trim((string)($savedLesson['example'] ?? ''));
            $savedPractice = trim((string)($savedLesson['practice'] ?? ''));
            $savedChecklist = $savedLesson['checklist'] ?? [];
            $savedResources = $savedLesson['resources'] ?? [];

            // Require a substantial lesson so short legacy responses are regenerated.
            // Use mb_strlen when available because it counts multi-byte characters correctly.
            $cacheIsComplete = function_exists('mb_strlen')
                ? mb_strlen($savedLearn) >= 700
                : strlen($savedLearn) >= 700;
            // Also require useful example, practice, checklist, and resource content.
            $cacheIsComplete = $cacheIsComplete
                && strlen($savedExample) >= 80
                && strlen($savedPractice) >= 80
                && is_array($savedChecklist) && count($savedChecklist) >= 4
                && is_array($savedResources) && count($savedResources) >= 2;

            // If every section passes validation, return the cached lesson without calling the AI.
            if ($cacheIsComplete) {
                // success tells the front end the request worked; source identifies a cache hit.
                echo json_encode([
                    'success' => true,
                    'source' => 'cache',
                    'lesson' => $savedLearn,
                    'example' => $savedExample,
                    'practice' => $savedPractice,
                    'checklist' => array_values($savedChecklist),
                    'resources' => array_values($savedResources)
                ], JSON_UNESCAPED_UNICODE);
                // Stop so the same lesson is not generated or saved again.
                exit;
            }
        }
        // Ignore incomplete old cache entries and continue to fresh AI generation below.
    }
}


/*
|--------------------------------------------------------------------------
| Generate Lesson
|--------------------------------------------------------------------------
*/

// Generate and validate a detailed lesson using the student's profile and this activity.
$result = ai_activity_lesson(
    $user,
    $topic,
    $activity
);

// If the AI helper failed, record the failure and send a useful JSON error response.
if (empty($result['success'])) {
    // Record the failed AI call for administrators to monitor usage and outages.
    log_ai_usage($pdo, (int)$_SESSION['user_id'], 'ai_lesson', 'failed', substr((string)($topic['title'] ?? ''), 0, 200));
    // Read the HTTP status reported by the AI helper, if it supplied one.
    $httpCode = (int)($result['http_code'] ?? 0);
    // Preserve a valid upstream error status where useful; otherwise report Bad Gateway.
    if ($httpCode >= 400 && $httpCode <= 599) {
        http_response_code($httpCode === 429 ? 429 : 502);
    } else {
        http_response_code(502);
    }

    // Use the helper's explanation unless the AI quota limit needs a clearer message.
    $errorMessage = $result['message'] ?? 'The AI learning guide could not be generated. Please try again.';
    if ($httpCode === 429) {
        $errorMessage = 'The Gemini AI quota has been reached. Please try again after the quota resets.';
    }

    // Return only a safe message; do not expose API keys or server internals.
    echo json_encode([
        'success' => false,
        'message' => $errorMessage
    ], JSON_UNESCAPED_UNICODE);
    // Stop because there is no valid lesson to save or return.
    exit;
}


// Log successful AI or fallback generation so administrators can monitor its source.
log_ai_usage($pdo, (int)$_SESSION['user_id'], 'ai_lesson', (($result['source'] ?? '') === 'gemini') ? 'success' : 'fallback', substr((string)($topic['title'] ?? ''), 0, 200));

/*
|--------------------------------------------------------------------------
| Save Successful Lesson
|--------------------------------------------------------------------------
*/

// Save only successful Gemini responses. Fallback lessons are returned but not cached here.
if (
    !empty($result['success'])
    && isset($result['lesson'])
    && ($result['source'] ?? '') === 'gemini'
) {

    // Start an empty cache, or restore existing cached lessons for other activities.
    $savedLessons = [];

    if (!empty($topic['ai_lessons'])) {

        // Decode the saved JSON before adding or replacing this activity's lesson.
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

    // Store all lesson sections under this activity's index to reuse on later visits.
    $savedLessons[$activityIndex] = [
        'lesson' => $result['lesson'],
        'example' => $result['example'] ?? '',
        'practice' => $result['practice'] ?? '',
        'checklist' => $result['checklist'] ?? [],
        'resources' => $result['resources'] ?? []
    ];


    // Prepare an update that writes the complete set of cached lessons for this topic.
    $stmt = $pdo->prepare(
        'UPDATE study_topics
         SET ai_lessons = ?
         WHERE id = ?'
    );


    // Encode the PHP array as Unicode-safe JSON and save it to the database.
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

// Give quota exhaustion a clear status and message even if the helper returned a result object.
if (
    isset($result['http_code'])
    && (int) $result['http_code'] === 429
) {

    // 429 means the AI provider has temporarily limited requests.
    http_response_code(429);

    echo json_encode([
        'success' => false,
        'message' =>
            'AI learning guide is temporarily unavailable because the Gemini quota has been reached. Please try again after the quota resets.'
    ], JSON_UNESCAPED_UNICODE);

    // Stop rather than returning a success-shaped response for a quota error.
    exit;
}


/*
|--------------------------------------------------------------------------
| Return Result
|--------------------------------------------------------------------------
*/

// Return the generated lesson object; JSON_UNESCAPED_UNICODE preserves readable text.
echo json_encode(
    $result,
    JSON_UNESCAPED_UNICODE
);
