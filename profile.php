<?php

require_once __DIR__ . '/includes/auth.php';

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$userId = (int) $_SESSION['user_id'];
$user   = getUserById($pdo, $userId);

if (!$user) {
    
    redirect('./');
}

$topics   = getStudyTopics($pdo, $userId);

$progress = getRoadmapProgress($topics);

syncAchievements($pdo, $userId, $progress);
$earnedAchievements = getUserAchievements($pdo, $userId);
$achievementCatalog = getAchievementCatalog();
$earnedByTitle = [];
foreach ($earnedAchievements as $earnedAchievement) {
    $earnedByTitle[$earnedAchievement['title']] = $earnedAchievement;
}

$hasRoadmap = $progress['totalStages'] > 0;

$hasAssessment = !empty($user['assessment_id']);

$roadmapPercent = $hasRoadmap
    ? (int) round(($progress['completedStages'] / $progress['totalStages']) * 100)
    : 0;

$displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

$initials    = strtoupper(substr((string) ($user['first_name'] ?? 'S'), 0, 1) . substr((string) ($user['last_name'] ?? ''), 0, 1));

$learningTrack = trim((string) ($user['career_goal'] ?? ''));
$assessmentData = json_decode((string) ($user['assessment_answers'] ?? ''), true);
$domainScores = is_array($assessmentData) && ($assessmentData['type'] ?? '') === 'knowledge_pretest'
    ? (array) ($assessmentData['domain_scores'] ?? [])
    : [];
$nextFocus = '';
if ($domainScores) {
    asort($domainScores);
    $weakestDomain = (string) array_key_first($domainScores);
    $nextFocus = 'Strengthen your ' . strtolower($weakestDomain) . ' foundations, then apply them in practical projects.';
} elseif ($hasAssessment) {
    $nextFocus = 'Follow your roadmap stages to build knowledge and apply it through practical projects.';
} else {
    $nextFocus = 'Take the knowledge pre-test to find a suitable starting point and roadmap.';
}

$createdAt = $user['created_at'] ?? null;

$pageTitle = 'My Profile';
$pageCss   = 'css/profile.css';
$bodyClass = 'profile-shell';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<main class="main-content">
    <div class="page-container profile-page">
<section class="panel profile-overview" aria-label="Profile overview">
            <div class="profile-avatar" aria-hidden="true"><?= e($initials !== '' ? $initials : 'S') ?></div>
            <div class="profile-overview-main">
                <div class="profile-name-row">
                    <h2><?= e($displayName !== '' ? $displayName : 'Student') ?></h2>
                    <?php if ($learningTrack !== ''): ?>
                        <span class="profile-learning-track"><?= e($learningTrack) ?></span>
                    <?php endif; ?>
                </div>
                <p><?= e($user['email'] ?? '') ?></p>
                <span class="profile-member-since">Member since <?= $createdAt ? e(date('M Y', strtotime((string) $createdAt))) : '—' ?></span>
            </div>
        </section>

<section class="panel learning-profile-panel" aria-labelledby="learning-profile-title">
            <div class="profile-section-heading">
                <div>
                    <span class="eyebrow">YOUR LEARNING PLAN</span>
                    <h2 class="h4 mb-1" id="learning-profile-title">Your Learning Snapshot</h2>
                    <p class="text-muted mb-0">A quick summary of your recommended direction and next focus.</p>
                </div>
            </div>

            <dl class="learning-details-grid learning-snapshot-grid">
                <div class="learning-detail"><dt>Recommended path</dt><dd><?= e($learningTrack !== '' ? $learningTrack : 'Not set yet') ?></dd></div>
                <div class="learning-detail"><dt>Starting point</dt><dd><?= e($user['current_level'] ?: 'Not assessed yet') ?></dd></div>
                <div class="learning-detail"><dt>Your next focus</dt><dd><?= e($nextFocus) ?></dd></div>
            </dl>
            <div class="learning-snapshot-actions">
                <?php if ($hasRoadmap): ?><a class="btn btn-primary btn-sm" href="roadmap">View my roadmap</a><?php endif; ?>
                <a class="btn btn-outline-primary btn-sm" href="assessment<?= $hasAssessment ? '?retake=1' : '' ?>"><?= $hasAssessment ? 'Retake pre-test' : 'Take pre-test' ?></a>
            </div>
        </section>

