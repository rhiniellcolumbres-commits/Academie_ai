<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$user = getUserById($pdo, $_SESSION['user_id']);

if (!$user) {
    redirect('./');
}
$assessmentStmt = $pdo->prepare(
    'SELECT *
     FROM assessments
     WHERE user_id = ?
     LIMIT 1'
);

$assessmentStmt->execute([
    $_SESSION['user_id']
]);

$assessment = $assessmentStmt->fetch();

$topics = getStudyTopics($pdo, $_SESSION['user_id']);

$completed = 0;

foreach ($topics as $topic) {
    if ((int)$topic['completed'] === 1) {
        $completed++;
    }
}

$total = count($topics);

$percent = $total
    ? (int)round(($completed / $total) * 100)
    : 0;


/*
|--------------------------------------------------------------------------
| Prepare activity information
|--------------------------------------------------------------------------
*/

foreach ($topics as &$topic) {

    $activities = getActivities($topic['activity'] ?? '');

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
     * Make sure there are always 6 activity states.
     */
   $topic['_activities'] = array_slice($activities, 0, 6);

$activityCount = count($topic['_activities']);

for ($i = 0; $i < $activityCount; $i++) {
    $progress[$i] = !empty($progress[$i]) ? 1 : 0;
}

$topic['_progress'] = $progress;

    $topic['_activity_completed'] = count(
        array_filter(
            $topic['_progress'],
            // Treat numeric 1 as a completed activity flag.
            fn($value) => (int)$value === 1
        )
    );
}

unset($topic);


/*
|--------------------------------------------------------------------------
| Determine which stages are unlocked
|--------------------------------------------------------------------------
*/

$previousCompleted = true;

foreach ($topics as $index => &$topic) {

    $topic['_is_completed'] =
        (int)$topic['completed'] === 1;

    $topic['_is_read'] =
        (int)($topic['is_read'] ?? 0) === 1;

    /*
     * First stage is unlocked.
     * Every following stage requires the previous
     * stage to be completed.
     */
    $topic['_is_unlocked'] = $previousCompleted;

    /*
     * Once this stage is not completed,
     * the next stage stays locked.
     */
    $previousCompleted = $topic['_is_completed'];
}

unset($topic);

/*
|--------------------------------------------------------------------------
| Stage selection
|--------------------------------------------------------------------------
*/
$selectedStage = null;

if (isset($_GET['stage']) && ctype_digit((string)$_GET['stage'])) {
    $selectedStage = (int)$_GET['stage'];
}

if ($selectedStage !== null) {
    $selectedTopic = null;

    foreach ($topics as $topic) {
        if ((int)$topic['order_number'] === $selectedStage) {
            $selectedTopic = $topic;
            break;
        }
    }

    if (!$selectedTopic || !$selectedTopic['_is_unlocked']) {
        redirect('roadmap');
    }
}

$pageTitle = 'Roadmap';
$pageCss = 'css/studypath.css';

require_once __DIR__ . '/includes/header.php';

?>

