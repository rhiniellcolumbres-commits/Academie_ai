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

$hasRoadmap = $progress['totalStages'] > 0;

$hasAssessment = !empty($user['assessment_id']);

$roadmapPercent = $hasRoadmap
    ? (int) round(($progress['completedStages'] / $progress['totalStages']) * 100)
    : 0;

$displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

$initials    = strtoupper(substr((string) ($user['first_name'] ?? 'S'), 0, 1) . substr((string) ($user['last_name'] ?? ''), 0, 1));

$learningTrack = trim((string) ($user['career_goal'] ?? ''));

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
                <p>Review your learning goals, roadmap progress, and achievements.</p>
            </div>
        </div>

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

<section class="panel learning-profile-panel mt-4" aria-labelledby="learning-profile-title">
            <div class="profile-section-heading">
                <div>
                    <span class="eyebrow">YOUR LEARNING PLAN</span>
                    <h2 class="h4 mb-1" id="learning-profile-title">Learning Profile</h2>
                    <p class="text-muted mb-0">Your goals and interests help shape your personalized roadmap.</p>
                </div>
                <?php if (!$hasAssessment): ?>
                    <a class="btn btn-outline-primary btn-sm" href="assessment">Complete Assessment</a>
                <?php endif; ?>
            </div>

            <dl class="learning-details-grid">
                <div class="learning-detail"><dt>Career goal</dt><dd><?= e($user['career_goal'] ?: 'Not provided') ?></dd></div>
                <div class="learning-detail"><dt>Current level</dt><dd><?= e($user['current_level'] ?: 'Not provided') ?></dd></div>
                <div class="learning-detail"><dt>Improvement goal</dt><dd><?= e($user['improvement_goal'] ?: 'Not provided') ?></dd></div>
                <div class="learning-detail"><dt>Interests</dt><dd><?= e($user['interests'] ?: 'Not provided') ?></dd></div>
                <div class="learning-detail"><dt>Skills</dt><dd><?= e($user['skills'] ?: 'Not provided') ?></dd></div>
            </dl>
        </section>

<section class="panel roadmap-progress-panel mt-4" aria-labelledby="profile-roadmap-title">
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
                        <p>Complete your assessment to create a personalized roadmap and start tracking your progress.</p>
                        <?php if ($hasAssessment): ?>
                            <a class="btn btn-dark btn-sm mt-2" href="roadmap">View Roadmap</a>
                        <?php else: ?>
                            <a class="btn btn-dark btn-sm mt-2" href="assessment">Start Assessment</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>

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
            <?php else: ?>
                <div class="achievements-empty">
                    <div class="achievement-icon" aria-hidden="true"><i class="bi bi-bullseye"></i></div>
                    <div>
                        <h3>No achievements yet</h3>
                        <p>Complete activities in your learning roadmap to earn badges and track milestones.</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($hasRoadmap): ?>
                <p class="achievement-note">Achievements update automatically as you complete activities and stages. Finishing a roadmap shows completion; it does not by itself certify professional proficiency.</p>
            <?php endif; ?>
        </section>

    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
