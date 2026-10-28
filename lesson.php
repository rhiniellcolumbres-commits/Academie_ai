<?php
require_once __DIR__ . '/includes/auth.php';

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$userId = (int)$_SESSION['user_id'];
$topicId = filter_input(INPUT_GET, 'topic_id', FILTER_VALIDATE_INT);
$activityIndex = filter_input(INPUT_GET, 'activity_index', FILTER_VALIDATE_INT);

if (!$topicId || $activityIndex === false || $activityIndex === null || $activityIndex < 0) {

    redirect('roadmap');
}

$topics = getStudyTopics($pdo, $userId);
$previousCompleted = true;
$selectedTopic = null;
$selectedTopicIndex = null;

foreach ($topics as $index => &$topic) {

    $topic['_is_unlocked'] = $previousCompleted;
    $topic['_is_read'] = (int)($topic['is_read'] ?? 0) === 1;
    $topic['_is_completed'] = (int)($topic['completed'] ?? 0) === 1;
    $previousCompleted = $topic['_is_completed'];

    if ((int)$topic['id'] === $topicId) {

        $selectedTopic = $topic;
        $selectedTopicIndex = $index;
    }
}
unset($topic);

if (!$selectedTopic || !$selectedTopic['_is_unlocked'] || !$selectedTopic['_is_read']) {

    redirect('roadmap');
}

$activities = array_slice(getActivities($selectedTopic['activity'] ?? ''), 0, ROADMAP_ACTIVITIES_PER_STAGE);
if (!isset($activities[$activityIndex])) {

    redirect('roadmap?stage=' . (int)$selectedTopic['order_number']);
}

$activity = $activities[$activityIndex];

$activityProgress = json_decode((string)($selectedTopic['activity_progress'] ?? ''), true);
$activityProgress = is_array($activityProgress) ? $activityProgress : [];
$alreadyLearned = is_array($activityProgress) && !empty($activityProgress[$activityIndex]);
$learnedActivityCount = 0;
foreach (array_keys($activities) as $index) {
    if (!empty($activityProgress[$index])) {
        $learnedActivityCount++;
    }
}
$stageProgressPercent = count($activities) > 0
    ? (int)round(($learnedActivityCount / count($activities)) * 100)
    : 0;
$previousLessonUrl = null;
if ($activityIndex > 0) {
    $previousLessonUrl = 'lesson?topic_id=' . $topicId . '&activity_index=' . ($activityIndex - 1);
} elseif (isset($topics[$selectedTopicIndex - 1])) {
    $previousTopic = $topics[$selectedTopicIndex - 1];
    $previousActivities = array_slice(getActivities($previousTopic['activity'] ?? ''), 0, ROADMAP_ACTIVITIES_PER_STAGE);
    if ($previousActivities) {
        $previousLessonUrl = 'lesson?topic_id=' . (int)$previousTopic['id'] . '&activity_index=' . (count($previousActivities) - 1);
    }
}
$nextLessonUrl = null;
$nextLessonRequiresStageCompletion = false;
$nextLessonStartsStageId = null;
$isFinalRoadmapLesson = $selectedTopicIndex === count($topics) - 1
    && !isset($activities[$activityIndex + 1]);
$showRoadmapCongrats = $isFinalRoadmapLesson
    && (int)($selectedTopic['completed'] ?? 0) === 1;

if (isset($activities[$activityIndex + 1])) {
    $nextLessonUrl = 'lesson?topic_id=' . $topicId . '&activity_index=' . ($activityIndex + 1);
} elseif (isset($topics[$selectedTopicIndex + 1])) {

    $nextTopic = $topics[$selectedTopicIndex + 1];
    $nextLessonUrl = 'lesson?topic_id=' . (int)$nextTopic['id'] . '&activity_index=0';
    $nextLessonRequiresStageCompletion = !$selectedTopic['_is_completed'];
    $nextLessonStartsStageId = (int)$nextTopic['id'];
}

$pageTitle = $activity;
$pageCss = 'css/studypath.css';
require_once __DIR__ . '/includes/header.php';
?>