<div class="roadmap-page">

    <!-- =====================================================
         NAVBAR
    ====================================================== -->

    <?php require __DIR__ . '/includes/navbar.php'; ?>


    <main class="roadmap-container">

        <!-- =================================================
             HEADER
        ================================================== -->

        <section class="roadmap-header">

            <div>

                <span class="eyebrow">
                    PERSONALIZED ROADMAP
                </span>

                <h1>
                    Your Learning Roadmap
                </h1>

                <p>
                    Follow your personalized learning path step by step.
                    Read each stage, complete its activities, and unlock
                    the next stage as you progress.
                </p>

            </div>

        </section>


        <?php if (empty($topics)): ?>

            <!-- =================================================
                 EMPTY STATE
            ================================================== -->

            <section class="roadmap-empty">

                <div class="empty-icon">
                    +
                </div>

                <h2>
                    Your roadmap isn't ready yet
                </h2>

                <p>
                    Complete your assessment so StudyPath AI can create
                    your personalized learning path.
                </p>

                <a
                    href="assessment"
                    class="roadmap-primary-btn"
                >
                    Take Assessment →
                </a>

            </section>

        <?php else: ?>


            <!-- =================================================
                 SUMMARY
            ================================================== -->

            <section class="roadmap-summary">

                <div class="summary-career">

                    <span class="summary-label">
                        TARGET CAREER
                    </span>

                    <h2>
                        <?= e($assessment['career_goal'] ?? 'Not specified') ?>
                    </h2>

                    <p>

                        <?php if (!empty($assessment['current_level'])): ?>

                             <?= e($assessment['current_level']) ?>

                        <?php endif; ?>

                        <?php if (!empty($assessment['improvement_goal'])): ?>

                            · <?= e($assessment['improvement_goal']) ?>

                        <?php endif; ?>

                    </p>

                    <small class="roadmap-source-badge">
                        <?= ($topics[0]['roadmap_source'] ?? 'fallback') === 'gemini' ? '<i class="bi bi-stars" aria-hidden="true"></i> Gemini personalized' : 'Standard personalized roadmap' ?>
                    </small>

                    <div class="mt-3">
                        <a
                            href="assessment?retake=1"
                            class="assessment-retake-link"
                            onclick="return confirm('Retake your assessment? Generating a new roadmap will replace your current roadmap and reset its stage and activity progress. This cannot be undone.');"
                        >
                            Retake Assessment
                        </a>
                    </div>

                </div>


                <div class="summary-progress">

                    <div class="progress-top">

                        <span>
                            OVERALL PROGRESS
                        </span>

                        <strong>
                            <?= $percent ?>%
                        </strong>

                    </div>

                    <div class="roadmap-progress-bar">

                        <span
                            style="width: <?= $percent ?>%;"
                        ></span>

                    </div>

                    <small>
                        <?= $completed ?> of <?= $total ?>
                        stages completed
                    </small>

                </div>

            </section>


            <!-- =================================================
                 LEARNING PATH
            ================================================== -->

            <section class="roadmap-section">

                <div class="roadmap-section-header">

                    <div>

                        <span class="eyebrow">
                            LEARNING PATH
                        </span>

                        <h2>
                            Follow the stages
                        </h2>

                        <p>
                            Complete each stage before moving to the
                            next part of your learning journey.
                        </p>

                    </div>

                </div>


                <?php if ($selectedStage === null): ?>

                    <!-- ROADMAP OVERVIEW -->
                    <div class="roadmap-timeline">

                        <?php foreach ($topics as $topic): ?>
                            <?php
                            $stageNumber = (int)$topic['order_number'];
                            $isCompleted = $topic['_is_completed'];
                            $isUnlocked = $topic['_is_unlocked'];
                            ?>

                            <article class="roadmap-stage <?= $isCompleted ? 'completed' : '' ?> <?= !$isUnlocked ? 'locked' : '' ?>">
                                <div class="stage-marker">
                                    <?php if ($isCompleted): ?><i class="bi bi-check-lg" aria-hidden="true"></i><?php elseif (!$isUnlocked): ?><i class="bi bi-lock-fill" aria-hidden="true"></i><?php else: ?><?= $stageNumber ?><?php endif; ?>
                                </div>
                                <div class="stage-content">
                                    <div class="stage-top">
                                        <span class="stage-category"><?= e($topic['category'] ?: ('Stage ' . $stageNumber)) ?></span>
                                        <?php if ($isCompleted): ?>
                                            <span class="stage-status"><i class="bi bi-trophy" aria-hidden="true"></i> Achievement unlocked</span>
                                        <?php elseif (!$isUnlocked): ?>
                                            <span class="stage-status">Locked</span>
                                        <?php else: ?>
                                            <span class="stage-status">Available</span>
                                        <?php endif; ?>
                                    </div>
                                    <h2><?= e($topic['title']) ?></h2>
                                    <p class="stage-description"><?= e($topic['description']) ?></p>
                                    <?php if (!empty($topic['expected_output'])): ?>
                                        <p class="stage-hint"><strong>Achievement:</strong> <?= e($topic['expected_output']) ?></p>
                                    <?php endif; ?>
                                    <?php if ($isUnlocked): ?>
                                        <a href="roadmap?stage=<?= $stageNumber ?>" class="read-stage-btn">
                                            <?= $isCompleted ? 'Review Stage →' : 'Open Stage →' ?>
                                        </a>
                                    <?php else: ?>
                                        <div class="stage-hint">Complete Stage <?= $stageNumber - 1 ?> first to unlock this stage.</div>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                <!-- SINGLE STAGE VIEW -->
                <div style="margin-bottom: 18px;">
                    <a href="roadmap" class="read-stage-btn">← Back to Roadmap</a>
                </div>

                <div class="roadmap-timeline">

                    <?php foreach ($topics as $index => $topic): ?>

                        <?php if ((int)$topic['order_number'] !== $selectedStage) continue; ?>

                        <?php

                        $stageNumber =
                            (int)$topic['order_number'];

                        $isCompleted =
                            $topic['_is_completed'];

                        $isRead =
                            $topic['_is_read'];

                        $isUnlocked =
                            $topic['_is_unlocked'];

                        $activityCount =
                            $topic['_activity_completed'];

                        $activities =
                            $topic['_activities'];

                        $activityProgress =
                            $topic['_progress'];

                        $activityPercent =
                            count($activities)
                            ? (int)round(
                                ($activityCount / count($activities))
                                * 100
                            )
                            : 0;

                        ?>

                        <article
                            class="
                                roadmap-stage

                                <?= $isCompleted
                                    ? 'completed'
                                    : ''
                                ?>

                                <?= !$isUnlocked
                                    ? 'locked'
                                    : ''
                                ?>

                                <?= $isUnlocked && !$isCompleted
                                    ? 'current'
                                    : ''
                                ?>
                            "
                        >


                            <!-- =================================================
                                 STAGE MARKER
                            ================================================== -->

                            <div class="stage-marker">

                                <?php if ($isCompleted): ?>

                                    <i class="bi bi-check-lg" aria-hidden="true"></i>

                                <?php elseif (!$isUnlocked): ?>

                                    <i class="bi bi-lock-fill" aria-hidden="true"></i>

                                <?php else: ?>

                                    <?= $stageNumber ?>

                                <?php endif; ?>

                            </div>


                            <!-- =================================================
                                 STAGE CONTENT
                            ================================================== -->

                            <div class="stage-content">


                                <!-- TOP -->

                                <div class="stage-top">

                                    <span class="stage-category">

                                        <?= e($topic['category']) ?>

                                    </span>


                                    <?php if ($isCompleted): ?>

                                        <span
                                            class="
                                                stage-status
                                                completed-status
                                            "
                                        >
                                            ✓ Completed
                                        </span>

                                    <?php elseif (!$isUnlocked): ?>

                                        <span
                                            class="
                                                stage-status
                                                locked-status
                                            "
                                        >
                                            <i class="bi bi-lock-fill" aria-hidden="true"></i> Locked
                                        </span>

                                    <?php else: ?>

                                        <span class="stage-status">

                                            Stage
                                            <?= $stageNumber ?>
                                            /
                                            <?= $total ?>

                                        </span>

                                    <?php endif; ?>

                                </div>


                                <!-- TITLE -->

                                <h2>

                                    <?= e($topic['title']) ?>

                                </h2>


                                <!-- DESCRIPTION -->

                                <p class="stage-description">

                                    <?= e($topic['description']) ?>

                                </p>

                                <?php if (!empty($topic['ai_why'])): ?>
                                    <div class="ai-why-box">
                                        <div class="stage-box-title"><i class="bi bi-stars" aria-hidden="true"></i> Why StudyPath AI recommended this</div>
                                        <p><?= e($topic['ai_why']) ?></p>
                                    </div>
                                <?php endif; ?>


                                <?php if (!$isUnlocked): ?>

                                    <!-- =============================================
                                         LOCKED MESSAGE
                                    ============================================== -->

                                    <div class="locked-message">

                                        <div class="locked-icon">
                                            <i class="bi bi-lock-fill" aria-hidden="true"></i>
                                        </div>

                                        <div>

                                            <strong>
                                                Stage Locked
                                            </strong>

                                            <p>
                                                Complete Stage
                                                <?= $stageNumber - 1 ?>
                                                first to unlock this stage.
                                            </p>

                                        </div>

                                    </div>


                                <?php else: ?>


                                    <!-- =============================================
                                         WHAT YOU'LL LEARN
                                    ============================================== -->

                                    <div class="stage-box">

                                        <div class="stage-box-title">

                                            <span>▣</span>

                                            What You'll Learn

                                        </div>


                                        <div class="topic-tags">

                                            <?php foreach (
                                                split_list(
                                                    $topic['topics']
                                                ) as $item
                                            ): ?>

                                                <span>
                                                    <?= e($item) ?>
                                                </span>

                                            <?php endforeach; ?>

                                        </div>

                                    </div>


                                    <!-- =============================================
                                         READ STAGE
                                    ============================================== -->

                                    <?php if (!$isRead && !$isCompleted): ?>

                                        <div class="read-stage-box">

                                            <div>

                                                <strong>
                                                    Ready to start?
                                                </strong>

                                                <p>
                                                    Read this stage before
                                                    starting the activities.
                                                </p>

                                            </div>


                                            <form
                                                action="api/study-path.php"
                                                method="POST"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="read"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="topic_id"
                                                    value="<?= (int)$topic['id'] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="read-stage-btn"
                                                >
                                                    Start Stage →
                                                </button>

                                            </form>

                                        </div>

                                    <?php endif; ?>


                                    <!-- =============================================
                                         SIX ACTIVITIES
                                    ============================================== -->

                                    <?php if (
                                        $isRead ||
                                        $isCompleted
                                    ): ?>

                                        <div
                                            class="
                                                stage-box
                                                activities-container
                                            "
                                        >

                                            <div
                                                class="
                                                    activities-header
                                                "
                                            >

                                                <div>

                                                    <div
                                                        class="
                                                            stage-box-title
                                                        "
                                                    >

                                                        <span>◆</span>

                                                        Learning Activities

                                                    </div>

                                                    <p
                                                        class="
                                                            activities-subtitle
                                                        "
                                                    >
                                                        Complete all 6 activities
                                                        to finish this stage.
                                                    </p>

                                                </div>


                                                <div
                                                    class="
                                                        activity-count
                                                    "
                                                >

                                                    <?= $activityCount ?>
                                                    /
                                                    <?= count($activities) ?>

                                                </div>

                                            </div>


                                            <!-- ACTIVITY PROGRESS -->

                                            <div
                                                class="
                                                    activity-progress-container
                                                "
                                            >

                                                <div
                                                    class="
                                                        activity-progress-bar
                                                    "
                                                >

                                                    <span
                                                        style="
                                                            width:
                                                            <?= $activityPercent ?>%;
                                                        "
                                                    ></span>

                                                </div>

                                                <small>

                                                    <?= $activityPercent ?>%
                                                    complete

                                                </small>

                                            </div>


                                            <!-- ACTIVITY LIST -->

                                            <div class="activity-list">

    <?php foreach (
        $activities
        as $activityIndex => $activity
    ): ?>

        <?php

        $checked =
            !empty(
                $activityProgress[$activityIndex]
            );

        ?>

        <div class="activity-row">

            <!-- COMPLETE ACTIVITY -->

            <form
                action="api/study-path.php"
                method="POST"
                class="activity-form"
            >

                <input
                    type="hidden"
                    name="action"
                    value="complete_activity"
                >

                <input
                    type="hidden"
                    name="topic_id"
                    value="<?= (int)$topic['id'] ?>"
                >

                <input
                    type="hidden"
                    name="activity_index"
                    value="<?= $activityIndex ?>"
                >

                <button
                    type="submit"
                    class="
                        activity-item
                        <?= $checked
                            ? 'activity-completed'
                            : ''
                        ?>
                    "
                    <?= $isCompleted
                        ? 'disabled'
                        : ''
                    ?>
                >

                    <span class="activity-checkbox">

                        <?php if ($checked): ?>

                            ✓

                        <?php endif; ?>

                    </span>

                    <span class="activity-number">

                        <?= $activityIndex + 1 ?>

                    </span>

                    <span class="activity-text">

                        <?= e($activity) ?>

                    </span>

                </button>

            </form>


            <!-- LEARNING GUIDE BUTTON -->

                <button
                    type="button"
                    class="learn-activity-btn"
                    data-topic-id="<?= (int)$topic['id'] ?>"
                    data-activity-index="<?= (int)$activityIndex ?>"
                    data-activity-title="<?= e($activity) ?>"
                    onclick="openLesson(
                        <?= (int)$topic['id'] ?>,
                        <?= $activityIndex ?>,
                        <?= htmlspecialchars(
                            json_encode($activity),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    )"
                >
                    <i class="bi bi-book-half" aria-hidden="true"></i> Learn This
                </button>

        </div>

    <?php endforeach; ?>