<section class="panel roadmap-progress-panel" aria-labelledby="profile-roadmap-title">
            <div class="profile-section-heading">
                <div>
                    <span class="eyebrow">KEEP MOVING FORWARD</span>
                    <h2 class="h4 mb-1" id="profile-roadmap-title">Roadmap Progress</h2>
                    <p class="text-muted mb-0">A quick view of what you have completed so far.</p>
                </div>
                <?php if ($hasRoadmap): ?>
                    <a class="btn btn-outline-primary btn-sm" href="roadmap">View Roadmap</a>
                <?php endif; ?>
            </div>

            <?php if ($hasRoadmap): ?>
                <div class="profile-progress-stats">
                    <div><strong><?= (int) $progress['completedStages'] ?> / <?= (int) $progress['totalStages'] ?></strong><span>Stages completed</span></div>
                    <div><strong><?= (int) $progress['completedActivities'] ?> / <?= (int) $progress['totalActivities'] ?></strong><span>Activities completed</span></div>
                    <div><strong><?= $roadmapPercent ?>%</strong><span>Roadmap complete</span></div>
                </div>
                <div class="profile-progress-track" role="progressbar" aria-label="Roadmap completion" aria-valuenow="<?= $roadmapPercent ?>" aria-valuemin="0" aria-valuemax="100">
                    <span style="width: <?= $roadmapPercent ?>%"></span>
                </div>
            <?php else: ?>
                <div class="achievements-empty profile-no-roadmap">
                    <div class="achievement-icon" aria-hidden="true"><i class="bi bi-signpost-split"></i></div>
                    <div>
                        <h3>No roadmap progress yet</h3>
                        <p>Complete the knowledge pre-test to create a personalized roadmap and start tracking your progress.</p>
                        <?php if ($hasAssessment): ?>
                            <a class="btn btn-dark btn-sm mt-2" href="roadmap">View Roadmap</a>
                        <?php else: ?>
                            <a class="btn btn-dark btn-sm mt-2" href="assessment">Take pre-test</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>

<section class="panel achievements-panel" aria-labelledby="achievements-title">
            <div class="achievements-heading">
                <div>
                    <span class="eyebrow">YOUR PROGRESS</span>
                    <h2 class="h4 mb-1" id="achievements-title">Achievements</h2>
                    <p class="text-muted mb-0">Badges are saved to your account.</p>
                </div>
                <div class="achievement-count">
                    <strong><?= count($earnedAchievements) ?></strong>
                    <span>earned</span>
                </div>
            </div>

            <div class="achievement-grid">
                <?php foreach ($achievementCatalog as $code => $badge):
                    $achievement = $earnedByTitle[$badge['title']] ?? null;
                    $lockedGuidance = [
                        'first_steps' => 'Complete your first learning activity.',
                        'stage_achiever' => 'Finish your first roadmap stage.',
                        'halfway_hero' => 'Reach halfway through your roadmap.',
                        'roadmap_champion' => 'Complete your roadmap.',
                        'activity_master' => 'Complete every roadmap activity.'
                    ][$code] ?? 'Keep making progress in your roadmap.';
                ?>
                    <article class="achievement-card <?= $achievement ? 'earned' : 'locked' ?>">
                        <div class="achievement-icon" aria-hidden="true"><i class="<?= e($badge['icon']) ?>"></i></div>
                        <div>
                            <h3><?= e($badge['title']) ?></h3>
                            <p><?= e($achievement ? $badge['description'] : $lockedGuidance) ?></p>
                            <?php if ($achievement && !empty($achievement['earned_at'])): ?>
                                <span class="achievement-status">✓ Earned · <?= e(date('M j, Y', strtotime((string) $achievement['earned_at']))) ?></span>
                            <?php elseif ($achievement): ?>
                                <span class="achievement-status">✓ Earned</span>
                            <?php else: ?>
                                <span class="achievement-locked-status">Not earned yet</span>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
