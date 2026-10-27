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

$activities = array_slice(getActivities($selectedTopic['activity'] ?? ''), 0, 6);
if (!isset($activities[$activityIndex])) {

    redirect('roadmap?stage=' . (int)$selectedTopic['order_number']);
}

$activity = $activities[$activityIndex];

$activityProgress = json_decode((string)($selectedTopic['activity_progress'] ?? ''), true);
$alreadyLearned = is_array($activityProgress) && !empty($activityProgress[$activityIndex]);
$nextLessonUrl = null;
$nextLessonRequiresStageCompletion = false;
$nextLessonStartsStageId = null;

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
        <header class="lesson-page-heading">
            <span class="eyebrow">STAGE <?= (int)$selectedTopic['order_number'] ?> · LESSON <?= $activityIndex + 1 ?></span>
            <h1><?= e($activity) ?></h1>
            <p><?= e($selectedTopic['title']) ?></p>
        </header>

        <div class="lesson-loading" id="lessonLoading" role="status" aria-live="polite">
            <span class="lesson-loading-icon"><i class="bi bi-stars" aria-hidden="true"></i></span>
            <p>Academie is preparing your lesson…</p>
        </div>

        <div class="lesson-error" id="lessonError" role="alert" hidden>
            <p id="lessonErrorMessage">We couldn’t load this lesson.</p>
            <button type="button" class="lesson-retry-btn" id="lessonRetryBtn">Try again</button>
        </div>

        <article class="lesson-page-content" id="lessonContent" hidden>
            <section class="lesson-section">
                <h2><i class="bi bi-book-half" aria-hidden="true"></i> Learn</h2>
                <p id="lessonText"></p>
            </section>
            <section class="lesson-section">
                <h2><i class="bi bi-lightbulb" aria-hidden="true"></i> Example</h2>
                <pre id="lessonExample"></pre>
            </section>
            <section class="lesson-section">
                <h2><i class="bi bi-bullseye" aria-hidden="true"></i> Practice</h2>
                <p id="lessonPractice"></p>
            </section>
            <section class="lesson-section">
                <h2><i class="bi bi-check2-square" aria-hidden="true"></i> Check Yourself</h2>
                <ul id="lessonChecklist"></ul>
            </section>
            <section class="lesson-section">
                <h2><i class="bi bi-journals" aria-hidden="true"></i> Resources</h2>
                <ul id="lessonResources"></ul>
            </section>

            <section class="content-feedback-card" aria-labelledby="lessonFeedbackTitle" data-feedback-form data-content-type="lesson" data-topic-id="<?= (int)$topicId ?>" data-activity-index="<?= (int)$activityIndex ?>" data-csrf-token="<?= e(csrf_token()) ?>">
                <div>
                    <h2 id="lessonFeedbackTitle">Was this lesson helpful?</h2>
                    <p>Your feedback helps us improve AI-generated lessons.</p>
                </div>
                <div class="content-feedback-actions">
                    <button type="button" class="feedback-rating" data-rating="1" aria-label="Yes, this lesson is helpful"><i class="bi bi-hand-thumbs-up" aria-hidden="true"></i> Yes</button>
                    <button type="button" class="feedback-rating" data-rating="-1" aria-label="No, this lesson is not helpful"><i class="bi bi-hand-thumbs-down" aria-hidden="true"></i> No</button>
                </div>
                <label class="feedback-reason-wrap" hidden>What could be improved?
                    <select class="feedback-reason form-select" aria-label="Reason this lesson was not helpful">
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

            <div class="lesson-complete-action">
                <button type="button" id="markLearnedBtn" class="mark-learned-btn" <?= $alreadyLearned ? 'disabled' : '' ?>>
                    <?= $alreadyLearned ? '✓ Already Learned' : '✓ Mark as Learned' ?>
                </button>
                <p class="lesson-save-message" id="lessonSaveMessage" role="status" aria-live="polite"></p>
            </div>
        </article>

        <nav class="lesson-page-actions" aria-label="Lesson navigation">
            <a class="lesson-action-secondary" href="roadmap?stage=<?= (int)$selectedTopic['order_number'] ?>">
                <i class="bi bi-map" aria-hidden="true"></i> Back to Roadmap
            </a>
            <?php if ($nextLessonUrl): ?>
                <a
                    class="lesson-action-primary <?= $nextLessonRequiresStageCompletion ? 'is-locked' : '' ?>"
                    id="nextLessonLink"
                    href="<?= e($nextLessonUrl) ?>"
                    <?= $nextLessonRequiresStageCompletion ? 'aria-disabled="true" tabindex="-1"' : '' ?>
                    data-requires-stage-completion="<?= $nextLessonRequiresStageCompletion ? '1' : '0' ?>"
                    <?= $nextLessonStartsStageId ? 'data-start-topic-id="' . $nextLessonStartsStageId . '"' : '' ?>
                >Next Lesson <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
            <?php else: ?>
                <span class="lesson-action-primary is-locked" aria-disabled="true">Final Lesson</span>
            <?php endif; ?>
        </nav>
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

        try {
            const formData = new FormData();
            formData.append('topic_id', topicId);
            formData.append('activity_index', activityIndex);
            formData.append('csrf_token', csrfToken);
            const response = await fetch('api/ai-lesson.php', {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' }
            });
            const data = await readJsonResponse(response, 'The lesson server returned an invalid response. Please try again.');
            if (!response.ok || !data.success) throw new Error(data.message || 'Unable to load this lesson. Please try again.');

            document.getElementById('lessonText').textContent = data.lesson || '';
            document.getElementById('lessonExample').textContent = data.example || '';
            document.getElementById('lessonPractice').textContent = data.practice || '';
            renderList('lessonChecklist', data.checklist);
            renderList('lessonResources', data.resources);
            loading.hidden = true;
            content.hidden = false;
        } catch (error) {
            loading.hidden = true;
            errorMessage.textContent = error.message || 'Unable to load this lesson. Please try again.';
            errorBox.hidden = false;
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
            learnedButton.textContent = '✓ Learned';
            saveMessage.textContent = data.completed ? 'Stage complete. The next stage is now unlocked.' : 'Your progress has been saved.';
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
