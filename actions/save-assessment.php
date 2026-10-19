<?php

session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/audit.php';


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {
    redirect('../');
}

require_post();

$userId = (int) $_SESSION['user_id'];

$existingRoadmapStmt = $pdo->prepare(
    'SELECT id FROM study_paths WHERE user_id = ? LIMIT 1'
);
$existingRoadmapStmt->execute([$userId]);
$hasExistingRoadmap = (bool) $existingRoadmapStmt->fetchColumn();

// An existing roadmap can only be replaced through the intentional retake flow.
if ($hasExistingRoadmap && ($_POST['retake_assessment'] ?? '') !== '1') {
    redirect('../dashboard?notice=assessment_exists');
}

/*
|--------------------------------------------------------------------------
| Get Assessment Data
|--------------------------------------------------------------------------
*/

$currentLevel = clean_text(
    $_POST['current_level'] ?? '',
    50
);

$improvementGoal = clean_text(
    $_POST['improvement_goal'] ?? '',
    150
);

$specializations = [
    'SE' => 'Software Engineering / Core Developer',
    'FULLSTACK' => 'Full-Stack Development',
    'FRONTEND' => 'Frontend Web Development',
    'BACKEND' => 'Backend Development',
    'UIUX' => 'UI/UX Design',
    'AIML' => 'AI & Machine Learning',
    'GAMEDEV' => 'Game Development'
];

$questionFields = ['q_interest', 'q_activity', 'q_strength', 'q_interest_area', 'q_goal'];
$scores = array_fill_keys(array_keys($specializations), 0);
$answers = [];

foreach ($questionFields as $field) {
    $answer = clean_text($_POST[$field] ?? '', 20);
    if (!array_key_exists($answer, $specializations)) {
        redirect('../assessment?error=invalid');
    }
    $scores[$answer]++;
    $answers[$field] = $answer;
}
arsort($scores);
$topScore = reset($scores);
// Keep only career scores tied with the highest assessment score.
$topMatches = array_keys(array_filter($scores, function ($score) use ($topScore) {
    return $score === $topScore;
}));
// Deterministic tie-break: first answer, then subsequent answers.
$careerCode = $answers['q_interest'];
if (!in_array($careerCode, $topMatches, true)) {
    foreach ($answers as $answer) {
        if (in_array($answer, $topMatches, true)) {
            $careerCode = $answer;
            break;
        }
    }
}
$careerGoal = $specializations[$careerCode];
$assessmentAnswers = json_encode([
    'answers' => $answers,
    'scores' => $scores,
    'recommended_code' => $careerCode,
    'recommended_label' => $careerGoal
], JSON_UNESCAPED_UNICODE);

$interests = clean_text(
    $_POST['interests'] ?? '',
    1000
);

$skills = clean_text(
    $_POST['skills'] ?? '',
    1000
);


/*
|--------------------------------------------------------------------------
| Allowed Values
|--------------------------------------------------------------------------
*/

$allowedLevels = [
    'Complete Beginner',
    'Beginner',
    'Intermediate',
    'Advanced'
];


$allowedGoals = [
    'Learn the Fundamentals',
    'Improve My Skills',
    'Build Practical Experience',
    'Prepare for a Career',
    'Prepare for Further Studies'
];



/*
|--------------------------------------------------------------------------
| Validate Assessment
|--------------------------------------------------------------------------
*/

if (
    $currentLevel === '' ||
    $improvementGoal === '' ||
    $careerGoal === ''
) {

    redirect('../assessment?error=empty');

}


if (!in_array($currentLevel, $allowedLevels, true)) {

    redirect('../assessment?error=invalid');

}


if (!in_array($improvementGoal, $allowedGoals, true)) {

    redirect('../assessment?error=invalid');

}



/*
|--------------------------------------------------------------------------
| Save Assessment
|--------------------------------------------------------------------------
*/

$checkAssessment = $pdo->prepare(
    'SELECT id
     FROM assessments
     WHERE user_id = ?
     LIMIT 1'
);

$checkAssessment->execute([$userId]);

$assessment = $checkAssessment->fetch(PDO::FETCH_ASSOC);


if ($assessment) {

    // Assessment already exists
    $stmt = $pdo->prepare(
        'UPDATE assessments
         SET current_level = ?,
             improvement_goal = ?,
             career_goal = ?,
             interests = ?,
             skills = ?,
             assessment_answers = ?
         WHERE user_id = ?'
    );

    $stmt->execute([
        $currentLevel,
        $improvementGoal,
        $careerGoal,
        $interests !== '' ? $interests : null,
        $skills !== '' ? $skills : null,
        $assessmentAnswers,
        $userId
    ]);

} else {

    // First assessment for this user
    $stmt = $pdo->prepare(
        'INSERT INTO assessments
        (
            user_id,
            current_level,
            improvement_goal,
            career_goal,
            interests,
            skills,
            assessment_answers
        )
        VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    $stmt->execute([
        $userId,
        $currentLevel,
        $improvementGoal,
        $careerGoal,
        $interests !== '' ? $interests : null,
        $skills !== '' ? $skills : null,
        $assessmentAnswers
    ]);
}


/*
|--------------------------------------------------------------------------
| Get Updated User
|--------------------------------------------------------------------------
*/

$user = getUserById(
    $pdo,
    $userId
);


if (!$user) {

    session_destroy();

    redirect('../');

}


/*
|--------------------------------------------------------------------------
| Generate Roadmap
|--------------------------------------------------------------------------
*/

$generation = save_generated_topics(
    $pdo,
    $userId,
    $user
);

log_ai_usage($pdo, $userId, 'assessment_roadmap', $generation['source'] === 'gemini' ? 'success' : 'fallback', 'Roadmap source: ' . $generation['source']);

/*
|--------------------------------------------------------------------------
| Go To Dashboard
|--------------------------------------------------------------------------
*/

redirect('../dashboard?success=assessment&source=' . urlencode($generation['source']));