</div>

                                        </div>

                                    <?php endif; ?>


                                    <!-- =============================================
                                         EXPECTED OUTPUT
                                    ============================================== -->

                                    <?php if (
                                        !empty($topic['expected_output'])
                                    ): ?>

                                        <div
                                            class="
                                                stage-box
                                                output-box
                                            "
                                        >

                                            <div
                                                class="
                                                    stage-box-title
                                                "
                                            >

                                                <span>✓</span>

                                                Expected Output

                                            </div>


                                            <p>

                                                <?= e(
                                                    $topic['expected_output']
                                                ) ?>

                                            </p>

                                        </div>

                                    <?php endif; ?>


                                    <!-- =============================================
                                         RESOURCES
                                    ============================================== -->

                                    <?php if (
                                        !empty($topic['resources'])
                                    ): ?>

                                        <div
                                            class="
                                                stage-box
                                                resources-box
                                            "
                                        >

                                            <div
                                                class="
                                                    stage-box-title
                                                "
                                            >

                                                <span>↗</span>

                                                Recommended Resources

                                            </div>


                                            <div
                                                class="
                                                    resource-list
                                                "
                                            >

                                                <?php foreach (
                                                    split_list(
                                                        $topic['resources']
                                                    ) as $resource
                                                ): ?>

                                                    <div
                                                        class="
                                                            resource-item
                                                        "
                                                    >

                                                        <span
                                                            class="
                                                                resource-dot
                                                            "
                                                        >
                                                            •
                                                        </span>

                                                        <span>
                                                            <?= e($resource) ?>
                                                        </span>

                                                    </div>

                                                <?php endforeach; ?>

                                            </div>

                                        </div>

                                    <?php endif; ?>


                                    <!-- =============================================
                                         COMPLETION STATUS
                                    ============================================== -->

                                    <?php if ($isCompleted): ?>

                                        <div
                                            class="
                                                stage-complete-message
                                            "
                                        >

                                            <span>
                                                <i class="bi bi-check-lg" aria-hidden="true"></i>
                                            </span>

                                            <div>

                                                <strong>
                                                    Stage Completed
                                                </strong>

                                                <p>
                                                    Great job! The next
                                                    stage is now unlocked.
                                                </p>

                                            </div>

                                        </div>

                                    <?php elseif ($isRead): ?>

                                        <?php if (
                                            $activityCount < count($activities)
                                        ): ?>

                                            <div
                                                class="
                                                    stage-hint
                                                "
                                            >

                                                Complete all
                                                <?= count($activities) ?>
                                                activities above to
                                                complete this stage.

                                            </div>

                                        <?php endif; ?>

                                    <?php endif; ?>


                                <?php endif; ?>

                            </div>

                        </article>

                    <?php endforeach; ?>

                    <?php if ($selectedStage !== null && $selectedTopic['_is_completed']): ?>
                        <?php $nextStage = $selectedStage + 1; ?>
                        <?php foreach ($topics as $nextTopic): ?>
                            <?php if ((int)$nextTopic['order_number'] === $nextStage): ?>
                                <div style="margin-top: 24px;">
                                    <a href="roadmap?stage=<?= $nextStage ?>" class="read-stage-btn">
                                        Next: Stage <?= $nextStage ?> →
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>

                <?php endif; ?>

            </section>

        <?php endif; ?>

    </main>

