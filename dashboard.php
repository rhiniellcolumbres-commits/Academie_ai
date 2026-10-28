<?php

require_once __DIR__ . '/includes/auth.php';

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$user = getUserById(
    $pdo,
    $_SESSION['user_id']
);

if (!$user) {

unset($_SESSION['user_id']);

session_regenerate_id(true);

    header(
        'Location: ./?error=session'
    );

    exit;
}

$assessmentStmt = $pdo->prepare(
    'SELECT *
     FROM assessments
     WHERE user_id = ?
     LIMIT 1'
);

$assessmentStmt->execute([
    $user['id']
]);

$assessment = $assessmentStmt->fetch();

$alternativeRecommendations = [];
if (!empty($assessment['assessment_answers'])) {
    
    $savedAssessmentAnswers = json_decode($assessment['assessment_answers'], true);
    $savedScores = $savedAssessmentAnswers['scores'] ?? [];
    
    $savedLabels = [
        'SE' => 'Software Engineering / Core Developer',
        'FULLSTACK' => 'Full-Stack Development',
        'FRONTEND' => 'Frontend Web Development',
        'BACKEND' => 'Backend Development',
        'UIUX' => 'UI/UX Design',
        'AIML' => 'AI & Machine Learning',
        'GAMEDEV' => 'Game Development'
    ];
    if (is_array($savedScores)) {
        
        arsort($savedScores);
        
        foreach ($savedScores as $code => $score) {
            if (isset($savedLabels[$code]) && $savedLabels[$code] !== ($assessment['career_goal'] ?? '')) {
                $alternativeRecommendations[] = $savedLabels[$code];
            }
            if (count($alternativeRecommendations) >= 2) {
                break;
            }
        }
    }
}

$topics = getStudyTopics(
    $pdo,
    $user['id']
);

$progress = getRoadmapProgress($topics);

$total               = $progress['totalStages'];
$done                = $progress['completedStages'];
$totalActivities     = $progress['totalActivities'];
$completedActivities = $progress['completedActivities'];

$percent = $total > 0
    ? (int)round(($done / $total) * 100)
    : 0;

syncAchievements($pdo, (int)$user['id'], $progress);
$earnedAchievementCount = count(getUserAchievements($pdo, (int)$user['id']));

$currentStage = null;

foreach ($topics as $topic) {

    if ((int)$topic['completed'] === 0) {

        $currentStage = $topic;

        break;
    }

}

$currentActivities = $currentStage ? getActivities($currentStage['activity'] ?? '') : [];
$currentActivityStates = $currentStage
    ? json_decode((string)($currentStage['activity_progress'] ?? ''), true)
    : [];
if (!is_array($currentActivityStates)) {
    $currentActivityStates = [];
}
$nextActivities = [];
foreach ($currentActivities as $activityIndex => $activityTitle) {
    if (empty($currentActivityStates[$activityIndex])) {
        $nextActivities[] = ['index' => $activityIndex, 'title' => $activityTitle];
    }
    if (count($nextActivities) === 4) {
        break;
    }
}
$latestAchievements = getUserAchievements($pdo, (int)$user['id']);
$latestAchievement = $latestAchievements[0] ?? null;
$latestAchievementIcon = trim((string)($latestAchievement['icon'] ?? '')) ?: 'bi bi-award';
?>
<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Dashboard | Academie
    </title>
    
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >
    <link
        rel="stylesheet"
        href="css/main.css"
    >

</head>

<body class="dashboard-page">

<?php require __DIR__ . '/includes/navbar.php'; ?>

<main class="dashboard-content">

<div class="dashboard-heading">

        <div>

            <span class="eyebrow dashboard-eyebrow">
                <i class="bi bi-grid-1x2-fill" aria-hidden="true"></i>
                STUDENT DASHBOARD
            </span>

            <h1>
                Welcome, <?= e($user['first_name']) ?>!
            </h1>

            <p>
                Keep learning, complete your roadmap,
                and work toward your career goal.
            </p>

        </div>

    </div>

<?php if (
        isset($_GET['success']) &&
        $_GET['success'] === 'assessment'
    ): ?>

        <div class="dashboard-success">

            Your learning assessment has been saved.
            Your personalized study path is ready!

        </div>

    <?php endif; ?>

<?php if (
        isset($_GET['success']) &&
        $_GET['success'] === 'career_changed'
    ): ?>

        <div class="dashboard-success">

            Your career goal has been changed.
            Your new personalized roadmap is ready!

        </div>

    <?php endif; ?>

    <?php if (isset($_GET['notice']) && $_GET['notice'] === 'assessment_exists'): ?>
        <div class="alert alert-info mt-3" role="alert">
            You already have a learning roadmap. Use <strong>Retake Assessment</strong> if you want to create a new one.
        </div>
    <?php endif; ?>

