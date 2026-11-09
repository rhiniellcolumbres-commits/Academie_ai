<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$user = getUserById($pdo, $_SESSION['user_id']);
if (!$user) {
    session_destroy();
    header('Location: ./');
    exit;
}

$roadmapStmt = $pdo->prepare('SELECT id FROM study_paths WHERE user_id = ? LIMIT 1');
$roadmapStmt->execute([(int) $_SESSION['user_id']]);
$hasExistingRoadmap = (bool) $roadmapStmt->fetchColumn();
$isRetake = isset($_GET['retake']) && $_GET['retake'] === '1';
$pending = $_SESSION['pending_assessment'] ?? null;
$isChoosingPath = false;
if (is_array($pending) && (int)($pending['user_id'] ?? 0) === (int)$_SESSION['user_id']) {
    $isChoosingPath = true;
}
if ($hasExistingRoadmap && !$isRetake) redirect('dashboard?notice=assessment_exists');
if (!$hasExistingRoadmap) $isRetake = false;

$quiz = getKnowledgePretestQuestions();
if (isset($_GET['error'])) {
    $errorMessages = [
        'csrf' => 'Your session expired. Refresh the page and try again.',
        'save' => 'We could not save the new roadmap. Please try again.',
        'choice' => 'Choose one of your matched paths before continuing.',
        'invalid' => 'Please answer every pre-test question.'
    ];
    $error = $errorMessages[$_GET['error']] ?? 'We could not process your request. Please try again.';
}

if ($isChoosingPath) {
    $introEyebrow = 'YOUR PRE-TEST RESULTS';
    $introHeading = 'Choose a matched technology path.';
    $score = (int)($pending['assessment']['assessment_answers']['score'] ?? 0);
    $scoreTotal = (int)($pending['assessment']['assessment_answers']['total'] ?? count($quiz));
    $introDescription = 'You scored ' . $score . ' / ' . $scoreTotal . '. Your results match several paths equally, so choose the roadmap you want to follow.';
    $matchedPaths = is_array($pending['top_domains'] ?? null) ? $pending['top_domains'] : [];
    $matchedScores = is_array($pending['assessment']['assessment_answers']['domain_scores'] ?? null)
        ? $pending['assessment']['assessment_answers']['domain_scores']
        : [];
} else {
    $introEyebrow = 'KNOWLEDGE PRE-TEST';
    $introHeading = 'Show us what you know.';
    $introDescription = 'Answer these ' . count($quiz)
        . ' questions. Your results help the AI choose a starting level and recommend a technology path and learning roadmap that fit your knowledge.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knowledge Pre-test | Academie</title>
    <link rel="stylesheet" href="css/main.css">
</head>
<body class="assessment-page">
<?php require __DIR__ . '/includes/navbar.php'; ?>
<main class="assessment-wrapper">
    <div class="assessment-intro">
        <span class="eyebrow assessment-eyebrow"><?= e($introEyebrow) ?></span>
        <h1><?= e($introHeading) ?></h1>
        <p><?= e($introDescription) ?></p>
    </div>
    <?php if (isset($error)): ?>
        <div class="alert alert-warning text-center mb-4" role="alert"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($isRetake): ?>
        <div class="assessment-retake-notice" role="alert">
            <div class="assessment-retake-copy">
                <h2>Retaking the pre-test?</h2>
                <p>Generating a new roadmap replaces your current one and resets stage and activity progress.</p>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($isChoosingPath): ?>
        <form id="assessmentForm" action="actions/save-assessment.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="retake_assessment" value="<?= $isRetake ? '1' : '0' ?>">
            <input type="hidden" name="choose_path" value="1">
            <section class="assessment-section path-question">
                <div class="path-question-heading"><h3>Your equally matched paths</h3><span class="path-single-choice">Choose one</span></div>
                <div class="assessment-options path-choice-grid">
                    <?php foreach ($matchedPaths as $path): ?>
                        <label class="assessment-option path-choice">
                            <input type="radio" name="selected_path" value="<?= e($path) ?>" required>
                            <span class="option-content"><strong><?= e($path) ?></strong><small><?= (int)($matchedScores[$path] ?? 0) ?> of 2 topic questions correct</small></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>
            <div class="assessment-actions assessment-submit-actions">
                <button type="submit" class="assessment-start">Generate my roadmap →</button>
            </div>
        </form>
    <?php else: ?>
        <form id="assessmentForm" action="actions/save-assessment.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="retake_assessment" value="<?= $isRetake ? '1' : '0' ?>">
            <section class="assessment-section path-question">
                <?php foreach ($quiz as $index => $item): $field = 'pretest_' . ($index + 1); ?>
                    <div class="path-question-item">
                        <div class="path-question-heading">
                            <span class="path-question-number"><?= sprintf('%02d', $index + 1) ?></span>
                            <h3><?= e($item['question']) ?></h3>
                            <span class="path-single-choice">Choose one</span>
                        </div>
                        <div class="assessment-options path-choice-grid">
                            <?php foreach ($item['choices'] as $choice => $label): ?>
                                <label class="assessment-option path-choice">
                                    <input type="radio" name="<?= e($field) ?>" value="<?= $choice ?>" required>
                                    <span class="option-content"><strong><?= e($label) ?></strong></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>
            <div class="assessment-actions assessment-submit-actions">
                <button type="submit" class="assessment-start"><?= $isRetake ? 'Retake pre-test &amp; generate roadmap →' : 'Finish pre-test &amp; recommend my roadmap →' ?></button>
            </div>
        </form>
    <?php endif; ?>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