</div>

<!-- =====================================================
     AI LEARNING GUIDE
====================================================== -->

<div
    id="lessonModal"
    class="lesson-modal"
    style="display: none;"
>

    <div class="lesson-modal-content">

        <button
            type="button"
            class="lesson-close"
            onclick="closeLesson()"
        >
            ×
        </button>


        <div id="lessonLoading">

            <div class="lesson-loading-icon">
                <i class="bi bi-stars" aria-hidden="true"></i>
            </div>

            <h2>
                <span class="brand-name"><span class="brand-acad">Acad</span><span class="brand-amie">emie</span></span> is preparing your lesson...
            </h2>

            <p>
                This may take a few seconds.
            </p>

        </div>


        <div
            id="lessonContent"
            style="display: none;"
        >   

            <span class="eyebrow">
                AI LEARNING GUIDE
            </span>

            <h2 id="lessonTitle">
                Learn This
            </h2>


            <!-- LESSON -->

            <section class="lesson-section">

                <h3>
                    <i class="bi bi-book-half" aria-hidden="true"></i> Learn
                </h3>

                <p id="lessonText"></p>

            </section>


            <!-- EXAMPLE -->

            <section class="lesson-section">

                <h3>
                    <i class="bi bi-lightbulb" aria-hidden="true"></i> Example
                </h3>

                <pre id="lessonExample"></pre>

            </section>


            <!-- PRACTICE -->

            <section class="lesson-section">

                <h3>
                    <i class="bi bi-bullseye" aria-hidden="true"></i> Practice
                </h3>

                <p id="lessonPractice"></p>

            </section>


            <!-- CHECKLIST -->

            <section class="lesson-section">

                <h3>
                    <i class="bi bi-check2-square" aria-hidden="true"></i> Check Yourself
                </h3>

                <ul id="lessonChecklist"></ul>

            </section>


            <!-- RESOURCES -->

            <section class="lesson-section">

                <h3>
                    <i class="bi bi-journals" aria-hidden="true"></i> Resources
                </h3>

                <ul id="lessonResources"></ul>

            </section>

            <!-- MARK AS LEARNED -->

