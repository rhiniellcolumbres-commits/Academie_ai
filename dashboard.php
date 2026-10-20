<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';


/*
|--------------------------------------------------------------------------
| Get User
|--------------------------------------------------------------------------
*/

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
/*
|--------------------------------------------------------------------------
| Get Assessment
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Get Roadmap
|--------------------------------------------------------------------------
*/

$topics = getStudyTopics(
    $pdo,
    $user['id']
);

// Count finished stages / activities (helper lives in includes/functions.php)
$progress = getRoadmapProgress($topics);

$total               = $progress['totalStages'];
$done                = $progress['completedStages'];
$totalActivities     = $progress['totalActivities'];
$completedActivities = $progress['completedActivities'];

$percent = $total > 0
    ? (int)round(($done / $total) * 100)
    : 0;

// Save any newly earned badges, then count them for the stats card.
syncAchievements($pdo, (int)$user['id'], $progress);
$earnedAchievementCount = count(getUserAchievements($pdo, (int)$user['id']));


/*
|--------------------------------------------------------------------------
| Find Current Stage
|--------------------------------------------------------------------------
*/

$currentStage = null;

foreach ($topics as $topic) {

    if ((int)$topic['completed'] === 0) {

        $currentStage = $topic;

        break;
    }

}


/*
|--------------------------------------------------------------------------
| Full Name
|--------------------------------------------------------------------------
*/

$fullName = trim(
    $user['first_name'] . ' ' . $user['last_name']
);
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
    <!-- Bootstrap Icons are required by the navbar and Learning Statistics icons. -->
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


<!-- =========================================================
     NAVBAR
========================================================= -->

<?php require __DIR__ . '/includes/navbar.php'; ?>


<!-- =========================================================
     MAIN CONTENT
========================================================= -->

