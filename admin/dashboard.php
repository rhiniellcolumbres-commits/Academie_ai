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

$usage = $pdo->query("
    SELECT u.id, u.first_name, u.last_name, u.email,
           COUNT(DISTINCT a.id) AS assessment_count,
           SUM(CASE WHEN l.usage_type='assessment_roadmap' THEN 1 ELSE 0 END) AS roadmap_uses,
           SUM(CASE WHEN l.usage_type='ai_lesson' THEN 1 ELSE 0 END) AS lesson_uses,
           MAX(l.created_at) AS last_ai_use
    FROM users u
    LEFT JOIN assessments a ON a.user_id=u.id
    LEFT JOIN ai_usage_logs l ON l.user_id=u.id
    GROUP BY u.id, u.first_name, u.last_name, u.email
    ORDER BY last_ai_use DESC, u.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$activities = $pdo->query("
    SELECT al.event_type, al.created_at, al.ip_address, u.first_name, u.last_name, u.email
    FROM user_activity_logs al
    INNER JOIN users u ON u.id=al.user_id
    ORDER BY al.created_at DESC
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

$aiActivity = $pdo->query("
    SELECT l.usage_type, l.status, l.details, l.created_at, u.first_name, u.last_name, u.email
    FROM ai_usage_logs l
    INNER JOIN users u ON u.id=l.user_id
    ORDER BY l.created_at DESC, l.id DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

$lastAiUse = $aiActivity[0]['created_at'] ?? null;

// Escapes text for safe HTML output.
function h($value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Dashboard | StudyPath</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{background:#f5f7fb;color:#202938}.navbar{background:#fff;border-bottom:1px solid #e6e9ef}.stat{border:0;border-radius:14px}.table-wrap{background:#fff;border-radius:14px;padding:18px;box-shadow:0 2px 10px #16213e0a}.muted{color:#6b7280;font-size:.9rem}</style></head><body>
<?php require __DIR__ . '/_nav.php'; ?>
<main class="container-fluid px-4 py-4">
<h1 class="h3 mb-1">Admin Dashboard</h1><p class="muted mb-4">A quick view of student activity, learning progress, and AI feature health.</p>
<div class="row g-3 mb-4">
<?php foreach ([['Students',$totalUsers],['Assessments',$totalAssessments],['Roadmap requests',$totalRoadmaps],['Lesson requests',$totalLessons],['AI fallbacks',$fallbackRequests],['AI failures',$failedRequests],['Logins today',$loginsToday]] as $stat): ?>
<div class="col-6 col-md"><div class="card stat shadow-sm h-100"><div class="card-body"><div class="muted"><?= h($stat[0]) ?></div><div class="display-6 fw-semibold"><?= number_format((int)$stat[1]) ?></div></div></div></div>
<?php endforeach; ?>
</div>
<section class="table-wrap mb-4" aria-labelledby="admin-actions-title"><h2 id="admin-actions-title" class="h5 mb-3">Admin tools</h2><div class="d-flex flex-wrap gap-2"><a class="btn btn-primary btn-sm" href="students">Find a student</a><a class="btn btn-outline-secondary btn-sm" href="#ai-activity">Review AI activity</a><a class="btn btn-outline-secondary btn-sm" href="#account-activity">Review sign-in activity</a></div><p class="muted mt-3 mb-0">Student records are read-only here. Roadmaps and lessons are generated from student assessments and topic progress.</p></section>
<section class="table-wrap mb-4"><div class="d-flex flex-wrap justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">Student AI Usage</h2><span class="muted">Counts are recorded from app events</span></div>
<div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Student</th><th>Email</th><th>Assessments</th><th>Roadmap generations</th><th>AI lesson requests</th><th>Last AI use</th></tr></thead><tbody>
<?php if (!$usage): ?><tr><td colspan="6" class="text-center text-muted py-4">No students found.</td></tr><?php endif; ?>
<?php foreach ($usage as $row): ?><tr><td><?= h(trim($row['first_name'].' '.$row['last_name'])) ?></td><td><?= h($row['email']) ?></td><td><?= (int)$row['assessment_count'] ?></td><td><?= (int)$row['roadmap_uses'] ?></td><td><?= (int)$row['lesson_uses'] ?></td><td><?= h($row['last_ai_use'] ?: 'Never') ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<section class="table-wrap mb-4" id="ai-activity"><div class="d-flex flex-wrap justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">Roadmap and lesson activity</h2><span class="muted">Last AI use: <?= h($lastAiUse ?: 'Never') ?> &middot; latest 50 requests</span></div>
<div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Student</th><th>Email</th><th>Feature</th><th>Result</th><th>Details</th><th>Date &amp; Time</th></tr></thead><tbody>
<?php if (!$aiActivity): ?><tr><td colspan="6" class="text-center text-muted py-4">No AI usage recorded yet. New roadmap and lesson requests will appear here.</td></tr><?php endif; ?>
<?php foreach ($aiActivity as $row): ?><tr><td><?= h(trim($row['first_name'].' '.$row['last_name'])) ?></td><td><?= h($row['email']) ?></td><td><?= $row['usage_type']==='ai_lesson' ? 'AI Lesson' : 'Roadmap' ?></td><td><span class="badge <?= $row['status']==='success' ? 'text-bg-success' : ($row['status']==='failed' ? 'text-bg-danger' : ($row['status']==='fallback' ? 'text-bg-warning' : 'text-bg-secondary')) ?>"><?= h(ucfirst($row['status'])) ?></span></td><td><?= h($row['details']) ?></td><td><?= h($row['created_at']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<section class="table-wrap" id="account-activity"><div class="d-flex flex-wrap justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">Recent student sign-in activity</h2><span class="muted">Latest 100 events</span></div>
<div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Student</th><th>Email</th><th>Activity</th><th>Date &amp; Time</th><th>IP Address</th></tr></thead><tbody>
<?php if (!$activities): ?><tr><td colspan="5" class="text-center text-muted py-4">No login/logout activity recorded yet. New activity will appear here.</td></tr><?php endif; ?>
<?php foreach ($activities as $row): ?><tr><td><?= h(trim($row['first_name'].' '.$row['last_name'])) ?></td><td><?= h($row['email']) ?></td><td><span class="badge <?= $row['event_type']==='login'?'text-bg-success':'text-bg-secondary' ?>"><?= h(strtoupper($row['event_type'])) ?></span></td><td><?= h($row['created_at']) ?></td><td><?= h($row['ip_address'] ?: 'N/A') ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<p class="muted mt-3">Privacy note: use these records only for legitimate system administration. IP addresses are logged to help audit access.</p>
</main></body></html>
