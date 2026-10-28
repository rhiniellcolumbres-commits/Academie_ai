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
$studyPathId = (int)($topics[0]['study_path_id'] ?? 0);

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

$totalActivities = 0;
$completedActivities = 0;

foreach ($topics as $topicIndex => $topic) {

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

$topic['_activities'] = array_slice($activities, 0, ROADMAP_ACTIVITIES_PER_STAGE);

$activityCount = count($topic['_activities']);
    for ($i = 0; $i < $activityCount; $i++) {
        $progress[$i] = !empty($progress[$i]) ? 1 : 0;
    }

    $topic['_progress'] = $progress;

$topic['_activity_completed'] = 0;
    foreach ($topic['_progress'] as $state) {
        if ((int) $state === 1) {
            $topic['_activity_completed']++;
        }
    }

    $topics[$topicIndex] = $topic;
    
    $totalActivities += count($topic['_activities']);
    $completedActivities += $topic['_activity_completed'];
}

$previousCompleted = true;

foreach ($topics as $index => $topic) {

$topic['_is_completed'] =
        (int)$topic['completed'] === 1;

    $topic['_is_read'] =
        (int)($topic['is_read'] ?? 0) === 1;

$topic['_is_unlocked'] = $previousCompleted;

$previousCompleted = $topic['_is_completed'];

$topics[$index] = $topic;
}

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

<?php require __DIR__ . '/includes/navbar.php'; ?>

<main class="roadmap-container">

<section class="roadmap-header">
    <div class="roadmap-heading-copy">
        <span class="eyebrow">YOUR ROADMAP</span>
        <h1><?= e($assessment['career_goal'] ?? 'Your Learning Roadmap') ?></h1>
        <p>Finish each stage to unlock the next.</p>
    </div>
    <?php if (!empty($topics)): ?>
        <a href="assessment?retake=1" class="roadmap-retake-btn" onclick="return confirm('Retake your assessment? Generating a new roadmap will replace your current roadmap and reset its stage and activity progress. This cannot be undone.');">Retake assessment</a>
    <?php endif; ?>
</section>

<?php if (empty($topics)): ?>

<section class="roadmap-empty">

                <div class="empty-icon">
                    +
                </div>

                <h2>
                    Your roadmap isn't ready yet
                </h2>

                <p>
                    Complete your assessment so Academie can create
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

<section class="roadmap-summary">
    <div class="summary-progress">
        <div class="roadmap-progress-copy">
            <span>Stages completed</span>
            <strong><?= $completed ?> of <?= $total ?> · <?= $percent ?>%</strong>
        </div>
        <div class="roadmap-progress-bar" role="progressbar" aria-label="Roadmap stages completed" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $percent ?>" aria-valuetext="<?= $percent ?> percent, <?= $completed ?> of <?= $total ?> stages completed">
            <span style="width: <?= $percent ?>%;"></span>
        </div>
        <small><?= $completedActivities ?> of <?= $totalActivities ?> activities done</small>
    </div>
</section>

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

<div class="roadmap-timeline">

                        <?php foreach ($topics as $topic): ?>
                            <?php
                            $stageNumber = (int)$topic['order_number'];
                            $isCompleted = $topic['_is_completed'];
                            $isUnlocked = $topic['_is_unlocked'];
                            $isCurrent = $isUnlocked && !$isCompleted;
                            $stageActivityTotal = count($topic['_activities'] ?? []);
                            ?>

                            <details class="roadmap-stage roadmap-stage-accordion <?= $isCompleted ? 'completed' : '' ?> <?= $isCurrent ? 'current' : '' ?> <?= !$isUnlocked ? 'locked' : '' ?>" <?= $isCurrent ? 'open' : '' ?>>
                                <summary class="roadmap-stage-summary">
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
                                            <span class="stage-status stage-current-status">Current · <?= (int)$topic['_activity_completed'] ?> of <?= $stageActivityTotal ?> done</span>
                                        <?php endif; ?>
                                    </div>
                                    <h2><?= e($topic['title']) ?></h2>
                                    <span class="roadmap-expand-hint">Details <i class="bi bi-chevron-down" aria-hidden="true"></i></span>
                                </div>
                                </summary>
                                <div class="roadmap-stage-details">
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
                            </details>
                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

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

                        $nextStageTopic = null;
                        foreach ($topics as $candidateTopic) {
                            if ((int)$candidateTopic['order_number'] > $stageNumber) {
                                $nextStageTopic = $candidateTopic;
                                break;
                            }
                        }

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

<div class="stage-marker">

                                <?php if ($isCompleted): ?>

                                    <i class="bi bi-check-lg" aria-hidden="true"></i>

                                <?php elseif (!$isUnlocked): ?>

                                    <i class="bi bi-lock-fill" aria-hidden="true"></i>

                                <?php else: ?>

                                    <?= $stageNumber ?>

                                <?php endif; ?>

                            </div>

<div class="stage-content">

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

<h2>

                                    <?= e($topic['title']) ?>

                                </h2>

<p class="stage-description">

                                    <?= e($topic['description']) ?>

                                </p>

                                <?php if (!empty($topic['ai_why'])): ?>
                                    <div class="ai-why-box">
                                        <div class="stage-box-title"><i class="bi bi-stars" aria-hidden="true"></i> Why Academie recommended this</div>
                                        <p><?= e($topic['ai_why']) ?></p>
                                    </div>
                                <?php endif; ?>

<?php if (!$isUnlocked): ?>

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

                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

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
                                                        Complete all <?= count($activities) ?> activities
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

