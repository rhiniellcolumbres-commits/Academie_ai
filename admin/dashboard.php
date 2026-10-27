<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../includes/db.php';

$totalUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalAssessments = (int)$pdo->query('SELECT COUNT(*) FROM assessments')->fetchColumn();
$totalRoadmaps = (int)$pdo->query("SELECT COUNT(*) FROM ai_usage_logs WHERE usage_type='assessment_roadmap'")->fetchColumn();
$totalLessons = (int)$pdo->query("SELECT COUNT(*) FROM ai_usage_logs WHERE usage_type='ai_lesson'")->fetchColumn();
$fallbackRequests = (int)$pdo->query("SELECT COUNT(*) FROM ai_usage_logs WHERE status='fallback'")->fetchColumn();
$failedRequests = (int)$pdo->query("SELECT COUNT(*) FROM ai_usage_logs WHERE status='failed'")->fetchColumn();
$loginsToday = (int)$pdo->query("SELECT COUNT(*) FROM user_activity_logs WHERE event_type='login' AND created_at >= CURDATE()")->fetchColumn();

$usage = $pdo->query("SELECT u.id, u.first_name, u.last_name, u.email,
        COUNT(DISTINCT a.id) AS assessment_count,
        SUM(CASE WHEN l.usage_type='assessment_roadmap' THEN 1 ELSE 0 END) AS roadmap_uses,
        SUM(CASE WHEN l.usage_type='ai_lesson' THEN 1 ELSE 0 END) AS lesson_uses,
        MAX(l.created_at) AS last_ai_use
    FROM users u
    LEFT JOIN assessments a ON a.user_id=u.id
    LEFT JOIN ai_usage_logs l ON l.user_id=u.id
    GROUP BY u.id, u.first_name, u.last_name, u.email
    ORDER BY last_ai_use DESC, u.id DESC")->fetchAll(PDO::FETCH_ASSOC);

$aiActivity = $pdo->query("SELECT l.usage_type, l.status, l.details, l.created_at, u.first_name, u.last_name, u.email
    FROM ai_usage_logs l INNER JOIN users u ON u.id=l.user_id
    ORDER BY l.created_at DESC, l.id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

$activities = $pdo->query("SELECT al.event_type, al.created_at, al.ip_address, u.first_name, u.last_name, u.email
    FROM user_activity_logs al INNER JOIN users u ON u.id=al.user_id
    ORDER BY al.created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);

$contentFeedback = [];
$feedbackTableReady = true;
try {
    $contentFeedback = $pdo->query("SELECT f.content_type, f.rating, f.reason, f.context_label, f.created_at,
            u.first_name, u.last_name, u.email
        FROM ai_content_feedback f INNER JOIN users u ON u.id=f.user_id
        ORDER BY f.created_at DESC, f.id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $feedbackTableReady = false;
}

$studentSearch = trim((string)($_GET['student_q'] ?? ''));
$studentSql = "SELECT u.id, u.first_name, u.last_name, u.email, u.created_at, a.career_goal,
        (SELECT COUNT(*) FROM study_topics st INNER JOIN study_paths sp ON sp.id=st.study_path_id WHERE sp.user_id=u.id) AS total_topics,
        (SELECT COUNT(*) FROM study_topics st INNER JOIN study_paths sp ON sp.id=st.study_path_id WHERE sp.user_id=u.id AND st.completed=1) AS completed_topics,
        (SELECT MAX(al.created_at) FROM user_activity_logs al WHERE al.user_id=u.id AND al.event_type='login') AS last_login
    FROM users u LEFT JOIN assessments a ON a.user_id=u.id";
$studentParams = [];
if ($studentSearch !== '') {
    $studentSql .= " WHERE u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR CAST(u.id AS CHAR) LIKE ?";
    $searchTerm = '%' . $studentSearch . '%';
    $studentParams = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
}
$studentSql .= ' ORDER BY u.created_at DESC LIMIT 300';
$studentStmt = $pdo->prepare($studentSql);
$studentStmt->execute($studentParams);
$students = $studentStmt->fetchAll(PDO::FETCH_ASSOC);

// Escapes a value before displaying it as HTML text.
function h($value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Academie Admin Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{background:#f5f7fb;color:#202938}.navbar{background:#fff;border-bottom:1px solid #e6e9ef}.stat{border:0;border-radius:14px}.table-wrap{background:#fff;border-radius:14px;padding:18px;box-shadow:0 2px 10px #16213e0a}.muted{color:#6b7280;font-size:.9rem}.admin-tool-buttons{display:flex;flex-wrap:wrap;gap:8px}.admin-tool-button{border:1px solid #d5dce7;border-radius:9px;padding:9px 14px;background:#fff;color:#334155;font-weight:650}.admin-tool-button:hover,.admin-tool-button[aria-pressed=true]{border-color:#245fbd;background:#eef4ff;color:#174ea6}.admin-tool-panel[hidden]{display:none!important}</style></head><body>
<?php require __DIR__ . '/_nav.php'; ?>
<main class="container-fluid px-4 py-4">
<h1 class="h3 mb-1">Academie Admin Dashboard</h1><p class="muted mb-4">A quick overview of student activity, learning progress, and AI feature health.</p>
<div class="row g-3 mb-4">
<?php foreach ([['Students',$totalUsers],['Assessments',$totalAssessments],['Roadmap requests',$totalRoadmaps],['Lesson requests',$totalLessons],['AI fallbacks',$fallbackRequests],['AI failures',$failedRequests],['Logins today',$loginsToday]] as $stat): ?>
<div class="col-6 col-md"><div class="card stat shadow-sm h-100"><div class="card-body"><div class="muted"><?= h($stat[0]) ?></div><div class="display-6 fw-semibold"><?= number_format((int)$stat[1]) ?></div></div></div></div>
<?php endforeach; ?>
</div>
<section class="table-wrap" aria-labelledby="admin-tools-title"><h2 id="admin-tools-title" class="h5 mb-2">Admin tools</h2><p class="muted">Choose a tool to show its content below.</p>
<div class="admin-tool-buttons" role="group" aria-label="Admin tools">
<button type="button" class="admin-tool-button" data-tool-target="students" aria-pressed="false">Find a student</button>
<button type="button" class="admin-tool-button" data-tool-target="student-usage" aria-pressed="false">Student AI usage</button>
<button type="button" class="admin-tool-button" data-tool-target="ai-activity" aria-pressed="false">AI activity</button>
<button type="button" class="admin-tool-button" data-tool-target="feedback" aria-pressed="false">Student feedback</button>
<button type="button" class="admin-tool-button" data-tool-target="account-activity" aria-pressed="false">Sign-in activity</button>
</div></section>
<div id="admin-tool-content" class="mt-4" aria-live="polite">
<p class="muted" id="admin-tool-placeholder">Select an admin tool above. Its information will appear here without leaving the dashboard.</p>
<section class="table-wrap admin-tool-panel" data-tool-panel="students" hidden><h2 class="h5 mb-3">Find a student</h2>
<form method="get" class="d-flex flex-wrap gap-2 mb-3"><input type="hidden" name="tool" value="students"><input class="form-control" style="max-width:420px" name="student_q" value="<?= h($studentSearch) ?>" placeholder="Search name, email, or student ID"><button class="btn btn-primary">Search</button><?php if ($studentSearch !== ''): ?><a class="btn btn-outline-secondary" href="dashboard?tool=students">Clear</a><?php endif; ?></form>
<div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>ID</th><th>Student</th><th>Email</th><th>Target career</th><th>Roadmap progress</th><th>Last login</th><th>Registered</th></tr></thead><tbody>
<?php if (!$students): ?><tr><td colspan="7" class="text-center text-secondary py-4">No matching student accounts.</td></tr><?php endif; ?>
<?php foreach ($students as $student): $total=(int)$student['total_topics']; $done=(int)$student['completed_topics']; $percent=$total>0?(int)round(($done/$total)*100):0; ?><tr><td><?= (int)$student['id'] ?></td><td class="fw-semibold"><?= h(trim($student['first_name'].' '.$student['last_name'])) ?></td><td><?= h($student['email']) ?></td><td><?= h($student['career_goal'] ?: 'Not set') ?></td><td style="min-width:150px"><div class="small mb-1"><?= $done ?> / <?= $total ?> topics (<?= $percent ?>%)</div><div class="progress" role="progressbar" aria-label="Learning progress" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width:<?= $percent ?>%"></div></div></td><td><?= h($student['last_login'] ?: 'No login recorded') ?></td><td><?= h($student['created_at']) ?></td></tr><?php endforeach; ?>
</tbody></table></div><p class="muted mb-0">Passwords are never displayed here.</p></section>
<section class="table-wrap admin-tool-panel" data-tool-panel="student-usage" hidden><h2 class="h5 mb-3">Student AI usage</h2><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Student</th><th>Email</th><th>Assessments</th><th>Roadmap generations</th><th>AI lesson requests</th><th>Last AI use</th></tr></thead><tbody>
<?php if (!$usage): ?><tr><td colspan="6" class="text-center text-muted py-4">No students found.</td></tr><?php endif; ?><?php foreach ($usage as $row): ?><tr><td><?= h(trim($row['first_name'].' '.$row['last_name'])) ?></td><td><?= h($row['email']) ?></td><td><?= (int)$row['assessment_count'] ?></td><td><?= (int)$row['roadmap_uses'] ?></td><td><?= (int)$row['lesson_uses'] ?></td><td><?= h($row['last_ai_use'] ?: 'Never') ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<section class="table-wrap admin-tool-panel" data-tool-panel="ai-activity" hidden><h2 class="h5 mb-3">Roadmap and lesson activity <span class="muted">Latest 50 requests</span></h2><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Student</th><th>Email</th><th>Feature</th><th>Result</th><th>Details</th><th>Date &amp; Time</th></tr></thead><tbody>
<?php if (!$aiActivity): ?><tr><td colspan="6" class="text-center text-muted py-4">No AI usage recorded yet.</td><?php endif; ?><?php foreach ($aiActivity as $row): ?><tr><td><?= h(trim($row['first_name'].' '.$row['last_name'])) ?></td><td><?= h($row['email']) ?></td><td><?= $row['usage_type']==='ai_lesson'?'AI lesson':'Roadmap' ?></td><td><span class="badge <?= $row['status']==='success'?'text-bg-success':($row['status']==='failed'?'text-bg-danger':($row['status']==='fallback'?'text-bg-warning':'text-bg-secondary')) ?>"><?= h(ucfirst($row['status'])) ?></span></td><td><?= h($row['details']) ?></td><td><?= h($row['created_at']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<section class="table-wrap admin-tool-panel" data-tool-panel="feedback" hidden><h2 class="h5 mb-3">AI content feedback <span class="muted">Latest 100 responses</span></h2>
<?php if (!$feedbackTableReady): ?><div class="alert alert-info mb-0">Run <code>database/ai_content_feedback.sql</code> once to enable student feedback storage.</div><?php else: ?><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Student</th><th>Content</th><th>Rating</th><th>Reason</th><th>Item</th><th>Date &amp; Time</th></tr></thead><tbody>
<?php if (!$contentFeedback): ?><tr><td colspan="6" class="text-center text-muted py-4">No student feedback yet.</td><?php endif; ?><?php foreach ($contentFeedback as $row): ?><tr><td><?= h(trim($row['first_name'].' '.$row['last_name'])) ?><div class="muted"><?= h($row['email']) ?></div></td><td><?= $row['content_type']==='lesson'?'AI lesson':'Roadmap' ?></td><td><span class="badge <?= (int)$row['rating']>0?'text-bg-success':'text-bg-warning' ?>"><?= (int)$row['rating']>0?'Helpful':'Needs improvement' ?></span></td><td><?= h($row['reason']?ucwords(str_replace('_',' ',$row['reason'])):'—') ?></td><td><?= h($row['context_label']) ?></td><td><?= h($row['created_at']) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></section>
<section class="table-wrap admin-tool-panel" data-tool-panel="account-activity" hidden><h2 class="h5 mb-3">Recent student sign-in activity <span class="muted">Latest 100 events</span></h2><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Student</th><th>Email</th><th>Activity</th><th>Date &amp; Time</th><th>IP Address</th></tr></thead><tbody>
<?php if (!$activities): ?><tr><td colspan="5" class="text-center text-muted py-4">No login/logout activity recorded yet.</td><?php endif; ?><?php foreach ($activities as $row): ?><tr><td><?= h(trim($row['first_name'].' '.$row['last_name'])) ?></td><td><?= h($row['email']) ?></td><td><span class="badge <?= $row['event_type']==='login'?'text-bg-success':'text-bg-secondary' ?>"><?= h(strtoupper($row['event_type'])) ?></span></td><td><?= h($row['created_at']) ?></td><td><?= h($row['ip_address']?:'N/A') ?></td></tr><?php endforeach; ?>
</tbody></table></div><p class="muted mb-0">IP addresses are logged to help audit access.</p></section>
</div></main>
<script>
// Sets up admin dashboard tool switching after the page is ready.
document.addEventListener('DOMContentLoaded', function () {
    const buttons = document.querySelectorAll('[data-tool-target]');
    const panels = document.querySelectorAll('[data-tool-panel]');
    const placeholder = document.getElementById('admin-tool-placeholder');
    // Shows the selected admin tool panel and updates the selected button state.
    function showTool(name) {
        let found = false;
        // Shows the panel whose name matches the selected tool.
        panels.forEach(function (panel) {
            const active = panel.dataset.toolPanel === name;
            panel.hidden = !active;
            if (active) found = true;
        });
        // Marks only the button for the selected tool as pressed.
        buttons.forEach(function (button) {
            button.setAttribute('aria-pressed', String(button.dataset.toolTarget === name));
        });
        placeholder.hidden = found;
    }
    // Connects each tool button to the panel switching function.
    buttons.forEach(function (button) {
        // Opens the panel named by the clicked button.
        button.addEventListener('click', function () { showTool(button.dataset.toolTarget); });
    });
    const requestedTool = new URLSearchParams(window.location.search).get('tool');
    if (requestedTool) showTool(requestedTool);
});
</script></body></html>