<main class="dashboard-content">


    <!-- =====================================================
         HEADER
    ====================================================== -->

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

    <!-- =====================================================
         SUCCESS MESSAGE
    ====================================================== -->

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


    <!-- =====================================================
         CAREER + PROGRESS
    ====================================================== -->

    <section class="dashboard-overview">


        <!-- CAREER -->

        <div class="overview-main">

            <span class="card-label">
                YOUR RECOMMENDED SPECIALIZATION
            </span>


            <?php if (!empty($assessment['career_goal'])): ?>

                <h2>
                    <?= e($assessment['career_goal']) ?>
                </h2>

                <p>
                    Your roadmap is designed to help you
                    progress toward becoming a
                    <?= e($assessment['career_goal']) ?>
                </p>


                <?php if (!empty($alternativeRecommendations)): ?>
                    <p class="text-muted small mt-3 mb-2">
                        Other paths you may also explore:
                        <?= e(implode(' · ', $alternativeRecommendations)) ?>
                    </p>
                <?php endif; ?>

                <div class="dashboard-learning-actions">
                    <a
                        href="roadmap"
                        class="dashboard-primary-btn"
                    >
                        Continue Learning →
                    </a>
                    <a
                        href="assessment?retake=1"
                        class="assessment-retake-link"
                        onclick="return confirm('Retake your assessment? Generating a new roadmap will replace your current roadmap and reset its stage and activity progress. This cannot be undone.');"
                    >
                        Retake Assessment
                    </a>
                </div>

            <?php else: ?>

                <h2>
                    Start Your Learning Journey
                </h2>

                <p>
                    Complete your assessment to create
                    your personalized learning roadmap.
                </p>

                <a
                    href="assessment"
                    class="dashboard-primary-btn"
                >
                    Take Assessment →
                </a>

            <?php endif; ?>

        </div>


        <!-- PROGRESS -->

        <div class="overview-progress">

            <span class="card-label">
                OVERALL PROGRESS
            </span>


            <div class="progress-number">
                <?= $percent ?>%
            </div>


            <div class="dashboard-progress-bar">

                <span
                    style="width: <?= $percent ?>%;"
                ></span>

            </div>


            <p>

                <?php if ($total > 0): ?>

                    <?= $done ?>
                    of
                    <?= $total ?>
                    stages completed.

                <?php else: ?>

                    Complete your assessment to begin.

                <?php endif; ?>

            </p>

        </div>

    </section>


    <!-- =====================================================
         LEARNING STATISTICS
    ====================================================== -->

    <section class="dashboard-learning-stats" aria-labelledby="learning-stats-title">
        <div class="dashboard-section-title">
            <div>
                <span class="eyebrow">YOUR PROGRESS</span>
                <h2 id="learning-stats-title">Learning Statistics</h2>
            </div>
        </div>

        <div class="dashboard-stat-grid">
            <article class="dashboard-stat-card">
                <span class="dashboard-stat-icon stat-icon-stages" aria-hidden="true"><i class="bi bi-check2-circle"></i></span>
                <strong><?= $done ?></strong>
                <span>Stages completed</span>
            </article>
            <article class="dashboard-stat-card">
                <span class="dashboard-stat-icon stat-icon-activities" aria-hidden="true"><i class="bi bi-list-check"></i></span>
                <strong><?= $completedActivities ?> / <?= $totalActivities ?></strong>
                <span>Activities completed</span>
            </article>
            <article class="dashboard-stat-card">
                <span class="dashboard-stat-icon stat-icon-achievements" aria-hidden="true"><i class="bi bi-trophy"></i></span>
                <strong><?= $earnedAchievementCount ?></strong>
                <span>Achievements earned</span>
            </article>
        </div>
    </section>


    <!-- =====================================================
         LEARNING PROFILE
    ====================================================== -->

    <?php if (!empty($assessment['career_goal'])): ?>

        <section class="assessment-summary">


            <!-- CURRENT LEVEL -->

            <div>

                <span class="summary-label">
                    CURRENT LEVEL
                </span>

                <strong>

                 <?= e(
                 $assessment['current_level']
                 ?? 'Not specified'
                ) ?>

                </strong>

            </div>


            <!-- LEARNING GOAL -->

            <div>

                <span class="summary-label">
                    LEARNING GOAL
                </span>

                <strong>

                    <?= e(
                        $assessment['improvement_goal']
                        ?? 'Not specified'
                    ) ?>

                </strong>

            </div>


            <!-- CAREER -->

            <div>

                <span class="summary-label">
                    TARGET CAREER
                </span>

                <strong>
                    <?= e($assessment['career_goal']) ?>
                </strong>

            </div>

        </section>

    <?php endif; ?>


    <!-- =====================================================
         CURRENT LEARNING
    ====================================================== -->

    <section class="current-learning">


        <div class="dashboard-section-title">

            <div>

                <span class="eyebrow">
                    CONTINUE LEARNING
                </span>

                <h2>
                    What should I study next?
                </h2>

            </div>


            <?php if (!empty($topics)): ?>

                <a
                    href="roadmap"
                    class="view-roadmap-link"
                >
                    View Full Roadmap →
                </a>

            <?php endif; ?>

        </div>


        <?php if ($currentStage !== null): ?>


            <!-- CURRENT STAGE CARD -->

            <div class="current-stage-card">


                <div class="current-stage-number">

                    <?= (int)$currentStage['order_number'] ?>

                </div>


                <div class="current-stage-content">

                    <span class="card-label">

                        <?= e($currentStage['category']) ?>

                    </span>


                    <h3>

                        <?= e($currentStage['title']) ?>

                    </h3>


                    <p>

                        <?= e($currentStage['description']) ?>

                    </p>
                        <a
                            href="roadmap"
                            class="dashboard-primary-btn"
                        >
                            Learn This →
                        </a>
                </div>

            </div>


        <?php elseif ($total > 0): ?>


            <!-- COMPLETED ROADMAP -->

            <div class="learning-complete-card">

                <div class="complete-icon">
                    ✓
                </div>

                <div>

                    <h3>
                        Roadmap Completed!
                    </h3>

                    <p>
                        Congratulations! You've completed
                        your entire learning roadmap.
                    </p>

                    <a
                        href="roadmap"
                        class="dashboard-primary-btn"
                    >
                        Review Roadmap →
                    </a>

                </div>

            </div>


        <?php else: ?>


            <!-- NO ROADMAP -->

            <div class="learning-empty-card">

                <h3>
                    No learning roadmap yet
                </h3>

                <p>
                    Complete your assessment to generate
                    your personalized roadmap.
                </p>

                <a
                    href="assessment"
                    class="dashboard-primary-btn"
                >
                    Take Assessment →
                </a>

            </div>


        <?php endif; ?>

    </section>


    <!-- =====================================================
         ROADMAP LINK
    ====================================================== -->

    <?php if (!empty($topics)): ?>

        <section class="dashboard-roadmap-banner">

            <div>

                <span class="eyebrow">
                    YOUR PERSONALIZED PATH
                </span>

                <h2>
                    Ready to keep going?
                </h2>

                <p>
                    Open your roadmap to see activities,
                    expected outputs, and recommended resources
                    for every stage.
                </p>

            </div>


            <a
                href="roadmap"
                class="dashboard-primary-btn"
            >
                Open Roadmap →
            </a>

        </section>

    <?php endif; ?>