<section class="dashboard-overview">
    <div class="overview-main">
        <span class="stage-pill">
            <?= $currentStage ? 'Current stage · ' . (int)$currentStage['order_number'] . ' of ' . (int)$total : ($total > 0 ? 'Roadmap complete' : 'Your learning path') ?>
        </span>
        <?php if (!empty($assessment['career_goal'])): ?>
            <h2><?= e($currentStage['title'] ?? $assessment['career_goal']) ?></h2>
            <p><?= e($currentStage['description'] ?? ('Your personalized path toward becoming a ' . $assessment['career_goal'] . '.')) ?></p>
            <div class="dashboard-learning-actions">
                <a href="roadmap" class="dashboard-primary-btn">Continue learning <span aria-hidden="true">→</span></a>
                <a href="assessment?retake=1" class="assessment-retake-link" onclick="return confirm('Retake your assessment? Generating a new roadmap will replace your current roadmap and reset its stage and activity progress. This cannot be undone.');">Retake assessment</a>
            </div>
        <?php else: ?>
            <h2>Start your learning journey</h2>
            <p>Complete your assessment to create a personalized learning roadmap.</p>
            <div class="dashboard-learning-actions"><a href="assessment" class="dashboard-primary-btn">Take assessment <span aria-hidden="true">→</span></a></div>
        <?php endif; ?>
        <?php if (!empty($alternativeRecommendations)): ?>
            <p class="alternative-paths">Other paths to explore: <?= e(implode(' · ', $alternativeRecommendations)) ?></p>
        <?php endif; ?>
    </div>
    <div class="overview-progress">
        <div class="progress-heading"><span class="card-label">ROADMAP PROGRESS</span><strong><?= $percent ?>%</strong></div>
        <div class="dashboard-progress-bar" role="progressbar" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100"><span style="width: <?= $percent ?>%;"></span></div>
        <p><?= $done ?> of <?= $total ?> stages · <?= $completedActivities ?> of <?= $totalActivities ?> activities</p>
    </div>
</section>

<section class="dashboard-learning-stats" aria-label="Learning progress">
    <article class="dashboard-stat-card"><strong><?= $done ?>/<?= $total ?></strong><span>Stages completed</span></article>
    <article class="dashboard-stat-card"><strong><?= $completedActivities ?>/<?= $totalActivities ?></strong><span>Activities done</span></article>
    <article class="dashboard-stat-card"><strong><?= $earnedAchievementCount ?></strong><span>Badges earned</span></article>
    <article class="dashboard-stat-card"><strong><?= max(0, $total - $done) ?></strong><span>Stages to go</span></article>
</section>

<section class="current-learning">
    <div class="dashboard-section-title">
        <div><span class="eyebrow">UP NEXT IN THIS STAGE</span><h2>Keep your momentum</h2></div>
        <?php if (!empty($topics)): ?><a href="roadmap" class="view-roadmap-link">View roadmap</a><?php endif; ?>
    </div>
    <?php if ($currentStage !== null): ?>
        <div class="next-activities-card">
            <?php if ($nextActivities): ?>
                <?php foreach ($nextActivities as $nextActivity): ?>
                    <div class="next-activity-row">
                        <span class="activity-check" aria-hidden="true"><i class="bi bi-circle"></i></span>
                        <div class="next-activity-copy"><strong><?= e($nextActivity['title']) ?></strong><span><?= e($currentStage['title']) ?></span></div>
                        <a href="lesson?topic_id=<?= (int)$currentStage['id'] ?>&amp;activity_index=<?= (int)$nextActivity['index'] ?>" class="activity-start-link">Start</a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="next-activities-empty">All activities in this stage are complete. Continue to the roadmap for your next stage.</p>
                <a href="roadmap" class="dashboard-primary-btn">Open roadmap →</a>
            <?php endif; ?>
        </div>
    <?php elseif ($total > 0): ?>
        <div class="next-activities-card"><h3>Roadmap completed!</h3><p>Congratulations! You have completed your learning path.</p><a href="roadmap" class="dashboard-primary-btn">Review roadmap →</a></div>
    <?php else: ?>
        <div class="next-activities-card"><h3>No roadmap yet</h3><p>Complete your assessment to generate a personalized study path.</p><a href="assessment" class="dashboard-primary-btn">Take assessment →</a></div>
    <?php endif; ?>
</section>