<div class="lesson-complete-action">

    <button
        type="button"
        id="markLearnedBtn"
        class="mark-learned-btn"
    >
        ✓ Mark as Learned
    </button>

    <button type="button" id="nextLessonBtn" class="next-lesson-btn" hidden>
        Next Lesson <span aria-hidden="true">→</span>
    </button>

</div>

        </div>


        <div
            id="lessonError"
            class="lesson-error"
            style="display: none;"
        ></div>

    </div>

</div>

<script>

// Fetch and display the AI lesson for the selected activity in the lesson modal.
async function openLesson(topicId, activityIndex, activity) {
    const modal = document.getElementById('lessonModal');
    const loading = document.getElementById('lessonLoading');
    const content = document.getElementById('lessonContent');
    const error = document.getElementById('lessonError');
    const markLearnedBtn = document.getElementById('markLearnedBtn');
    const nextLessonBtn = document.getElementById('nextLessonBtn');

    markLearnedBtn.dataset.topicId = String(topicId);
    markLearnedBtn.dataset.activityIndex = String(activityIndex);

    const lessonButtons = Array.from(document.querySelectorAll('.learn-activity-btn'));
    const currentLessonIndex = lessonButtons.findIndex(function (lessonButton) {
        return lessonButton.dataset.topicId === String(topicId)
            && lessonButton.dataset.activityIndex === String(activityIndex);
    });
    const followingLesson = currentLessonIndex >= 0 ? lessonButtons[currentLessonIndex + 1] : null;
    nextLessonBtn.hidden = !followingLesson;
    if (followingLesson) {
        nextLessonBtn.dataset.topicId = followingLesson.dataset.topicId;
        nextLessonBtn.dataset.activityIndex = followingLesson.dataset.activityIndex;
        nextLessonBtn.dataset.activityTitle = followingLesson.dataset.activityTitle;
    }

    let alreadyLearned = false;

    // Find out whether this activity is already marked learned before showing the button.
    document.querySelectorAll('.activity-form').forEach(function (form) {
        const formTopicId = form.querySelector('input[name="topic_id"]');
        const formActivityIndex = form.querySelector('input[name="activity_index"]');
        const activityButton = form.querySelector('.activity-item');

        if (
            formTopicId &&
            formActivityIndex &&
            activityButton &&
            formTopicId.value === String(topicId) &&
            formActivityIndex.value === String(activityIndex)
        ) {
            alreadyLearned = activityButton.classList.contains('activity-completed');
        }
    });

    markLearnedBtn.disabled = alreadyLearned;
    markLearnedBtn.classList.toggle('learned', alreadyLearned);
    markLearnedBtn.textContent = alreadyLearned
        ? '✓ Already Learned'
        : '✓ Mark as Learned';

    modal.style.display = 'flex';
    loading.style.display = 'block';
    content.style.display = 'none';
    error.style.display = 'none';

    try {
        const formData = new FormData();
        formData.append('topic_id', topicId);
        formData.append('activity_index', activityIndex);

        const response = await fetch('api/ai-lesson.php', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' }
        });

        const responseText = await response.text();
        let data;

        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('AI lesson endpoint returned a non-JSON response:', response.status, responseText);
            throw new Error(
                response.status >= 500
                    ? 'The server could not generate the lesson. Check the PHP error log and Gemini API configuration, then try again.'
                    : 'The lesson response was invalid. Please try again.'
            );
        }

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Unable to generate lesson.');
        }

        document.getElementById('lessonTitle').textContent = activity;
        document.getElementById('lessonText').textContent = data.lesson || '';
        document.getElementById('lessonExample').textContent = data.example || '';
        document.getElementById('lessonPractice').textContent = data.practice || '';

        const checklist = document.getElementById('lessonChecklist');
        checklist.innerHTML = '';

        // Render checklist entries as text so returned content is not treated as HTML.
        (Array.isArray(data.checklist) ? data.checklist : []).forEach(function (item) {
            const li = document.createElement('li');
            li.textContent = item;
            checklist.appendChild(li);
        });

        const resources = document.getElementById('lessonResources');
        resources.innerHTML = '';

        // Render resource names as a list beneath the lesson.
        (Array.isArray(data.resources) ? data.resources : []).forEach(function (item) {
            const li = document.createElement('li');
            li.textContent = item;
            resources.appendChild(li);
        });

        loading.style.display = 'none';
        content.style.display = 'block';
    } catch (err) {
        loading.style.display = 'none';
        error.style.display = 'block';
        error.textContent = err.message;
    }
}

