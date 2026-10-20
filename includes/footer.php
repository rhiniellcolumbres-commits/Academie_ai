<?php
/**
 * Shared closing markup for Academie pages.
 * Builds root-relative legal-page links for both root and nested routes, then
 * closes the document and loads Bootstrap's JavaScript bundle.
 */
// Shared footer for public and authenticated pages.
// Build links from the project root so they also work on nested pages (for example admin/index.php).
$scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$scriptDirectory = rtrim($scriptDirectory, '/');
if (basename($scriptDirectory) === 'admin') {
    $scriptDirectory = rtrim(dirname($scriptDirectory), '/');
}
$appBaseUrl = ($scriptDirectory === '' || $scriptDirectory === '.') ? '' : $scriptDirectory;
?>
<footer class="site-footer">
    <div class="site-footer-inner">
        <aside class="site-disclaimer" aria-label="Academic project disclaimer">
            <strong>Disclaimer:</strong>
            Academie is developed for demonstration and academic purposes as part of a student project.
            It is not an official educational institution or professional advisory service.
            AI-generated learning paths and study materials are provided for educational guidance only and may require verification.
        </aside>

        <nav class="site-footer-links" aria-label="Footer navigation">
            <a href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/privacy">Privacy Policy</a>
            <a href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/terms">Terms</a>
            <a href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/moderation">Moderation</a>
            <a href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/copyright">Copyright</a>
        </nav>

        <p class="site-copyright">&copy; <?= date('Y') ?> Academie</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
