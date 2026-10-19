<?php
$pageTitle = 'Privacy Policy';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/public-navbar.php';
?>
<?php
// Use a reliable destination instead of browser history, which may be empty
// when the policy page is opened directly or in a new tab.
$backUrl = !empty($_SESSION['user_id']) ? 'dashboard' : './';
?>
<div class="legal-back-wrap">
    <a class="legal-back-button" href="<?= e($backUrl) ?>">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> Back
    </a>
</div>
<main class="legal-page">
    <article class="legal-card">
        <span class="legal-eyebrow">PRIVACY</span>
        <h1>Privacy Policy</h1>
        <p class="legal-updated">Last Updated: October 14, 2026</p>
        <p class="legal-disclaimer"><strong>Disclaimer:</strong> StudyPath AI is developed for demonstration and academic purposes only as part of a student project. It is not an official educational institution or professional advisory service.</p>
        <p>This policy explains how StudyPath AI may use information submitted through this academic demonstration website.</p>
        <h2>1. Information you provide</h2>
        <p>When you register or use learning features, the website may process information such as your account details, learning preferences, assessment answers, selected career goals, and learning progress.</p>
        <h2>2. How information is used</h2>
        <p>Information may be used to authenticate your account, create or display a learning roadmap, personalize educational suggestions, and track completed activities.</p>
        <h2>3. AI-generated content</h2>
        <p>If AI features are enabled, relevant assessment answers or learning preferences may be sent to the configured AI service to generate educational suggestions. Do not enter passwords, sensitive personal information, or information about other people into free-text fields.</p>
        <h2>4. Storage and security</h2>
        <p>Information may be stored in the website's database for the features described above. Reasonable development safeguards should be used, but this academic project is not represented as a production-grade service and absolute security cannot be guaranteed.</p>
        <h2>5. Sharing and third-party services</h2>
        <p>The website may rely on hosting, database, authentication, or AI services to operate. Those services may process data as needed to provide their functionality under their own terms and policies.</p>
        <h2>6. Your choices</h2>
        <p>Avoid submitting unnecessary personal information. If you need to ask about account information or request its correction or removal, contact the project administrator at <a href="mailto:rhiniellcolumbres@gmail.com">rhiniellcolumbres@gmail.com</a>.</p>
        <h2>7. Policy changes</h2>
        <p>This notice may be updated as the academic project changes. The latest revision date appears above.</p>
    </article>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