// Save the current lesson's completion and update the modal button's state.
async function markLessonAsLearned() {
    const button = document.getElementById('markLearnedBtn');
    const topicId = button.dataset.topicId;
    const activityIndex = button.dataset.activityIndex;

    if (!topicId || activityIndex === '') {
        return;
    }

    button.disabled = true;
    button.classList.add('is-saving');
    button.classList.remove('learned');
    button.textContent = 'Saving...';

    try {
        const formData = new FormData();
        formData.append('action', 'complete_activity');
        formData.append('topic_id', topicId);
        formData.append('activity_index', activityIndex);

        const response = await fetch('api/study-path.php', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' }
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Unable to mark activity as learned.');
        }

        updateActivityUI(data.activity_progress || [], topicId);

        button.classList.remove('is-saving');
        button.classList.add('learned');
        button.textContent = '✓ Learned';
        button.disabled = true;

        if (data.completed) {
            // Reload after a short pause so the student can see the completion state.
            setTimeout(function () {
                window.location.reload();
            }, 500);
        }
    } catch (error) {
        alert(error.message);
        button.classList.remove('is-saving');
        button.disabled = false;
        button.textContent = '✓ Mark as Learned';
    }
}

// Refresh activity checkmarks and progress indicators for one roadmap stage.
function updateActivityUI(progress, topicId) {
    // Update each activity button that belongs to the stage just saved.
    document.querySelectorAll('.activity-form').forEach(function (form) {
        const formTopicId = form.querySelector('input[name="topic_id"]');
        const formActivityIndex = form.querySelector('input[name="activity_index"]');
        const item = form.querySelector('.activity-item');
        const checkbox = form.querySelector('.activity-checkbox');

        if (!formTopicId || !formActivityIndex || !item || !checkbox) {
            return;
        }

        if (formTopicId.value !== String(topicId)) {
            return;
        }

        const index = Number(formActivityIndex.value);
        const learned = Number(progress[index]) === 1;

        item.disabled = learned;
        item.classList.toggle('activity-completed', learned);
        checkbox.textContent = learned ? '✓' : '';
    });

    const activitiesContainer = document.querySelector('.activities-container');

    if (!activitiesContainer) {
        return;
    }

    // Count completed activities to calculate the progress percentage.
    const completedCount = progress.filter(function (value) {
        return Number(value) === 1;
    }).length;

    const count = activitiesContainer.querySelector('.activity-count');
    if (count) {
        count.textContent = completedCount + ' / ' + progress.length;
    }

    const percent = progress.length
        ? Math.round((completedCount / progress.length) * 100)
        : 0;

    const progressBar = activitiesContainer.querySelector('.activity-progress-bar span');
    if (progressBar) {
        progressBar.style.width = percent + '%';
    }

    const progressText = activitiesContainer.querySelector('.activity-progress-container small');
    if (progressText) {
        progressText.textContent = percent + '% complete';
    }
}