<aside class="dashboard-side-cards">
    <?php if (!empty($assessment['career_goal'])): ?>
        <section class="assessment-summary">
            <span class="eyebrow">YOUR ASSESSMENT</span>
            <h2><?= e($assessment['career_goal']) ?></h2>
            <div class="assessment-detail"><span>Level</span><strong><?= e($assessment['current_level'] ?? 'Not specified') ?></strong></div>
            <div class="assessment-detail"><span>Goal</span><strong><?= e($assessment['improvement_goal'] ?? 'Not specified') ?></strong></div>
            <a href="assessment?retake=1" class="assessment-retake-link" onclick="return confirm('Retake your assessment? Generating a new roadmap will replace your current roadmap and reset its stage and activity progress. This cannot be undone.');">Retake assessment</a>
        </section>
    <?php endif; ?>
    <section class="latest-badge-card">
        <h2>Latest badge</h2>
        <?php if ($latestAchievement): ?>
            <div class="latest-badge-content"><span class="badge-medallion"><i class="<?= e($latestAchievementIcon) ?>" aria-hidden="true"></i></span><div><strong><?= e($latestAchievement['title']) ?></strong><span><?= e($latestAchievement['description']) ?></span></div></div>
        <?php else: ?>
            <div class="latest-badge-content"><span class="badge-medallion"><i class="bi bi-star" aria-hidden="true"></i></span><div><strong>Your first badge is waiting</strong><span>Complete an activity to earn one.</span></div></div>
        <?php endif; ?>
    </section>
</aside>

</main>