<div
                                                class="
                                                    activity-progress-container
                                                "
                                            >

                                                <div
                                                    class="
                                                        activity-progress-bar
                                                    "
                                                    role="progressbar"
                                                    aria-label="Activities completed in this stage"
                                                    aria-valuemin="0"
                                                    aria-valuemax="100"
                                                    aria-valuenow="<?= $activityPercent ?>"
                                                    aria-valuetext="<?= $activityCount ?> of <?= count($activities) ?> activities completed"
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

<form
                action="api/study-path.php"
                method="POST"
                class="activity-form"
            >

                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

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

<a
                    class="learn-activity-btn"
                    href="lesson?topic_id=<?= (int)$topic['id'] ?>&amp;activity_index=<?= (int)$activityIndex ?>"
                    data-topic-id="<?= (int)$topic['id'] ?>"
                    data-activity-index="<?= (int)$activityIndex ?>"
                    data-activity-title="<?= e($activity) ?>"
                >
                    <i class="bi bi-book-half" aria-hidden="true"></i> Learn This
                </a>

        </div>

    <?php endforeach; ?>

</div>

                                        </div>

                                    <?php endif; ?>

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
                                                    <?= $nextStageTopic
                                                        ? 'Great job! The next stage is now unlocked.'
                                                        : 'Great job! You completed your full roadmap.'
                                                    ?>
                                                </p>
                                                <?php if ($nextStageTopic): ?>
                                                    <a class="stage-next-link" href="roadmap?stage=<?= (int)$nextStageTopic['order_number'] ?>">
                                                        Continue to Stage <?= (int)$nextStageTopic['order_number'] ?>
                                                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                                    </a>
                                                <?php endif; ?>

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

                </div>

                <?php endif; ?>

            </section>

            <section class="content-feedback-card" aria-labelledby="roadmapFeedbackTitle" data-feedback-form data-content-type="roadmap" data-study-path-id="<?= $studyPathId ?>" data-csrf-token="<?= e(csrf_token()) ?>">
                <div>
                    <h2 id="roadmapFeedbackTitle">Is this roadmap helpful?</h2>
                    <p>Your feedback helps us improve AI-generated study plans.</p>
                </div>
                <div class="content-feedback-actions">
                    <button type="button" class="feedback-rating" data-rating="1" aria-label="Yes, this roadmap is helpful"><i class="bi bi-hand-thumbs-up" aria-hidden="true"></i> Yes</button>
                    <button type="button" class="feedback-rating" data-rating="-1" aria-label="No, this roadmap is not helpful"><i class="bi bi-hand-thumbs-down" aria-hidden="true"></i> No</button>
                </div>
                <label class="feedback-reason-wrap" hidden>What could be improved?
                    <select class="feedback-reason form-select" aria-label="Reason this roadmap was not helpful">
                        <option value="">Choose a reason</option>
                        <option value="incorrect">Some information seems incorrect</option>
                        <option value="unclear">It is unclear</option>
                        <option value="too_difficult">It feels too difficult</option>
                        <option value="other">Something else</option>
                    </select>
                    <button type="button" class="feedback-submit">Send feedback</button>
                </label>
                <p class="feedback-message" role="status" aria-live="polite"></p>
            </section>

        <?php endif; ?>

    </main>

</div>

<script src="js/content-feedback.js" defer></script>
<script>
// Updates the activity progress display.
function updateActivityUI(progress, topicId) {
    // Updates the matching stage's activity controls from saved progress.
    document.querySelectorAll('.activity-form').forEach(function (form) {
        const formTopicId = form.querySelector('input[name="topic_id"]');
        const formActivityIndex = form.querySelector('input[name="activity_index"]');
        const item = form.querySelector('.activity-item');
        const checkbox = form.querySelector('.activity-checkbox');
        if (!formTopicId || !formActivityIndex || !item || !checkbox || formTopicId.value !== String(topicId)) return;

        const learned = Number(progress[Number(formActivityIndex.value)]) === 1;
        item.disabled = learned;
        item.classList.toggle('activity-completed', learned);
        checkbox.textContent = learned ? '✓' : '';
    });

    const container = document.querySelector('.activities-container');
    if (!container) return;

    // Counts completed activities to calculate the progress percentage.
    const completedCount = progress.filter(function (value) { return Number(value) === 1; }).length;
    const percent = progress.length ? Math.round((completedCount / progress.length) * 100) : 0;
    const count = container.querySelector('.activity-count');
    const bar = container.querySelector('.activity-progress-bar span');
    const label = container.querySelector('.activity-progress-container small');
    if (count) count.textContent = completedCount + ' / ' + progress.length;
    if (bar) bar.style.width = percent + '%';
    if (label) label.textContent = percent + '% complete';
}

// Attaches submit handlers to the activity progress forms after page load.
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.activity-form').forEach(function (form) {
        // Sends an activity completion update and refreshes the displayed progress.
        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            const button = form.querySelector('.activity-item');
            if (!button || button.disabled) return;

            button.disabled = true;
            button.classList.add('activity-saving');
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { 'Accept': 'application/json' }
                });
                const responseText = await response.text();
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    throw new Error('The server returned an invalid response. Please try again.');
                }
                if (!response.ok || !data.success) throw new Error(data.message || 'Unable to save activity progress.');

                const topicId = form.querySelector('input[name="topic_id"]');
                updateActivityUI(Array.isArray(data.activity_progress) ? data.activity_progress : [], topicId.value);
                // Reloads after stage completion so newly unlocked stages appear.
                if (data.completed) setTimeout(function () { window.location.reload(); }, 500);
            } catch (error) {
                alert(error.message || 'Unable to save activity progress.');
                button.disabled = false;
            } finally {
                button.classList.remove('activity-saving');
            }
        });
    });

});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