// Hide the lesson dialog when the student closes it.
function closeLesson() {
    document.getElementById('lessonModal').style.display = 'none';
}

// Attach save handlers once activity forms are available in the page.
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.activity-form').forEach(function (form) {
        // Submit progress in the background so the page does not navigate away.
        form.addEventListener('submit', async function (event) {
            event.preventDefault();

            const button = form.querySelector('.activity-item');
            if (!button || button.disabled) {
                return;
            }

            button.disabled = true;
            button.classList.add('activity-saving');

            let data = null;

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { 'Accept': 'application/json' }
                });

                const text = await response.text();

                try {
                    data = JSON.parse(text);
                } catch (parseError) {
                    throw new Error('Unable to save activity progress.');
                }

                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Unable to save activity progress.');
                }

                const topicIdInput = form.querySelector('input[name="topic_id"]');
                const activityIndexInput = form.querySelector('input[name="activity_index"]');

                if (topicIdInput && activityIndexInput) {
                    updateActivityUI(
                        Array.isArray(data.activity_progress) ? data.activity_progress : [],
                        topicIdInput.value
                    );
                }

                if (data.completed) {
                    // Reload shortly after the final activity is saved.
                    setTimeout(function () {
                        window.location.reload();
                    }, 500);
                }
            } catch (error) {
                alert(error.message);
                button.disabled = false;
            } finally {
                button.classList.remove('activity-saving');
            }
        });
    });

    const markLearnedBtn = document.getElementById('markLearnedBtn');

    if (markLearnedBtn) {
        markLearnedBtn.addEventListener('click', markLessonAsLearned);
    }

    const nextLessonBtn = document.getElementById('nextLessonBtn');
    if (nextLessonBtn) {
        nextLessonBtn.addEventListener('click', function () {
            if (this.dataset.topicId && this.dataset.activityIndex && this.dataset.activityTitle) {
                openLesson(this.dataset.topicId, this.dataset.activityIndex, this.dataset.activityTitle);
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