<style>
/* Dashboard shell inspired by the supplied Academie layout. */
body.dashboard-page {
    --dashboard-blue: #2453e6;
    --dashboard-ink: #101d36;
    --dashboard-muted: #60708e;
    --dashboard-canvas: #f3f6fb;
    --dashboard-sidebar-width: clamp(248px, 16vw, 304px);
    min-height: 100vh;
    padding-left: var(--dashboard-sidebar-width);
    background: var(--dashboard-canvas);
}
body.dashboard-page .main-navbar {
    position: fixed !important;
    inset: 0 auto 0 0;
    z-index: 1000;
    display: flex !important;
    width: var(--dashboard-sidebar-width) !important;
    min-height: 100vh;
    height: 100vh;
    flex-direction: column;
    align-items: stretch;
    justify-content: flex-start;
    gap: 26px;
    padding: 24px 16px !important;
    box-sizing: border-box !important;
    background: #fff;
    border: 0;
    border-right: 1px solid #e8edf5;
    box-shadow: none;
}
body.dashboard-page .main-navbar .main-logo {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0;
    color: var(--dashboard-ink);
    font-size: 21px;
    font-weight: 800;
}
body.dashboard-page .dashboard-brand-mark {
    display: inline-flex;
    width: 34px;
    height: 34px;
    flex: 0 0 34px;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: var(--dashboard-blue);
    color: #fff;
    font-size: 17px;
    font-weight: 800;
}
body.dashboard-page .main-navbar .main-nav-links {
    display: flex;
    width: 100%;
    flex-direction: column;
    align-items: stretch;
    gap: 4px;
    margin: 0 !important;
    padding: 0;
}
body.dashboard-page .main-navbar .main-nav-links a,
body.dashboard-page .main-navbar .main-nav-links a.active,
body.dashboard-page .main-navbar .main-nav-links a:not(.active) {
    display: flex;
    width: 100% !important;
    min-width: 0 !important;
    min-height: 44px;
    align-items: center;
    justify-content: flex-start !important;
    gap: 10px;
    padding: 0 14px !important;
    border: 0;
    border-radius: 11px;
    color: #536482;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
}
body.dashboard-page .main-navbar .main-nav-links a:hover,
body.dashboard-page .main-navbar .main-nav-links a.active,
body.dashboard-page .main-navbar .main-nav-links a.active:hover {
    color: var(--dashboard-blue);
    background: #e9efff;
}
body.dashboard-page .main-navbar .main-nav-links a::after { display: none !important; }
body.dashboard-page .main-navbar .main-nav-links a i { font-size: 17px; }
body.dashboard-page .main-navbar .navbar-account {
    display: flex;
    width: 100%;
    flex-direction: column;
    align-items: stretch;
    gap: 10px;
    margin: auto 0 0;
}
body.dashboard-page .main-navbar .navbar-user-info {
    max-width: none;
    width: 100%;
    padding: 12px;
    text-align: left;
    border-radius: 12px;
    background: #f3f6fb;
}
body.dashboard-page .main-navbar .navbar-user-name { color: var(--dashboard-ink); }
body.dashboard-page .main-navbar .navbar-user-track { color: var(--dashboard-muted); }
body.dashboard-page .main-navbar .navbar-logout {
    width: 100%;
    margin-left: 0;
    min-height: 42px;
    justify-content: center;
    border: 1px solid #e4eaf4;
    border-radius: 11px;
    color: var(--dashboard-ink);
    background: #fff;
}
body.dashboard-page .dashboard-content {
    width: 100% !important;
    max-width: none;
    margin: 0;
    padding: 42px clamp(24px, 2.1vw, 40px) 72px !important;
    box-sizing: border-box;
    display: grid;
    grid-template-columns: minmax(0, 1.45fr) minmax(280px, .9fr);
    column-gap: 24px;
    row-gap: 30px;
}
body.dashboard-page .dashboard-heading {
    grid-column: 1 / -1;
    width: 100%;
    margin: 0 0 32px;
    align-items: flex-end;
}
body.dashboard-page .dashboard-success,
body.dashboard-page .dashboard-content > .alert { grid-column: 1 / -1; margin: 0; }
body.dashboard-page .dashboard-heading h1,
body.dashboard-page .overview-main h2,
body.dashboard-page .dashboard-section-title h2,
body.dashboard-page .current-stage-content h3 { color: var(--dashboard-ink); }
body.dashboard-page .dashboard-heading h1 { font-size: clamp(34px, 4vw, 42px); letter-spacing: -1.5px; }
body.dashboard-page .dashboard-heading p,
body.dashboard-page .overview-main p,
body.dashboard-page .current-stage-content p { color: var(--dashboard-muted); }
body.dashboard-page .dashboard-eyebrow { color: var(--dashboard-blue); }
body.dashboard-page .dashboard-primary-btn { background: var(--dashboard-blue); border-radius: 10px; }
body.dashboard-page .dashboard-overview {
    grid-column: 1 / -1;
    grid-template-columns: minmax(0, 1.45fr) minmax(280px, .9fr);
    gap: 0;
    align-items: stretch;
    margin: 0;
    overflow: hidden;
    background: var(--dashboard-ink);
    border: 1px solid #0b1730;
    border-radius: 20px;
    box-shadow: 0 1px 2px rgb(16 29 54 / 4%);
}
body.dashboard-page .overview-main,
body.dashboard-page .overview-progress,
body.dashboard-page .dashboard-stat-card,
body.dashboard-page .assessment-summary,
body.dashboard-page .current-stage-card,
body.dashboard-page .learning-empty-card,
body.dashboard-page .learning-complete-card {
    border-color: #e5ebf4;
    border-radius: 18px;
    box-shadow: 0 1px 2px rgb(16 29 54 / 2%);
    box-sizing: border-box;
}
body.dashboard-page .overview-main,
body.dashboard-page .overview-progress { min-width: 0; padding: clamp(24px, 2.2vw, 36px); }
body.dashboard-page .overview-main,
body.dashboard-page .overview-progress {
    background: transparent !important;
    border: 0 !important;
    outline: 0 !important;
    border-radius: 0;
    box-shadow: none !important;
}
body.dashboard-page .overview-main h2,
body.dashboard-page .overview-main .card-label,
body.dashboard-page .overview-main .assessment-retake-link {
    color: #fff;
    background: transparent !important;
}
body.dashboard-page .overview-main p { color: #c5cee0; }
body.dashboard-page .overview-main .assessment-retake-link {
    display: inline-flex;
    min-height: 44px;
    align-items: center;
    padding: 0 16px;
    border: 1px solid #52617d;
    border-radius: 10px;
    text-decoration: none;
}
body.dashboard-page .overview-progress { border-left: 0; }
body.dashboard-page .dashboard-learning-stats { grid-column: 1 / -1; margin: 0; }
body.dashboard-page .dashboard-section-title { margin-bottom: 18px; }
body.dashboard-page .dashboard-stat-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
body.dashboard-page .dashboard-stat-card { min-width: 0; padding: 24px; }
body.dashboard-page .assessment-summary { grid-column: auto; align-self: start; width: 100%; margin: 0; }
body.dashboard-page .assessment-summary > div { padding: 22px 24px; }
body.dashboard-page .current-learning { grid-column: 1; margin: 0; }
body.dashboard-page .overview-progress { background: var(--dashboard-ink); }
body.dashboard-page .overview-progress .card-label,
body.dashboard-page .overview-progress p { color: #c6d0e2; }
body.dashboard-page .overview-progress .progress-number { color: #fff; }
body.dashboard-page .dashboard-progress-bar { background: #334463; }
body.dashboard-page .dashboard-progress-bar span { background: #f4a62a; }
body.dashboard-page .dashboard-stat-card strong { color: var(--dashboard-ink); }
body.dashboard-page .view-roadmap-link { color: var(--dashboard-blue); }
/* Reference dashboard content grid */
body.dashboard-page .dashboard-heading h1 { font-size: clamp(34px, 3.5vw, 42px); letter-spacing: -1.4px; }
body.dashboard-page .dashboard-heading p { color: var(--dashboard-muted); }
body.dashboard-page .stage-pill {
    display: inline-flex;
    align-items: center;
    margin-bottom: 16px;
    padding: 5px 11px;
    border-radius: 999px;
    color: #352100;
    background: #f5a623;
    font-size: 12px;
    font-weight: 700;
}
body.dashboard-page .overview-main h2 { margin-bottom: 10px; font-size: clamp(25px, 2.2vw, 32px); }
body.dashboard-page .overview-main p { max-width: 690px; line-height: 1.6; }
body.dashboard-page .dashboard-learning-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
body.dashboard-page .overview-main .dashboard-primary-btn { background: #fff; color: var(--dashboard-ink); }
body.dashboard-page .overview-main .alternative-paths { margin: 16px 0 0; font-size: 13px; }
body.dashboard-page .overview-progress { justify-content: center; padding-left: clamp(24px, 3vw, 52px); }
body.dashboard-page .progress-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
body.dashboard-page .progress-heading .card-label { margin: 0; color: #c6d0e2; }
body.dashboard-page .progress-heading strong { color: #fff; font-size: 20px; }
body.dashboard-page .overview-progress .dashboard-progress-bar { height: 8px; margin-top: 14px; }
body.dashboard-page .overview-progress p { margin-top: 10px; font-size: 13px; }
body.dashboard-page .dashboard-learning-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
}
body.dashboard-page .dashboard-stat-card {
    min-height: 104px;
    justify-content: center;
    gap: 6px;
    padding: 20px 22px;
    background: #fff;
    border: 1px solid #e5ebf4;
    border-radius: 17px;
}
body.dashboard-page .dashboard-stat-card strong { font-size: 32px; line-height: 1; }
body.dashboard-page .dashboard-stat-card span { color: var(--dashboard-muted); font-size: 13px; }
body.dashboard-page .current-learning,
body.dashboard-page .assessment-summary,
body.dashboard-page .latest-badge-card {
    min-width: 0;
    padding: 26px;
    background: #fff;
    border: 1px solid #e5ebf4;
    border-radius: 18px;
    box-shadow: 0 1px 2px rgb(16 29 54 / 2%);
}
body.dashboard-page .current-learning .dashboard-section-title { align-items: center; margin-bottom: 14px; }
body.dashboard-page .dashboard-section-title h2 { font-size: 21px; letter-spacing: -.5px; }
body.dashboard-page .current-learning .eyebrow { margin-bottom: 6px; }
body.dashboard-page .dashboard-side-cards {
    grid-column: 2;
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    align-content: start;
    gap: 18px;
}
body.dashboard-page .assessment-summary { display: block; }
body.dashboard-page .assessment-summary > div.assessment-detail {
    min-height: 44px;
    box-sizing: border-box;
    margin-top: 8px;
    padding: 10px 12px;
    border: 1px solid #e5ebf4;
    border-radius: 11px;
    background: #fff;
}
body.dashboard-page .assessment-summary .eyebrow { margin-bottom: 5px; }
body.dashboard-page .assessment-summary h2,
body.dashboard-page .latest-badge-card h2 { margin: 0 0 14px; color: var(--dashboard-ink); font-size: 19px; }
body.dashboard-page .assessment-detail { display: flex; justify-content: space-between; gap: 12px; margin-top: 8px; font-size: 13px; }
body.dashboard-page .assessment-detail span { color: var(--dashboard-muted); }
body.dashboard-page .assessment-detail strong { color: var(--dashboard-ink); text-align: right; }
body.dashboard-page .assessment-summary .assessment-retake-link {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    margin-top: 16px;
    border: 1px solid #e5ebf4;
    border-radius: 10px;
    color: var(--dashboard-ink);
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
}
body.dashboard-page .latest-badge-content { display: flex; align-items: center; gap: 13px; }
body.dashboard-page .badge-medallion { display: inline-flex; width: 52px; height: 52px; flex: 0 0 52px; align-items: center; justify-content: center; border-radius: 14px; color: #9a5700; background: #fff3d9; font-size: 20px; }
body.dashboard-page .latest-badge-content strong,
body.dashboard-page .latest-badge-content span { display: block; }
body.dashboard-page .latest-badge-content strong { color: var(--dashboard-ink); font-size: 14px; }
body.dashboard-page .latest-badge-content div > span { margin-top: 4px; color: var(--dashboard-muted); font-size: 12px; }
body.dashboard-page .next-activities-card { overflow: hidden; }
body.dashboard-page .next-activity-row { display: flex; min-height: 62px; align-items: center; gap: 12px; border-top: 1px solid #edf0f5; }
body.dashboard-page .activity-check { color: #a9b7cf; font-size: 19px; }
body.dashboard-page .next-activity-copy { display: grid; min-width: 0; flex: 1; gap: 3px; }
body.dashboard-page .next-activity-copy strong { overflow: hidden; color: var(--dashboard-ink); font-size: 13px; text-overflow: ellipsis; white-space: nowrap; }
body.dashboard-page .next-activity-copy span { overflow: hidden; color: var(--dashboard-muted); font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
body.dashboard-page .activity-start-link { display: inline-flex; min-height: 36px; align-items: center; justify-content: center; padding: 0 14px; border: 1px solid #e5ebf4; border-radius: 10px; color: var(--dashboard-ink); font-size: 13px; font-weight: 700; text-decoration: none; }
body.dashboard-page .next-activities-empty { color: var(--dashboard-muted); line-height: 1.55; }
@media (max-width: 900px) {
    body.dashboard-page { --dashboard-sidebar-width: 0px; padding-left: 0; }
    body.dashboard-page .main-navbar {
        position: sticky !important;
        inset: auto;
        width: 100% !important;
        min-height: 0;
        height: auto;
        flex-direction: row;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        padding: 12px 16px !important;
        border-right: 0;
        border-bottom: 1px solid #e8edf5;
    }
    body.dashboard-page .main-navbar .main-logo { margin-right: auto; }
    body.dashboard-page .main-navbar .main-nav-links {
        order: 3;
        width: 100%;
        flex-direction: row;
        justify-content: flex-start;
        gap: 5px;
    }
    body.dashboard-page .main-navbar .main-nav-links a { min-height: 40px; padding: 0 10px; }
    body.dashboard-page .main-navbar .navbar-account { display: flex; width: auto; flex-direction: row; align-items: center; gap: 0; margin: 0; }
    body.dashboard-page .main-navbar .navbar-user-info { width: auto; padding: 4px; background: transparent; }
    body.dashboard-page .main-navbar .navbar-logout { width: auto; min-height: 38px; padding: 0 12px; }
    body.dashboard-page .dashboard-content { width: 100% !important; padding: 28px 16px 48px !important; }
    body.dashboard-page .dashboard-overview { grid-template-columns: minmax(0, 1fr); gap: 14px; }
    body.dashboard-page .dashboard-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    body.dashboard-page .dashboard-heading { align-items: flex-start; margin-bottom: 22px; }
    body.dashboard-page .dashboard-content { display: grid; grid-template-columns: minmax(0, 1fr); row-gap: 18px; }
    body.dashboard-page .dashboard-heading,
    body.dashboard-page .dashboard-success,
    body.dashboard-page .dashboard-content > .alert,
    body.dashboard-page .dashboard-overview,
    body.dashboard-page .dashboard-learning-stats,
    body.dashboard-page .assessment-summary,
    body.dashboard-page .current-learning { margin-bottom: 24px; }
    body.dashboard-page .overview-progress { border-top: 1px solid #2b3954; border-left: 0; }
    body.dashboard-page .dashboard-side-cards,
    body.dashboard-page .assessment-summary,
    body.dashboard-page .current-learning { grid-column: 1; }
    body.dashboard-page .dashboard-learning-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    body.dashboard-page .current-learning,
    body.dashboard-page .assessment-summary,
    body.dashboard-page .latest-badge-card { padding: 20px; }
}
@media (max-width: 480px) {
    body.dashboard-page .dashboard-learning-stats { grid-template-columns: minmax(0, 1fr); }
    body.dashboard-page .overview-main,
    body.dashboard-page .overview-progress { padding: 22px; }
}
</style>

<style>

.dashboard-page {
    min-height: 100vh;
    background: #f7f7f5;
}

.dashboard-content {
    width: min(1100px, 92%);
    margin: auto;

    padding: 65px 0 100px;
}

.dashboard-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;

    margin-bottom: 40px;
}

.dashboard-heading h1 {
    margin: 0 0 10px;

    font-size: clamp(36px, 5vw, 52px);
    letter-spacing: -2px;

    color: #111111;
}

.dashboard-heading p {
    margin: 0;

    color: #777777;
    line-height: 1.6;
}

.eyebrow {
    display: inline-block;

    margin-bottom: 10px;

    color: #777777;

    font-size: 10px;
    font-weight: 800;

    letter-spacing: 2px;
}

.dashboard-user {
    display: flex;
    align-items: center;
    gap: 12px;
}

.dashboard-avatar {
    width: 46px;
    height: 46px;

    border-radius: 50%;

    background: #111111;
    color: #ffffff;

    display: flex;
    align-items: center;
    justify-content: center;

    font-weight: 800;
}

.dashboard-user strong {
    display: block;

    font-size: 14px;
    color: #111111;
}

.dashboard-user span {
    display: block;

    margin-top: 3px;

    font-size: 12px;
    color: #888888;
}

.dashboard-success {
    padding: 15px 18px;

    margin-bottom: 25px;

    background: #ffffff;

    border: 1px solid #dddddd;
    border-radius: 10px;

    font-size: 13px;
    color: #444444;
}

.dashboard-overview {
    display: grid;

    grid-template-columns: 1.4fr 1fr;

    gap: 20px;

    margin-bottom: 20px;
}

.overview-main,
.overview-progress {
    padding: 32px;

    background: #ffffff;

    border: 1px solid #e5e5e5;
    border-radius: 18px;
}

.card-label {
    display: block;

    margin-bottom: 10px;

    color: #888888;

    font-size: 10px;
    font-weight: 800;

    letter-spacing: 1.5px;
}

.overview-main h2 {
    margin: 0 0 10px;

    font-size: 29px;
    letter-spacing: -1px;

    color: #111111;
}

.overview-main p {
    max-width: 560px;

    margin: 0 0 25px;

    color: #777777;

    line-height: 1.6;
}

.dashboard-primary-btn {
    display: inline-block;

    padding: 12px 18px;

    background: #111111;
    color: #ffffff;

    border-radius: 9px;

    text-decoration: none;

    font-size: 13px;
    font-weight: 700;

    transition: .2s ease;
}

.dashboard-primary-btn:hover {
    color: #ffffff;

    transform: translateY(-1px);

    opacity: .9;
}

.overview-progress {
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.progress-number {
    margin: 5px 0 15px;

    font-size: 48px;
    font-weight: 800;

    letter-spacing: -2px;

    color: #111111;
}

.dashboard-progress-bar {
    width: 100%;
    height: 8px;

    background: #eeeeee;

    border-radius: 20px;

    overflow: hidden;
}

.dashboard-progress-bar span {
    display: block;

    height: 100%;

    background: #111111;

    border-radius: 20px;
}

.overview-progress p {
    margin: 12px 0 0;

    font-size: 13px;
    color: #888888;
}

.dashboard-learning-stats {
    margin: 0 0 42px;
}

.dashboard-learning-stats .dashboard-section-title {
    margin-bottom: 16px;
}

.dashboard-stat-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}

.dashboard-stat-card {
    display: flex;
    min-width: 0;
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
    padding: 22px;
    background: #ffffff;
    border: 1px solid #e5e5e5;
    border-radius: 16px;
}

.dashboard-stat-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 42px;
    width: 42px;
    height: 42px;
    margin-bottom: 2px;
    border-radius: 12px;
    font-size: 21px;
    line-height: 1;
}

.dashboard-stat-icon i {
    display: block;
    line-height: 1;
}

.stat-icon-stages {
    color: #2563eb;
    background: #eff6ff;
}

.stat-icon-activities {
    color: #0f766e;
    background: #ecfdf5;
}

.stat-icon-achievements {
    color: #b45309;
    background: #fffbeb;
}

.dashboard-stat-card strong {
    color: #111111;
    font-size: 28px;
    font-weight: 800;
    line-height: 1.15;
}

.dashboard-stat-card > span:last-child {
    color: #777777;
    font-size: 13px;
    line-height: 1.4;
}

@media (max-width: 700px) {
    .dashboard-stat-grid {
        grid-template-columns: 1fr;
    }

    .dashboard-stat-card {
        padding: 18px;
    }
}

.assessment-summary {
    display: grid;

    grid-template-columns: repeat(3, 1fr);

    margin-bottom: 60px;

    background: #ffffff;

    border: 1px solid #e5e5e5;
    border-radius: 18px;

    overflow: hidden;
}

.assessment-summary > div {
    padding: 23px 28px;

    border-right: 1px solid #eeeeee;
}

.assessment-summary > div:last-child {
    border-right: none;
}

.summary-label {
    display: block;

    margin-bottom: 7px;

    font-size: 9px;
    font-weight: 800;

    letter-spacing: 1.3px;

    color: #999999;
}

.assessment-summary strong {
    font-size: 14px;

    color: #222222;
}

.dashboard-section-title {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;

    margin-bottom: 22px;
}

.dashboard-section-title h2 {
    margin: 0;

    font-size: 30px;
    letter-spacing: -1px;
}

.view-roadmap-link {
    color: #111111;

    text-decoration: none;

    font-size: 13px;
    font-weight: 700;
}

.view-roadmap-link:hover {
    text-decoration: underline;
}

.current-learning {
    margin-bottom: 40px;
}

.current-stage-card {
    display: grid;

    grid-template-columns: 75px 1fr;

    gap: 25px;

    padding: 32px;

    background: #ffffff;

    border: 1px solid #e5e5e5;
    border-radius: 18px;
}

.current-stage-number {
    width: 58px;
    height: 58px;

    border-radius: 50%;

    background: #111111;
    color: #ffffff;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 18px;
    font-weight: 800;
}

.current-stage-content h3 {
    margin: 0 0 10px;

    font-size: 25px;
    letter-spacing: -.7px;
}

.current-stage-content p {
    max-width: 700px;

    margin: 0 0 20px;

    color: #777777;

    line-height: 1.7;
}

.learning-complete-card {
    display: flex;

    align-items: center;

    gap: 20px;

    padding: 32px;

    background: #ffffff;

    border: 1px solid #e5e5e5;
    border-radius: 18px;
}

.complete-icon {
    width: 52px;
    height: 52px;

    border-radius: 50%;

    background: #111111;
    color: #ffffff;

    display: flex;
    align-items: center;
    justify-content: center;

    font-weight: 800;
}

.learning-complete-card h3 {
    margin: 0 0 5px;
}

.learning-complete-card p {
    margin: 0 0 15px;

    color: #777777;
}

.learning-empty-card {
    padding: 45px 30px;

    background: #ffffff;

    border: 1px solid #e5e5e5;
    border-radius: 18px;
}

.learning-empty-card h3 {
    margin-bottom: 8px;
}

.learning-empty-card p {
    margin-bottom: 20px;

    color: #777777;
}

@media (max-width: 768px) {

.dashboard-content {
        padding-top: 45px;
    }

    .dashboard-heading {
        align-items: flex-start;
        flex-direction: column;

        gap: 25px;
    }

    .dashboard-overview {
        grid-template-columns: 1fr;
    }

    .assessment-summary {
        grid-template-columns: 1fr;
    }

    .assessment-summary > div {
        border-right: none;
        border-bottom: 1px solid #eeeeee;
    }

    .assessment-summary > div:last-child {
        border-bottom: none;
    }

    .dashboard-section-title {
        align-items: flex-start;
        flex-direction: column;

        gap: 12px;
    }

    .current-stage-card {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 600px) {
    .dashboard-content { width: calc(100% - 28px); padding: 24px 0 40px; }
    .dashboard-heading { margin-bottom: 18px; }
    .dashboard-heading h1 { font-size: clamp(28px, 8vw, 34px); letter-spacing: -1px; }
    .dashboard-heading p { font-size: 14px; line-height: 1.5; }
    .dashboard-overview { gap: 12px; margin-bottom: 16px; }
    .overview-main, .overview-progress { padding: 18px; border-radius: 14px; }
    .overview-main h2 { font-size: 21px; }
    .dashboard-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    .dashboard-stat-card:last-child { grid-column: 1 / -1; }
    .dashboard-stat-card { gap: 6px; padding: 13px; border-radius: 13px; }
    .dashboard-stat-card strong { font-size: 23px; }
    .dashboard-stat-card > span:last-child { font-size: 12px; }
    .dashboard-stat-icon { flex-basis: 36px; width: 36px; height: 36px; font-size: 18px; }
    .dashboard-learning-stats, .current-learning { margin-bottom: 24px; }
    .dashboard-learning-stats .dashboard-section-title { margin-bottom: 10px; }
    .dashboard-section-title h2 { font-size: 24px; letter-spacing: -.5px; }
    .assessment-summary { margin-bottom: 24px; }
    .assessment-summary > div { padding: 13px 16px; }
    .current-stage-card { grid-template-columns: 42px minmax(0, 1fr); gap: 12px; padding: 16px; border-radius: 14px; }
    .current-stage-number { width: 40px; height: 40px; font-size: 15px; }
    .current-stage-content h3 { font-size: 19px; line-height: 1.35; }
    .current-stage-content p { margin-bottom: 14px; font-size: 14px; line-height: 1.55; }
    .learning-complete-card, .learning-empty-card { gap: 14px; padding: 20px; border-radius: 14px; }
}

</style>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
