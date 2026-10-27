<?php
$pageTitle = 'Moderation and Removal Policy';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/public-navbar.php';
$backUrl = !empty($_SESSION['user_id']) ? 'dashboard' : './';
?>
<div class="legal-back-wrap"><a class="legal-back-button" href="<?= e($backUrl) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back</a></div>
<main class="legal-page"><article class="legal-card">
<span class="legal-eyebrow">MODERATION</span><h1>Moderation and Removal Policy</h1>
<p class="legal-updated">Last Updated: October 14, 2026</p>
<p class="legal-disclaimer"><strong>Disclaimer:</strong> Academie is developed for demonstration and academic purposes as part of a student project. It is not an official educational institution or professional advisory service.</p>
<p>This policy explains how to use Academie respectfully, what content is not allowed, and how the project team may respond to reports.</p>
<h2>1. Respectful use</h2><p>Use text-entry and learning features for their intended educational purpose. Keep submissions relevant, respectful, and lawful.</p>
<h2>2. Prohibited content and activity</h2><p>Do not submit or share threats, harassment, hateful or discriminatory content, unlawful material, spam, malicious code, or content that violates another person's rights. Do not share another person's private information without permission.</p>
<h2>3. Review of submissions</h2><p>The project team may review submitted content when needed to operate, maintain, or protect the website and its users. Some features may not be monitored continuously, and automated moderation may not be available.</p>
<h2>4. Reporting a concern</h2><p>To report content or behavior that may be harmful or inappropriate, email <a href="mailto:rhiniellcolumbres@gmail.com">rhiniellcolumbres@gmail.com</a>. Include the page or feature, where the content appears, and a brief explanation. Do not include unnecessary sensitive information.</p>
<h2>5. Removal and restrictions</h2><p>After reviewing a concern, the project team may remove content, limit access to a feature, or restrict an account if reasonably needed to address misuse, safety concerns, or technical problems. Actions depend on the circumstances and available information.</p>
<h2>6. Review timing</h2><p>This is an academic demonstration project. Reports may not be reviewed immediately, and the project team cannot guarantee a response or a particular response time.</p>
<h2>7. AI-generated material</h2><p>AI-generated explanations and recommendations can be inaccurate, incomplete, or unsuitable. Check important information with reliable sources and report harmful or clearly inappropriate output using the contact details above.</p>
</article></main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
