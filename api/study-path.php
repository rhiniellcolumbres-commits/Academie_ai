<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

set_exception_handler(function (Throwable $e) {

http_response_code(500);

echo json_encode([
        'success' => false,
        'message' => 'Server error while processing the request.'
    ]);

exit;
});

if (!isset($_SESSION['user_id'])) {

http_response_code(401);

echo json_encode([
        'success' => false,
        'message' => 'Authentication required.'
    ]);

exit;
}

$userId = (int)$_SESSION['user_id'];

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

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {

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

$topicId = isset($data['topic_id'])
        ? (int)$data['topic_id']
        : 0;

    $title = trim(
        $data['title'] ?? ''
    );

    $description = trim(
        $data['description'] ?? ''
    );

if ($topicId <= 0) {

http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid topic ID.'
        ]);

exit;
    }

if ($title === '') {

http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Title is required.'
        ]);

exit;
    }

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

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

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

$topicId = isset($data['topic_id'])
        ? (int)$data['topic_id']
        : 0;

if ($topicId <= 0) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid topic ID.'
        ]);

        exit;
    }

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed'
    ]);

    exit;
}

$action = $_POST['action'] ?? '';

$topicId = filter_input(
    INPUT_POST,
    'topic_id',
    FILTER_VALIDATE_INT
);

if (!$topicId) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid topic.'
    ]);

    exit;
}

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

if (!$previousCompleted) {

    http_response_code(403);

    echo json_encode([
        'success' => false,
        'message' =>
            'This stage is locked. Complete the previous stage first.'
    ]);

    exit;
}

if ($action === 'read') {

if ((int)$topic['is_read'] === 1) {

        echo json_encode([
            'success' => true,
            'message' => 'Stage already opened.'
        ]);

        exit;
    }

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

if (
        !isset($_SERVER['HTTP_ACCEPT']) ||
        strpos(
            $_SERVER['HTTP_ACCEPT'],
            'application/json'
        ) === false
    ) {

        header(
            'Location: ../roadmap?stage='
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

if ($action === 'complete_activity') {

if ((int)$topic['is_read'] !== 1) {

        http_response_code(403);

        echo json_encode([
            'success' => false,
            'message' =>
                'Read the stage before completing activities.'
        ]);

        exit;
    }

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

$activityIndex = filter_input(
        INPUT_POST,
        'activity_index',
        FILTER_VALIDATE_INT
    );

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

$activityCount = count($activities);

    for ($i = 0; $i < $activityCount; $i++) {

        $progress[$i] =
            !empty($progress[$i])
                ? 1
                : 0;
    }

$progress[$activityIndex] = 1;

$allCompleted = true;

    for ($i = 0; $i < $activityCount; $i++) {

        if ((int)$progress[$i] !== 1) {

            $allCompleted = false;

            break;
        }
    }

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

http_response_code(400);

echo json_encode([
    'success' => false,
    'message' => 'Unknown action.'
]);

exit;
