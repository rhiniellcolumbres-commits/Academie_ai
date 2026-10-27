<?php
$pageTitle = 'Terms & Conditions';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/public-navbar.php';
$backUrl = !empty($_SESSION['user_id']) ? 'dashboard' : './';
?>
<div class="legal-back-wrap"><a class="legal-back-button" href="<?= e($backUrl) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back</a></div>
<main class="legal-page"><article class="legal-card">
<span class="legal-eyebrow">TERMS</span><h1>Terms & Conditions</h1>
<p class="legal-updated">Last Updated: October 14, 2026</p>
<p class="legal-disclaimer"><strong>Disclaimer:</strong> Academie is developed for demonstration and academic purposes as part of a student project. It is not an official educational institution or professional advisory service.</p>
<p>By accessing or using Academie, you agree to follow these terms and use the website responsibly. If you do not agree, do not use the website.</p>
<h2>1. Academic purpose</h2><p>Academie is a student project for demonstration and academic purposes. It is not an official educational institution, accredited training provider, or professional career-advisory service.</p>
<h2>2. Educational suggestions</h2><p>Learning paths, lesson explanations, activities, and AI-generated recommendations are general learning aids. They may be incomplete, outdated, or incorrect. Check important information with reliable sources, instructors, or qualified advisers.</p>
<h2>3. Account responsibilities</h2><p>Keep your sign-in details private and tell the project team if you believe someone accessed your account without permission. Do not access another person's account or data.</p>
<h2>4. Acceptable use</h2><p>Use the website for lawful, intended purposes. Do not disrupt its operation, attempt unauthorized access, submit unlawful or harmful content, or violate another person's rights.</p>
<h2>5. Third-party services</h2><p>Some features rely on third-party hosting, authentication, or AI services. Those services may become unavailable and are governed by their own terms and policies.</p>
<h2>6. Availability</h2><p>The website is provided “as is” and “as available” for demonstration. Features may change, contain errors, or become unavailable during development. Uninterrupted access is not guaranteed.</p>
<h2>7. Educational content</h2><p>To the extent permitted by applicable law, the project team does not guarantee the accuracy or suitability of website content. It is not a substitute for official information or professional advice.</p>
<h2>8. Changes and access</h2><p>These terms may be updated as the project develops. The latest revision date appears above. The project team may suspend or restrict access when reasonably needed to protect the website or its users.</p>
<h2>9. Contact</h2><p>For questions about these terms, contact <a href="mailto:rhiniellcolumbres@gmail.com">rhiniellcolumbres@gmail.com</a>.</p>
</article></main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