</main>


<!-- =========================================================
     DASHBOARD PAGE STYLES
========================================================= -->

<style>

.dashboard-page {
    min-height: 100vh;
    background: #f7f7f5;
}


/* =========================================================
   CONTENT
========================================================= */

.dashboard-content {
    width: min(1100px, 92%);
    margin: auto;

    padding: 65px 0 100px;
}


/* =========================================================
   HEADER
========================================================= */

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


/* =========================================================
   USER
========================================================= */

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


/* =========================================================
   SUCCESS
========================================================= */

.dashboard-success {
    padding: 15px 18px;

    margin-bottom: 25px;

    background: #ffffff;

    border: 1px solid #dddddd;
    border-radius: 10px;

    font-size: 13px;
    color: #444444;
}


/* =========================================================
   OVERVIEW
========================================================= */

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


/* =========================================================
   PROGRESS
========================================================= */

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


/* =========================================================
   LEARNING STATISTICS
========================================================= */

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


/* =========================================================
   ASSESSMENT SUMMARY
========================================================= */

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


/* =========================================================
   SECTION TITLE
========================================================= */

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


/* =========================================================
   CURRENT STAGE
========================================================= */

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


/* =========================================================
   COMPLETED
========================================================= */

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


/* =========================================================
   EMPTY
========================================================= */

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

/* =========================================================
   BANNER
========================================================= */

.dashboard-roadmap-banner {
    display: flex;

    align-items: center;
    justify-content: space-between;

    gap: 30px;

    padding: 35px;

    background: #111111;

    border-radius: 18px;

    color: #ffffff;
}

.dashboard-roadmap-banner .eyebrow {
    color: #aaaaaa;
}

.dashboard-roadmap-banner h2 {
    margin: 0 0 8px;

    font-size: 27px;
}

.dashboard-roadmap-banner p {
    max-width: 650px;

    margin: 0;

    color: #bbbbbb;

    line-height: 1.6;
}

.dashboard-roadmap-banner .dashboard-primary-btn {
    background: #ffffff;
    color: #111111;

    white-space: nowrap;
}


/* =========================================================
   MOBILE
========================================================= */

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

    .dashboard-roadmap-banner {
        align-items: flex-start;
        flex-direction: column;
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
    .dashboard-roadmap-banner { gap: 16px; padding: 20px; border-radius: 14px; }
    .dashboard-roadmap-banner h2 { font-size: 22px; }
    .dashboard-roadmap-banner .dashboard-primary-btn { white-space: normal; }
    .learning-complete-card, .learning-empty-card { gap: 14px; padding: 20px; border-radius: 14px; }
}

</style>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