<div class="roadmap-page lesson-page">
    <?php require __DIR__ . '/includes/navbar.php'; ?>

    <main class="lesson-page-container">
        <nav class="lesson-breadcrumb" aria-label="Breadcrumb">
            <a href="roadmap">Roadmap</a><span aria-hidden="true">›</span>
            <a href="roadmap?stage=<?= (int)$selectedTopic['order_number'] ?>">Stage <?= (int)$selectedTopic['order_number'] ?>: <?= e($selectedTopic['title']) ?></a><span aria-hidden="true">›</span>
            <strong>Lesson <?= $activityIndex + 1 ?></strong>
        </nav>
        <header class="lesson-page-heading">
            <span class="eyebrow">STAGE <?= (int)$selectedTopic['order_number'] ?> · LESSON <?= $activityIndex + 1 ?></span>
            <h1><?= e($activity) ?></h1>
        </header>

        <div class="lesson-layout">
            <aside class="lesson-stage-sidebar" aria-label="Lessons in this stage">
                <h2>In this stage</h2>
                <ol class="lesson-stage-list">
                    <?php foreach ($activities as $index => $stageActivity): ?>
                        <?php $isStageActivityLearned = !empty($activityProgress[$index]); ?>
                        <li class="<?= $index === $activityIndex ? 'is-current' : '' ?> <?= $isStageActivityLearned ? 'is-complete' : '' ?>">
                            <a href="lesson?topic_id=<?= (int)$topicId ?>&amp;activity_index=<?= (int)$index ?>" <?= $index === $activityIndex ? 'aria-current="page"' : '' ?>>
                                <span class="lesson-list-icon" aria-hidden="true"><?php if ($isStageActivityLearned): ?><i class="bi bi-check-lg"></i><?php else: ?><?= $index + 1 ?><?php endif; ?></span>
                                <span class="lesson-stage-title" title="<?= e($stageActivity) ?>"><?= e(preg_replace('/^\s*\d+[.)]\s*/u', '', $stageActivity)) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <div class="lesson-stage-progress"><span><i style="width: <?= $stageProgressPercent ?>%"></i></span><small><?= $learnedActivityCount ?> of <?= count($activities) ?> lessons done</small></div>
            </aside>

            <section class="lesson-main-panel" aria-label="Lesson content">
                <div class="lesson-loading" id="lessonLoading" role="status" aria-live="polite">
                    <span class="lesson-loading-icon"><i class="bi bi-stars" aria-hidden="true"></i></span>
                    <div class="lesson-loading-copy">
                        <p>Academie is preparing your lesson…</p>
                        <small>This can take a little longer while we tailor the lesson to your activity.</small>
                    </div>
                </div>

                <div class="lesson-error" id="lessonError" role="alert" hidden>
                    <p id="lessonErrorMessage">We couldn’t load this lesson.</p>
                    <button type="button" class="lesson-retry-btn" id="lessonRetryBtn">Try again</button>
                </div>

                <article class="lesson-page-content" id="lessonContent" hidden>
                    <nav class="lesson-tabs" role="tablist" aria-label="Lesson sections">
                        <button type="button" id="lesson-tab-learn" role="tab" aria-selected="true" aria-controls="lesson-panel-learn" data-lesson-tab="learn" class="is-active">1 Learn</button>
                        <button type="button" id="lesson-tab-example" role="tab" aria-selected="false" aria-controls="lesson-panel-example" data-lesson-tab="example" tabindex="-1">2 Example</button>
                        <button type="button" id="lesson-tab-practice" role="tab" aria-selected="false" aria-controls="lesson-panel-practice" data-lesson-tab="practice" tabindex="-1">3 Practice</button>
                        <button type="button" id="lesson-tab-check" role="tab" aria-selected="false" aria-controls="lesson-panel-check" data-lesson-tab="check" tabindex="-1">4 Check</button>
                        <button type="button" id="lesson-tab-resources" role="tab" aria-selected="false" aria-controls="lesson-panel-resources" data-lesson-tab="resources" tabindex="-1">5 Resources</button>
                    </nav>
                    <section class="lesson-section" id="lesson-panel-learn" role="tabpanel" aria-labelledby="lesson-tab-learn" data-lesson-panel="learn">
                        <h2>Learn</h2>
                        <p id="lessonText"></p>
                    </section>
                    <section class="lesson-section" id="lesson-panel-example" role="tabpanel" aria-labelledby="lesson-tab-example" data-lesson-panel="example" hidden>
                        <h2>Example</h2>
                        <pre id="lessonExample"></pre>
                        <button type="button" class="lesson-copy-btn" id="copyLessonExample"><i class="bi bi-copy" aria-hidden="true"></i> Copy</button>
                    </section>
                    <section class="lesson-section" id="lesson-panel-practice" role="tabpanel" aria-labelledby="lesson-tab-practice" data-lesson-panel="practice" hidden>
                        <h2>Practice</h2>
                        <p id="lessonPractice"></p>
                    </section>
                    <section class="lesson-section" id="lesson-panel-check" role="tabpanel" aria-labelledby="lesson-tab-check" data-lesson-panel="check" hidden>
                        <h2>Check Yourself</h2>
                        <ul id="lessonChecklist"></ul>
                    </section>
                    <section class="lesson-section" id="lesson-panel-resources" role="tabpanel" aria-labelledby="lesson-tab-resources" data-lesson-panel="resources" hidden>
                        <h2>Resources</h2>
                        <ul id="lessonResources"></ul>
                    </section>

                    <p class="lesson-ai-note">AI-generated lesson. Double-check important facts with the resources tab.</p>
                    <section class="content-feedback-card" aria-labelledby="lessonFeedbackTitle" data-feedback-form data-content-type="lesson" data-topic-id="<?= (int)$topicId ?>" data-activity-index="<?= (int)$activityIndex ?>" data-csrf-token="<?= e(csrf_token()) ?>">
                        <div><h2 id="lessonFeedbackTitle">Was this lesson helpful?</h2></div>
                        <div class="content-feedback-actions">
                            <button type="button" class="feedback-rating" data-rating="1" aria-label="Yes, this lesson is helpful"><i class="bi bi-hand-thumbs-up" aria-hidden="true"></i> Yes</button>
                            <button type="button" class="feedback-rating" data-rating="-1" aria-label="No, this lesson is not helpful"><i class="bi bi-hand-thumbs-down" aria-hidden="true"></i> No</button>
                        </div>
                        <label class="feedback-reason-wrap" hidden>What could be improved?
                            <select class="feedback-reason form-select" aria-label="Reason this lesson was not helpful">
                                <option value="">Choose a reason</option><option value="incorrect">Some information seems incorrect</option><option value="unclear">It is unclear</option><option value="too_difficult">It feels too difficult</option><option value="other">Something else</option>
                            </select>
                            <button type="button" class="feedback-submit">Send feedback</button>
                        </label>
                        <p class="feedback-message" role="status" aria-live="polite"></p>
                    </section>
                <section class="lesson-congrats" id="roadmapCongrats" role="status" aria-live="polite" <?= $showRoadmapCongrats ? '' : 'hidden' ?>>
                    <span class="lesson-congrats-icon" aria-hidden="true"><i class="bi bi-trophy-fill"></i></span>
                    <div class="lesson-congrats-copy">
                        <span class="eyebrow">ROADMAP COMPLETE</span>
                        <h2>Congratulations!</h2>
                        <p>You completed every stage and activity in your roadmap. Celebrate how far you’ve come.</p>
                    </div>
                </section>
                </article>

                <p class="lesson-save-message" id="lessonSaveMessage" role="status" aria-live="polite"></p>
                <footer class="lesson-page-actions" id="lessonPageActions" aria-label="Lesson navigation">
                    <?php if ($previousLessonUrl): ?>
                        <a class="lesson-action-secondary" href="<?= e($previousLessonUrl) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Previous</a>
                    <?php else: ?>
                        <a class="lesson-action-secondary" href="roadmap?stage=<?= (int)$selectedTopic['order_number'] ?>">← Back to stage</a>
                    <?php endif; ?>
                    <div class="lesson-action-group">
                        <button type="button" id="markLearnedBtn" class="mark-learned-btn" disabled><?= $showRoadmapCongrats ? '🎉 Congratulations!' : ($alreadyLearned ? '🎉 Lesson complete!' : '✓ Mark as learned') ?></button>
                        <?php if ($nextLessonUrl): ?>
                            <a class="lesson-action-primary <?= $nextLessonRequiresStageCompletion ? 'is-locked' : '' ?>" id="nextLessonLink" href="<?= e($nextLessonUrl) ?>" <?= $nextLessonRequiresStageCompletion ? 'aria-disabled="true" tabindex="-1"' : '' ?> data-requires-stage-completion="<?= $nextLessonRequiresStageCompletion ? '1' : '0' ?>" <?= $nextLessonStartsStageId ? 'data-start-topic-id="' . $nextLessonStartsStageId . '"' : '' ?>>Next lesson <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                        <?php endif; ?>
                    </div>
                </footer>
            </section>
        </div>
    </main>
