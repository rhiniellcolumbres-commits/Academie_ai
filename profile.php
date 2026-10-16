<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$userId = (int) $_SESSION['user_id'];
$user   = getUserById($pdo, $userId);

if (!$user) {
    redirect('index.php');
}

/*
|--------------------------------------------------------------------------
| Roadmap progress and achievements
|--------------------------------------------------------------------------
| 1. Load the student's roadmap stages.
| 2. Count finished stages/activities.
| 3. Save any badge the student has newly earned.
| 4. Load all saved badges to show on the page.
*/

$topics   = getStudyTopics($pdo, $userId);
$progress = getRoadmapProgress($topics);

syncAchievements($pdo, $userId, $progress);
$earnedAchievements = getUserAchievements($pdo, $userId);

$hasRoadmap = $progress['totalStages'] > 0;

/*
|--------------------------------------------------------------------------
| Values shown in the profile header
|--------------------------------------------------------------------------
*/

// getUserById() already joins the assessment, so career_goal and course are on $user.
$displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$initials    = strtoupper(substr((string) ($user['first_name'] ?? 'S'), 0, 1) . substr((string) ($user['last_name'] ?? ''), 0, 1));

$learningTrack = trim((string) ($user['career_goal'] ?? ''));
if ($learningTrack === '') {
    $learningTrack = trim((string) ($user['course'] ?? ''));
}

$createdAt = $user['created_at'] ?? null;

$pageTitle = 'My Profile';
$pageCss   = 'css/profile.css';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<main class="main-content">
    <div class="page-container profile-page">
        <div class="page-header">
            <div>
                <span class="eyebrow">YOUR SPACE</span>
                <h1>My Profile</h1>
                <p>View your student profile and the achievements you have earned.</p>
            </div>
        </div>

        <!-- 1. PROFILE HEADER -->
        <section class="panel profile-overview mt-4" aria-label="Profile overview">
            <div class="profile-avatar" aria-hidden="true"><?= e($initials !== '' ? $initials : 'S') ?></div>
            <div class="profile-overview-main">
                <span class="eyebrow">STUDENT ACCOUNT</span>
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

        <!-- 2. ACHIEVEMENTS -->
        <section class="panel achievements-panel mt-4" aria-labelledby="achievements-title">
            <div class="achievements-heading">
                <div>
                    <span class="eyebrow">YOUR PROGRESS</span>
                    <h2 class="h4 mb-1" id="achievements-title">Achievements</h2>
                    <p class="text-muted mb-0">Your badges are saved to your account, so achievements you earn stay here.</p>
                </div>
                <div class="achievement-count">
                    <strong><?= count($earnedAchievements) ?></strong>
                    <span>earned</span>
                </div>
            </div>

            <?php if ($earnedAchievements): ?>
                <div class="achievement-grid">
                    <?php foreach ($earnedAchievements as $achievement): ?>
                        <article class="achievement-card earned">
                            <div class="achievement-icon" aria-hidden="true"><i class="<?= e($achievement['icon']) ?>"></i></div>
                            <div>
                                <h3><?= e($achievement['title']) ?></h3>
                                <p><?= e($achievement['description']) ?></p>
                                <?php if (!empty($achievement['roadmap_title'])): ?>
                                    <small class="achievement-roadmap">Roadmap: <?= e($achievement['roadmap_title']) ?></small>
                                <?php endif; ?>
                                <span class="achievement-status">✓ Earned<?= !empty($achievement['earned_at']) ? ' · ' . e(date('M j, Y', strtotime((string) $achievement['earned_at']))) : '' ?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($hasRoadmap): ?>
                <div class="achievements-empty">
                    <div class="achievement-icon" aria-hidden="true"><i class="bi bi-bullseye"></i></div>
                    <div>
                        <h3>Your first achievement is waiting!</h3>
                        <p>Complete your first activity in the <a href="study-path.php">Study Path</a> to unlock the <strong>First Steps</strong> achievement.</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($hasRoadmap): ?>
                <p class="achievement-note">Achievements update automatically as you complete activities and stages. Finishing a roadmap shows completion; it does not by itself certify professional proficiency.</p>
            <?php else: ?>
                <div class="achievements-empty">
                    <div class="achievement-icon" aria-hidden="true"><i class="bi bi-signpost-split"></i></div>
                    <div>
                        <h3>No current roadmap</h3>
                        <p>Your previous achievements remain saved. Generate a new learning roadmap to continue making progress.</p>
                        <a class="btn btn-dark btn-sm mt-2" href="assessment.php">Start Assessment</a>
                    </div>
                </div>
            <?php endif; ?>
        </section>

    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
