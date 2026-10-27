<?php
$pageTitle = 'Privacy Policy';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/public-navbar.php';
$backUrl = !empty($_SESSION['user_id']) ? 'dashboard' : './';
?>
<div class="legal-back-wrap"><a class="legal-back-button" href="<?= e($backUrl) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back</a></div>
<main class="legal-page"><article class="legal-card">
<span class="legal-eyebrow">PRIVACY</span><h1>Privacy Policy</h1>
<p class="legal-updated">Last Updated: October 14, 2026</p>
<p class="legal-disclaimer"><strong>Disclaimer:</strong> Academie is developed for demonstration and academic purposes as part of a student project. It is not an official educational institution or professional advisory service.</p>
<p>This policy describes what information Academie may handle, why it may be used, and how to contact the project administrator with a privacy question. The information handled may depend on the features you use.</p>
<h2>1. Information collected and stored</h2><p>When you create an account, Academie stores your name, email address, and a securely hashed password. Learning features also store assessment answers, your selected career goal, your generated study roadmap, and activity completion progress. AI lesson guides that you request may be saved with your study topics so they can be shown again. If you submit optional feedback on a roadmap or lesson, Academie stores your rating, selected reason (if provided), and the related learning item with your account.</p>
<h2>2. How information is used</h2><p>This information may be used to sign you in, create or show your learning roadmap, tailor educational suggestions, and record completed activities.</p>
<h2>3. AI features and Gemini</h2><p>When Gemini AI is configured, Academie sends assessment answers and your career goal to Google's Gemini service to generate a study roadmap. When you request an AI lesson, the relevant career goal, learning stage, topic, and activity are sent to Gemini to generate the lesson. Do not enter passwords, sensitive personal information, or another person's information into learning fields. Google's own terms and privacy practices apply to information it processes.</p>
<h2>4. Storage and security</h2><p>Account and learning information is stored in the website database. Passwords are stored as hashes rather than plain text. This is an academic project, not a production-grade service; safeguards are used, but complete security cannot be guaranteed. The code does not state a fixed data-retention period.</p>
<h2>5. Service providers</h2><p>Academie relies on its configured web host, database, and, when enabled, Google's Gemini service. These providers may process information as needed to provide the service under their own terms and privacy practices.</p>
<h2>6. Your choices and requests</h2><p>Only provide information needed to use the website. To ask about your account data or request correction or deletion, contact the project administrator at <a href="mailto:rhiniellcolumbres@gmail.com">rhiniellcolumbres@gmail.com</a>. Requests may require verification. The project does not currently describe an automated account-deletion process.</p>
<h2>7. Policy changes</h2><p>This notice may be updated as the project changes. The latest revision date appears above.</p>
</article></main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
