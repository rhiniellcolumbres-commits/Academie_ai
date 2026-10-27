<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/audit.php';

if (!isset($_SESSION['user_id'])) {
    
    redirect('../');
}

require_post();

if (!csrf_valid($_POST['csrf_token'] ?? null)) {
    redirect('../assessment?retake=1&error=csrf');
}

$userId = (int) $_SESSION['user_id'];

$existingRoadmapStmt = $pdo->prepare(
    'SELECT id FROM study_paths WHERE user_id = ? LIMIT 1'
);
$existingRoadmapStmt->execute([$userId]);

$hasExistingRoadmap = (bool) $existingRoadmapStmt->fetchColumn();

if ($hasExistingRoadmap && ($_POST['retake_assessment'] ?? '') !== '1') {
    redirect('../dashboard?notice=assessment_exists');
}

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

// Keeps the career choices tied for the highest assessment score.
$topMatches = array_keys(array_filter($scores, function ($score) use ($topScore) {
    return $score === $topScore;
}));

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

$checkAssessment = $pdo->prepare(
    'SELECT *
     FROM assessments
     WHERE user_id = ?
     LIMIT 1'
);

$checkAssessment->execute([$userId]);

$assessment = $checkAssessment->fetch(PDO::FETCH_ASSOC);

if ($assessment) {

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

$user = getUserById(
    $pdo,
    $userId
);

if (!$user) {

session_destroy();

    redirect('../');

}

try {
    $generation = save_generated_topics(
        $pdo,
        $userId,
        $user
    );
} catch (Throwable $e) {
    // Restore the previous assessment if replacing its roadmap failed.
    try {
        if ($assessment) {
            $restore = $pdo->prepare(
                'UPDATE assessments
                 SET current_level = ?, improvement_goal = ?, career_goal = ?,
                     interests = ?, skills = ?, assessment_answers = ?
                 WHERE user_id = ?'
            );
            $restore->execute([
                $assessment['current_level'],
                $assessment['improvement_goal'],
                $assessment['career_goal'],
                $assessment['interests'],
                $assessment['skills'],
                $assessment['assessment_answers'],
                $userId
            ]);
        } else {
            $removeAssessment = $pdo->prepare(
                'DELETE FROM assessments WHERE user_id = ?'
            );
            $removeAssessment->execute([$userId]);
        }
    } catch (Throwable $restoreError) {
        error_log('Could not restore assessment after roadmap save failed.');
    }

    error_log('Could not save the generated study roadmap.');
    redirect('../assessment?retake=1&error=save');
}

log_ai_usage($pdo, $userId, 'assessment_roadmap', $generation['source'] === 'gemini' ? 'success' : 'fallback', 'Roadmap source: ' . $generation['source']);

redirect('../dashboard?success=assessment&source=' . urlencode($generation['source']));
