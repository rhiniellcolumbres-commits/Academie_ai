<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/audit.php';

if (!isset($_SESSION['user_id'])) redirect('../');
require_post();

$userId = (int) $_SESSION['user_id'];
$retakeRequested = ($_POST['retake_assessment'] ?? '') === '1';
if (!csrf_valid($_POST['csrf_token'] ?? null)) {
    redirect('../assessment?' . ($retakeRequested ? 'retake=1&' : '') . 'error=csrf');
}

$existingRoadmapStmt = $pdo->prepare('SELECT id FROM study_paths WHERE user_id = ? LIMIT 1');
$existingRoadmapStmt->execute([$userId]);
$hasExistingRoadmap = (bool) $existingRoadmapStmt->fetchColumn();
if ($hasExistingRoadmap && !$retakeRequested) redirect('../dashboard?notice=assessment_exists');

$isChoosingPath = ($_POST['choose_path'] ?? '') === '1';
if ($isChoosingPath) {
    $pending = $_SESSION['pending_assessment'] ?? null;
    if (!is_array($pending) || (int)($pending['user_id'] ?? 0) !== $userId || !is_array($pending['assessment'] ?? null)) {
        redirect('../assessment?' . ($retakeRequested ? 'retake=1&' : '') . 'error=invalid');
    }

    $retakeRequested = !empty($pending['retake']);
    if ($hasExistingRoadmap && !$retakeRequested) redirect('../dashboard?notice=assessment_exists');

    $assessmentData = $pending['assessment'];
    $selectedPath = clean_text($_POST['selected_path'] ?? '', 150);
    $matchedPaths = $pending['top_domains'] ?? [];
    if (!is_array($matchedPaths) || !in_array($selectedPath, $matchedPaths, true)) {
        redirect('../assessment?choose_path=1&' . ($retakeRequested ? 'retake=1&' : '') . 'error=choice');
    }

    $assessmentData['career_goal'] = $selectedPath;
    $assessmentData['assessment_answers']['selected_path'] = $selectedPath;
    $assessmentData['assessment_answers']['recommended_label'] = $selectedPath;
} else {
    unset($_SESSION['pending_assessment']);
    $questions = getKnowledgePretestQuestions();
    $answers = [];
    $domainScores = [];
    $score = 0;

    foreach ($questions as $index => $question) {
        $field = 'pretest_' . ($index + 1);
        $answer = filter_var($_POST[$field] ?? null, FILTER_VALIDATE_INT);
        if ($answer === false || $answer === null || !array_key_exists($answer, $question['choices'])) {
            redirect('../assessment?' . ($retakeRequested ? 'retake=1&' : '') . 'error=invalid');
        }

        $answers[$field] = $answer;
        $isCorrect = $answer === $question['correct'];
        if (!empty($question['domain'])) {
            $domainScores[$question['domain']] = ($domainScores[$question['domain']] ?? 0) + ($isCorrect ? 1 : 0);
        }
        $score += $isCorrect ? 1 : 0;
    }

    arsort($domainScores);
    $highestDomainScore = max($domainScores);
    $topDomains = [];
    foreach ($domainScores as $domain => $domainScore) {
        if ($domainScore === $highestDomainScore) {
            $topDomains[] = $domain;
        }
    }

    // Convert the overall score into a starting level and learning focus.
    if ($score <= 4) {
        $currentLevel = 'Complete Beginner';
    } elseif ($score <= 8) {
        $currentLevel = 'Beginner';
    } elseif ($score <= 12) {
        $currentLevel = 'Intermediate';
    } else {
        $currentLevel = 'Advanced';
    }

    if ($score <= 5) {
        $improvementGoal = 'Learn the Fundamentals';
    } elseif ($score <= 10) {
        $improvementGoal = 'Improve My Skills';
    } else {
        $improvementGoal = 'Build Practical Experience';
    }

    $recommendedPath = count($topDomains) === 1 ? $topDomains[0] : null;
    $assessmentData = [
        'current_level' => $currentLevel,
        'improvement_goal' => $improvementGoal,
        'career_goal' => $recommendedPath ?? '',
        'assessment_answers' => [
            'type' => 'knowledge_pretest',
            'answers' => $answers,
            'score' => $score,
            'total' => count($questions),
            'domain_scores' => $domainScores,
            'recommended_domain' => $recommendedPath ?? implode(', ', $topDomains),
            'recommended_label' => $recommendedPath
        ]
    ];

    if (count($topDomains) > 1) {
        $_SESSION['pending_assessment'] = [
            'user_id' => $userId,
            'retake' => $retakeRequested,
            'top_domains' => $topDomains,
            'assessment' => $assessmentData
        ];
        redirect('../assessment?choose_path=1' . ($retakeRequested ? '&retake=1' : ''));
    }
}

$assessmentAnswers = json_encode($assessmentData['assessment_answers'], JSON_UNESCAPED_UNICODE);
$checkAssessment = $pdo->prepare('SELECT * FROM assessments WHERE user_id = ? LIMIT 1');
$checkAssessment->execute([$userId]);
$assessment = $checkAssessment->fetch(PDO::FETCH_ASSOC);

if ($assessment) {
    $stmt = $pdo->prepare('UPDATE assessments SET current_level=?, improvement_goal=?, career_goal=?, assessment_answers=? WHERE user_id=?');
    $stmt->execute([$assessmentData['current_level'], $assessmentData['improvement_goal'], $assessmentData['career_goal'], $assessmentAnswers, $userId]);
} else {
    $stmt = $pdo->prepare('INSERT INTO assessments (user_id,current_level,improvement_goal,career_goal,assessment_answers) VALUES (?,?,?,?,?)');
    $stmt->execute([$userId, $assessmentData['current_level'], $assessmentData['improvement_goal'], $assessmentData['career_goal'], $assessmentAnswers]);
}

$user = getUserById($pdo, $userId);
if (!$user) {
    session_destroy();
    redirect('../');
}

try {
    $generation = save_generated_topics($pdo, $userId, $user);
} catch (Throwable $e) {
    try {
        if ($assessment) {
            $restore = $pdo->prepare('UPDATE assessments SET current_level=?, improvement_goal=?, career_goal=?, assessment_answers=? WHERE user_id=?');
            $restore->execute([$assessment['current_level'], $assessment['improvement_goal'], $assessment['career_goal'], $assessment['assessment_answers'], $userId]);
        } else {
            $pdo->prepare('DELETE FROM assessments WHERE user_id=?')->execute([$userId]);
        }
    } catch (Throwable $restoreError) {
        error_log('Could not restore assessment after roadmap save failed.');
    }

    error_log('Could not save the generated study roadmap.');
    $retryUrl = '../assessment?';
    if (isset($_SESSION['pending_assessment'])) {
        $retryUrl .= 'choose_path=1&';
    }
    redirect($retryUrl . ($retakeRequested ? 'retake=1&' : '') . 'error=save');
}

unset($_SESSION['pending_assessment']);
$usageStatus = 'fallback';
if ($generation['source'] === 'gemini') {
    $usageStatus = 'success';
}
log_ai_usage($pdo, $userId, 'assessment_roadmap', $usageStatus, 'Roadmap source: ' . $generation['source']);
redirect('../dashboard?success=assessment&source=' . urlencode($generation['source']));
