<?php
$pageTitle = 'Terms & Conditions';
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
        <span class="legal-eyebrow">TERMS</span>
        <h1>Terms & Conditions</h1>
        <p class="legal-updated">Last Updated: October 14, 2026</p>
        <p class="legal-disclaimer"><strong>Disclaimer:</strong> StudyPath AI is developed for demonstration and academic purposes only as part of a student project. It is not an official educational institution or professional advisory service.</p>
        <p>By using StudyPath AI, you agree to use this website responsibly and understand that it is an academic demonstration project.</p>
        <h2>1. Academic purpose</h2>
        <p>StudyPath AI is created for demonstration and academic purposes. It is not an official educational institution, accredited training provider, or professional career-advisory service.</p>
        <h2>2. Educational suggestions</h2>
        <p>Learning paths, lesson explanations, activities, and AI-generated recommendations are provided as learning aids. They may be incomplete or inaccurate, so verify important information with trusted resources, instructors, or advisers.</p>
        <h2>3. Account responsibilities</h2>
        <p>You are responsible for keeping your sign-in details private and for activity carried out through your account. Do not attempt to access another person's account or data.</p>
        <h2>4. Acceptable use</h2>
        <p>Do not misuse the website, interfere with its operation, attempt unauthorized access, submit unlawful content, or use it to violate another person's rights.</p>
        <h2>5. Third-party services</h2>
        <p>Some features may depend on third-party hosting, authentication, or AI services. Their availability and operation may be subject to their own terms.</p>
        <h2>6. Availability and warranty</h2>
        <p>The website is provided for demonstration on an “as is” and “as available” basis. Features may change or become unavailable during development, and uninterrupted operation is not guaranteed.</p>
        <h2>7. Limitation</h2>
        <p>To the extent permitted by applicable law, the project team does not guarantee the accuracy or suitability of generated educational content. Do not rely on it as a substitute for professional or official advice.</p>
        <h2>8. Changes and termination</h2>
        <p>Features or these terms may be revised as the project develops. Access may be restricted when necessary to protect the website or its users.</p>
        <h2>9. Contact</h2>
        <p>For questions about these terms, contact the project team at <a href="mailto:rhiniellcolumbres@gmail.com">rhiniellcolumbres@gmail.com</a>.</p>
    </article>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