</div>

<script src="js/content-feedback.js" defer></script>
<script>
// Initializes lesson loading, progress actions, and next-stage navigation.
document.addEventListener('DOMContentLoaded', function () {
    const topicId = <?= (int)$topicId ?>;
    const activityIndex = <?= (int)$activityIndex ?>;
    const csrfToken = <?= json_encode(csrf_token()) ?>;
    const loading = document.getElementById('lessonLoading');
    const content = document.getElementById('lessonContent');
    const errorBox = document.getElementById('lessonError');
    const errorMessage = document.getElementById('lessonErrorMessage');
    const learnedButton = document.getElementById('markLearnedBtn');
    const saveMessage = document.getElementById('lessonSaveMessage');
    const nextLink = document.getElementById('nextLessonLink');
    const roadmapCongrats = document.getElementById('roadmapCongrats');
    const isFinalRoadmapLesson = <?= $isFinalRoadmapLesson ? 'true' : 'false' ?>;
    const lessonTabs = Array.from(document.querySelectorAll('[data-lesson-tab]'));
    const lessonPanels = Array.from(document.querySelectorAll('[data-lesson-panel]'));

    // Switches the visible lesson section and keeps tab keyboard navigation accessible.
    function activateLessonTab(tab, moveFocus) {
        const selectedName = tab.dataset.lessonTab;
        lessonTabs.forEach(function (item) {
            const isSelected = item === tab;
            item.classList.toggle('is-active', isSelected);
            item.setAttribute('aria-selected', isSelected ? 'true' : 'false');
            item.tabIndex = isSelected ? 0 : -1;
        });
        lessonPanels.forEach(function (panel) {
            panel.hidden = panel.dataset.lessonPanel !== selectedName;
        });
        if (moveFocus) tab.focus();
    }

    lessonTabs.forEach(function (tab, index) {
        tab.addEventListener('click', function () { activateLessonTab(tab, false); });
        tab.addEventListener('keydown', function (event) {
            var nextIndex = index;
            if (event.key === 'ArrowRight') nextIndex = (index + 1) % lessonTabs.length;
            else if (event.key === 'ArrowLeft') nextIndex = (index - 1 + lessonTabs.length) % lessonTabs.length;
            else if (event.key === 'Home') nextIndex = 0;
            else if (event.key === 'End') nextIndex = lessonTabs.length - 1;
            else return;
            event.preventDefault();
            activateLessonTab(lessonTabs[nextIndex], true);
        });
    });

// Reads a JSON response and handles invalid response data.
async function readJsonResponse(response, fallbackMessage) {
        const responseText = await response.text();
        try {
            return JSON.parse(responseText);
        } catch (parseError) {
            throw new Error(fallbackMessage);
        }
    }

// Loads and displays the lesson for the current activity.
async function loadLesson() {
        loading.hidden = false;
        content.hidden = true;
        errorBox.hidden = true;
        let requestTimeout = null;

        try {
            const formData = new FormData();
            formData.append('topic_id', topicId);
            formData.append('activity_index', activityIndex);
            formData.append('csrf_token', csrfToken);
            const requestController = new AbortController();
            requestTimeout = window.setTimeout(function () {
                requestController.abort();
            }, 60000);
            const response = await fetch('api/ai-lesson.php', {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' },
                signal: requestController.signal
            });
            window.clearTimeout(requestTimeout);
            const data = await readJsonResponse(response, 'The lesson server returned an invalid response. Please try again.');
            if (!response.ok || !data.success) throw new Error(data.message || 'Unable to load this lesson. Please try again.');

            document.getElementById('lessonText').textContent = data.lesson || '';
            document.getElementById('lessonExample').textContent = data.example || '';
            document.getElementById('lessonPractice').textContent = data.practice || '';
            renderList('lessonChecklist', data.checklist);
            renderList('lessonResources', data.resources);
            loading.hidden = true;
            content.hidden = false;
            learnedButton.disabled = <?= $alreadyLearned ? 'true' : 'false' ?>;
        } catch (error) {
            loading.hidden = true;
            errorMessage.textContent = error.name === 'AbortError'
                ? 'This lesson is taking longer than expected. Please try again.'
                : (error.message || 'Unable to load this lesson. Please try again.');
            errorBox.hidden = false;
        } finally {
            if (requestTimeout !== null) window.clearTimeout(requestTimeout);
        }
    }

    // Displays a list of lesson items.
    function renderList(id, items) {
        const list = document.getElementById(id);
        list.replaceChildren();
        // Creates one safe list item for each checklist or resource entry.
        (Array.isArray(items) ? items : []).forEach(function (item) {
            const entry = document.createElement('li');
            entry.textContent = item;
            list.appendChild(entry);
        });
    }

    document.getElementById('lessonRetryBtn').addEventListener('click', loadLesson);
    loadLesson();

    document.getElementById('copyLessonExample').addEventListener('click', async function () {
        const copyButton = this;
        const code = document.getElementById('lessonExample').textContent;
        try {
            await navigator.clipboard.writeText(code);
            copyButton.innerHTML = '<i class="bi bi-check2" aria-hidden="true"></i> Copied';
            window.setTimeout(function () { copyButton.innerHTML = '<i class="bi bi-copy" aria-hidden="true"></i> Copy'; }, 1400);
        } catch (error) {
            saveMessage.textContent = 'Copy is unavailable in this browser.';
        }
    });

    // Saves completion of this activity and updates the lesson controls.
    learnedButton.addEventListener('click', async function () {
        if (learnedButton.disabled) return;
        learnedButton.disabled = true;
        learnedButton.classList.add('is-saving');
        learnedButton.textContent = 'Saving…';
        saveMessage.textContent = '';

        try {
            const formData = new FormData();
            formData.append('action', 'complete_activity');
            formData.append('topic_id', topicId);
            formData.append('activity_index', activityIndex);
            formData.append('csrf_token', csrfToken);
            const response = await fetch('api/study-path.php', {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' }
            });
            const data = await readJsonResponse(response, 'The progress server returned an invalid response. Please try again.');
            if (!response.ok || !data.success) throw new Error(data.message || 'Unable to save lesson progress.');

            learnedButton.classList.remove('is-saving');
            learnedButton.classList.add('learned');
            learnedButton.textContent = data.completed && isFinalRoadmapLesson
                ? '🎉 Congratulations!'
                : '🎉 Lesson complete!';
            if (data.completed && isFinalRoadmapLesson) {
                saveMessage.textContent = '';
                roadmapCongrats.hidden = false;
                roadmapCongrats.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else {
                saveMessage.textContent = data.completed ? 'Great work! Stage complete. The next stage is now unlocked.' : 'Nice work! This lesson is complete.';
            }
            if (nextLink && nextLink.dataset.requiresStageCompletion === '1' && data.completed) {
                nextLink.classList.remove('is-locked');
                nextLink.removeAttribute('aria-disabled');
                nextLink.removeAttribute('tabindex');
                nextLink.dataset.requiresStageCompletion = '0';
            }
        } catch (error) {
            learnedButton.disabled = false;
            learnedButton.classList.remove('is-saving');
            learnedButton.textContent = '✓ Mark as Learned';
            saveMessage.textContent = error.message || 'Unable to save lesson progress.';
        }
    });

    if (nextLink) {
        // Blocks locked navigation or marks the next stage as read before opening it.
        nextLink.addEventListener('click', function (event) {
            if (nextLink.dataset.requiresStageCompletion === '1') {
                event.preventDefault();
                saveMessage.textContent = 'Complete this stage to unlock the next lesson.';
                saveMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            if (!nextLink.dataset.startTopicId) return;
            event.preventDefault();
            nextLink.classList.add('is-saving');
            nextLink.setAttribute('aria-disabled', 'true');

            const formData = new FormData();
            formData.append('action', 'read');
            formData.append('topic_id', nextLink.dataset.startTopicId);
            formData.append('csrf_token', csrfToken);
            fetch('api/study-path.php', {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' }
            // Opens the next stage only after its read request succeeds.
            }).then(async function (response) {
                const data = await readJsonResponse(response, 'The server returned an invalid response. Please try again.');
                if (!response.ok || !data.success) throw new Error(data.message || 'Unable to open the next stage.');
                window.location.assign(nextLink.href);
            // Restores the link and shows a message if opening the stage fails.
            }).catch(function (error) {
                nextLink.classList.remove('is-saving');
                nextLink.removeAttribute('aria-disabled');
                saveMessage.textContent = error.message || 'Unable to open the next stage.';
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
