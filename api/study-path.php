<?php

session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');


/*
|--------------------------------------------------------------------------
| Handle Server Errors
|--------------------------------------------------------------------------
*/

set_exception_handler(function (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Server error while processing the request.'
    ]);

    exit;
});


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

$userId = (int)$_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| GET
|--------------------------------------------------------------------------
| Get the logged-in user's study topics.
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $topics = getStudyTopics(
        $pdo,
        $userId
    );

    echo json_encode([
        'success' => true,
        'data' => $topics
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| PUT
|--------------------------------------------------------------------------
| Update an existing study topic.
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {

    /*
     * Read the request body.
     */
    $rawInput = file_get_contents('php://input');

    $data = [];

    /*
     * Support JSON requests.
     */
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (stripos($contentType, 'application/json') !== false) {

        $decoded = json_decode(
            $rawInput,
            true
        );

        if (is_array($decoded)) {
            $data = $decoded;
        }

    } else {

        /*
         * Support form-urlencoded requests.
         */
        parse_str(
            $rawInput,
            $data
        );
    }


    /*
     * Get submitted values.
     */
    $topicId = isset($data['topic_id'])
        ? (int)$data['topic_id']
        : 0;

    $title = trim(
        $data['title'] ?? ''
    );

    $description = trim(
        $data['description'] ?? ''
    );


    /*
     * Validate topic ID.
     */
    if ($topicId <= 0) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid topic ID.'
        ]);

        exit;
    }


    /*
     * Validate title.
     */
    if ($title === '') {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Title is required.'
        ]);

        exit;
    }


    /*
     * Make sure the topic belongs
     * to the logged-in user.
     */
    $check = $pdo->prepare(
        'SELECT st.id
         FROM study_topics st
         INNER JOIN study_paths sp
             ON st.study_path_id = sp.id
         WHERE st.id = ?
         AND sp.user_id = ?
         LIMIT 1'
    );

    $check->execute([
        $topicId,
        $userId
    ]);

    if (!$check->fetch(PDO::FETCH_ASSOC)) {

        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'Topic not found.'
        ]);

        exit;
    }


    /*
     * Update the topic.
     */
    $update = $pdo->prepare(
        'UPDATE study_topics
         SET title = ?,
             description = ?
         WHERE id = ?'
    );

    $update->execute([
        $title,
        $description,
        $topicId
    ]);


    echo json_encode([
        'success' => true,
        'message' => 'Study topic updated successfully.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
| Delete an existing study topic.
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

    /*
     * Read the request body.
     */
    $rawInput = file_get_contents('php://input');

    $data = [];

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (stripos($contentType, 'application/json') !== false) {

        $decoded = json_decode(
            $rawInput,
            true
        );

        if (is_array($decoded)) {
            $data = $decoded;
        }

    } else {

        parse_str(
            $rawInput,
            $data
        );
    }


    /*
     * Get topic ID.
     */
    $topicId = isset($data['topic_id'])
        ? (int)$data['topic_id']
        : 0;


    /*
     * Validate topic ID.
     */
    if ($topicId <= 0) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid topic ID.'
        ]);

        exit;
    }


    /*
     * Make sure the topic belongs
     * to the logged-in user.
     */
    $check = $pdo->prepare(
        'SELECT st.id
         FROM study_topics st
         INNER JOIN study_paths sp
             ON st.study_path_id = sp.id
         WHERE st.id = ?
         AND sp.user_id = ?
         LIMIT 1'
    );

    $check->execute([
        $topicId,
        $userId
    ]);


    if (!$check->fetch(PDO::FETCH_ASSOC)) {

        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'Topic not found.'
        ]);

        exit;
    }


    /*
     * Delete the topic.
     */
    $delete = $pdo->prepare(
        'DELETE FROM study_topics
         WHERE id = ?'
    );

    $delete->execute([
        $topicId
    ]);


    echo json_encode([
        'success' => true,
        'message' => 'Study topic deleted successfully.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Only POST is handled below this point.
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| POST Data
|--------------------------------------------------------------------------
*/

$action = $_POST['action'] ?? '';

$topicId = filter_input(
    INPUT_POST,
    'topic_id',
    FILTER_VALIDATE_INT
);


/*
|--------------------------------------------------------------------------
| Validate Topic ID
|--------------------------------------------------------------------------
*/

if (!$topicId) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid topic.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Get Topic
|--------------------------------------------------------------------------
| Only allow access to topics belonging
| to the logged-in user's study path.
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    'SELECT st.*
     FROM study_topics st
     INNER JOIN study_paths sp
         ON st.study_path_id = sp.id
     WHERE st.id = ?
     AND sp.user_id = ?
     LIMIT 1'
);

$stmt->execute([
    $topicId,
    $userId
]);

$topic = $stmt->fetch(
    PDO::FETCH_ASSOC
);


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
| Determine Previous Stage
|--------------------------------------------------------------------------
*/

$stageNumber = (int)$topic['order_number'];

$previousCompleted = true;


if ($stageNumber > 1) {

    $previousStmt = $pdo->prepare(
        'SELECT st.completed
         FROM study_topics st
         INNER JOIN study_paths sp
             ON sp.id = st.study_path_id
         WHERE sp.user_id = ?
         AND st.study_path_id = ?
         AND st.order_number = ?
         LIMIT 1'
    );

    $previousStmt->execute([
        $userId,
        $topic['study_path_id'],
        $stageNumber - 1
    ]);

    $previousStage = $previousStmt->fetch(
        PDO::FETCH_ASSOC
    );


    if (!$previousStage) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Previous stage not found.'
        ]);

        exit;
    }


    $previousCompleted =
        (int)$previousStage['completed'] === 1;
}


