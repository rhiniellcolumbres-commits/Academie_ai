<?php
$pageTitle = 'Moderation and Removal Policy';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/public-navbar.php';
?>
<?php
// Use a reliable destination instead of browser history, which may be empty
// when the policy page is opened directly or in a new tab.
$backUrl = !empty($_SESSION['user_id']) ? 'dashboard.php' : 'index.php';
?>
<div class="legal-back-wrap">
    <a class="legal-back-button" href="<?= e($backUrl) ?>">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> Back
    </a>
</div>
<main class="legal-page">
    <article class="legal-card">
        <span class="legal-eyebrow">MODERATION</span>
        <h1>Moderation and Removal Policy</h1>
        <p class="legal-updated">Last Updated: October 14, 2026</p>
        <p class="legal-disclaimer"><strong>Disclaimer:</strong> StudyPath AI is developed for demonstration and academic purposes only as part of a student project. It is not an official educational institution or professional advisory service.</p>
        <p>This policy describes expectations for respectful use of StudyPath AI and how inappropriate submissions may be handled.</p>
        <h2>1. Respectful use</h2>
        <p>Users should provide relevant information and communicate respectfully when using any text-entry or learning features.</p>
        <h2>2. Prohibited content and activity</h2>
        <p>Do not submit threats, harassment, hateful or discriminatory content, unlawful material, spam, malicious code, or private information about another person.</p>
        <h2>3. Review of submissions</h2>
        <p>Content submitted to the website may be reviewed when necessary to operate, maintain, or protect the academic project. Not every feature necessarily includes live or automated moderation.</p>
        <h2>4. Reporting a concern</h2>
        <p>If you identify content or behavior that may be harmful or inappropriate, contact the project team at <a href="mailto:rhiniellcolumbres@gmail.com">rhiniellcolumbres@gmail.com</a>. Include the page or feature involved and a concise description of the concern.</p>
        <h2>5. Removal and restrictions</h2>
        <p>The project team may remove submissions, disable access to a feature, or restrict an account when reasonably necessary to address misuse, safety concerns, or technical issues.</p>
        <h2>6. No guarantee of review</h2>
        <p>As an academic demonstration, the website may not provide continuous monitoring or a formal response-time guarantee.</p>
        <h2>7. AI-generated material</h2>
        <p>AI-generated explanations or recommendations may be inaccurate or unsuitable. Users should verify important material and report clearly problematic output when possible.</p>
    </article>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
