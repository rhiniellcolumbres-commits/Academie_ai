<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../includes/db.php';

/** Escape a value before displaying it in the admin students table. */
function admin_students_h($value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8'); }
$search = trim((string)($_GET['q'] ?? ''));
$sql = "SELECT u.id, u.first_name, u.last_name, u.email, u.created_at,
               a.career_goal,
               sp.title AS roadmap_title,
               (SELECT COUNT(*) FROM study_topics st WHERE st.study_path_id = sp.id) AS total_topics,
               (SELECT COUNT(*) FROM study_topics st WHERE st.study_path_id = sp.id AND st.completed = 1) AS completed_topics,
               (SELECT MAX(al.created_at) FROM user_activity_logs al WHERE al.user_id = u.id AND al.event_type = 'login') AS last_login
        FROM users u
        LEFT JOIN assessments a ON a.user_id = u.id
        LEFT JOIN study_paths sp ON sp.user_id = u.id";
$params = [];
if ($search !== '') {
    $sql .= " WHERE u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR CAST(u.id AS CHAR) LIKE ?";
    $term = '%' . $search . '%';
    $params = [$term, $term, $term, $term];
}
$sql .= ' ORDER BY u.created_at DESC LIMIT 300';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();
$totalStudents = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Student Management | StudyPath Admin</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{background:#f5f7fb;color:#202938}.panel{background:#fff;border:1px solid #e7eaf0;border-radius:14px;padding:18px}.muted{color:#6b7280;font-size:.9rem}</style></head><body>
<?php require __DIR__ . '/_nav.php'; ?>
<main class="container-fluid px-4 py-4"><h1 class="h3 mb-1">Student Management</h1><p class="muted mb-4">Search student accounts and review their learning goals, roadmap progress, and latest login.</p>
<div class="panel mb-4"><div class="d-flex flex-wrap align-items-center justify-content-between gap-3"><div><div class="muted">Registered student accounts</div><div class="display-6 fw-semibold"><?= number_format($totalStudents) ?></div></div><form method="get" class="d-flex gap-2" style="min-width:min(100%,420px)"><input class="form-control" name="q" value="<?= admin_students_h($search) ?>" placeholder="Search name, email, or student ID"><button class="btn btn-primary">Search</button><?php if ($search !== ''): ?><a class="btn btn-outline-secondary" href="students">Clear</a><?php endif; ?></form></div></div>
<section class="panel"><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>ID</th><th>Student</th><th>Email</th><th>Target career</th><th>Roadmap progress</th><th>Last login</th><th>Registered</th></tr></thead><tbody>
<?php if (!$students): ?><tr><td colspan="7" class="text-center text-secondary py-4">No matching student accounts.</td></tr><?php endif; ?>
<?php foreach ($students as $student): $total = (int)$student['total_topics']; $done = (int)$student['completed_topics']; $percent = $total > 0 ? (int)round(($done / $total) * 100) : 0; ?><tr><td><?= (int)$student['id'] ?></td><td class="fw-semibold"><?= admin_students_h(trim($student['first_name'].' '.$student['last_name'])) ?></td><td><?= admin_students_h($student['email']) ?></td><td><?= admin_students_h($student['career_goal'] ?: 'Not set') ?></td><td style="min-width:150px"><div class="small mb-1"><?= $done ?> / <?= $total ?> topics (<?= $percent ?>%)</div><div class="progress" role="progressbar" aria-label="Learning progress" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width:<?= $percent ?>%"></div></div></td><td><?= admin_students_h($student['last_login'] ?: 'No login recorded') ?></td><td><?= admin_students_h($student['created_at']) ?></td></tr><?php endforeach; ?>
</tbody></table></div><p class="muted mb-0">Passwords are never displayed here.</p></section></main></body></html>