/*
|--------------------------------------------------------------------------
| Prevent Access to Locked Stages
|--------------------------------------------------------------------------
*/

if (!$previousCompleted) {

    http_response_code(403);

    echo json_encode([
        'success' => false,
        'message' =>
            'This stage is locked. Complete the previous stage first.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| ACTION: READ
|--------------------------------------------------------------------------
| Mark the stage as read/opened.
|--------------------------------------------------------------------------
*/

if ($action === 'read') {

    /*
     * Stage was already opened.
     */
    if ((int)$topic['is_read'] === 1) {

        echo json_encode([
            'success' => true,
            'message' => 'Stage already opened.'
        ]);

        exit;
    }


    /*
     * Mark stage as read.
     */
    $update = $pdo->prepare(
        'UPDATE study_topics
         SET is_read = 1
         WHERE id = ?
         AND study_path_id = ?'
    );

    $update->execute([
        $topicId,
        $topic['study_path_id']
    ]);


    /*
     * Return JSON when requested by JavaScript.
     */
    if (
        !isset($_SERVER['HTTP_ACCEPT']) ||
        strpos(
            $_SERVER['HTTP_ACCEPT'],
            'application/json'
        ) === false
    ) {

        header(
            'Location: ../study-path.php?stage='
            . $stageNumber
        );

        exit;
    }


    echo json_encode([
        'success' => true,
        'message' => 'Stage opened successfully.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| ACTION: COMPLETE ACTIVITY
|--------------------------------------------------------------------------
| Mark one activity as completed.
| This does not toggle it back to incomplete.
|--------------------------------------------------------------------------
*/

if ($action === 'complete_activity') {

    /*
     * Student must read the stage first.
     */
    if ((int)$topic['is_read'] !== 1) {

        http_response_code(403);

        echo json_encode([
            'success' => false,
            'message' =>
                'Read the stage before completing activities.'
        ]);

        exit;
    }


    /*
     * Get the available activities.
     */
    $activities = getActivities(
        $topic['activity'] ?? ''
    );


    if (count($activities) === 0) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' =>
                'This stage has no activities.'
        ]);

        exit;
    }


    /*
     * Get activity index.
     */
    $activityIndex = filter_input(
        INPUT_POST,
        'activity_index',
        FILTER_VALIDATE_INT
    );


    /*
     * Check that the activity exists.
     */
    if (
        $activityIndex === false ||
        $activityIndex === null ||
        $activityIndex < 0 ||
        $activityIndex >= count($activities)
    ) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid activity.'
        ]);

        exit;
    }


    /*
     * Get current progress.
     */
    $progress = [];

    if (!empty($topic['activity_progress'])) {

        $decoded = json_decode(
            $topic['activity_progress'],
            true
        );

        if (is_array($decoded)) {
            $progress = $decoded;
        }
    }


    /*
     * Create progress values
     * for all activities.
     */
    $activityCount = count($activities);

    for ($i = 0; $i < $activityCount; $i++) {

        $progress[$i] =
            !empty($progress[$i])
                ? 1
                : 0;
    }


    /*
     * Mark this activity as completed.
     */
    $progress[$activityIndex] = 1;


    /*
     * Check whether all activities
     * are now completed.
     */
    $allCompleted = true;

    for ($i = 0; $i < $activityCount; $i++) {

        if ((int)$progress[$i] !== 1) {

            $allCompleted = false;

            break;
        }
    }


    /*
     * Complete the stage when
     * all activities are finished.
     */
    if ($allCompleted) {

        $update = $pdo->prepare(
            'UPDATE study_topics
             SET activity_progress = ?,
                 completed = 1,
                 completed_at = NOW()
             WHERE id = ?
             AND study_path_id = ?'
        );

        $update->execute([
            json_encode(
                $progress,
                JSON_UNESCAPED_UNICODE
            ),
            $topicId,
            $topic['study_path_id']
        ]);


        echo json_encode([
            'success' => true,
            'completed' => true,
            'activity_progress' => $progress,
            'message' =>
                'Stage completed! The next stage is now unlocked.'
        ]);

        exit;
    }


    /*
     * Save activity progress.
     */
    $update = $pdo->prepare(
        'UPDATE study_topics
         SET activity_progress = ?
         WHERE id = ?
         AND study_path_id = ?'
    );

    $update->execute([
        json_encode(
            $progress,
            JSON_UNESCAPED_UNICODE
        ),
        $topicId,
        $topic['study_path_id']
    ]);


    echo json_encode([
        'success' => true,
        'completed' => false,
        'activity_progress' => $progress,
        'message' => 'Activity marked as learned.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Unknown Action
|--------------------------------------------------------------------------
*/

http_response_code(400);

echo json_encode([
    'success' => false,
    'message' => 'Unknown action.'
]);

exit;