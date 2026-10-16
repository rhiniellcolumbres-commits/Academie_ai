<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['admin_support_csrf'])) $_SESSION['admin_support_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['admin_support_csrf'];
$notice = '';
$error = '';
function admin_support_h($value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session check expired. Refresh and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        if ($ticketId < 1) {
            $error = 'Invalid support ticket.';
        } elseif ($action === 'reply') {
            $message = trim((string)($_POST['message'] ?? ''));
            if ($message === '' || mb_strlen($message) > 5000) {
                $error = 'Enter a reply of up to 5,000 characters.';
            } else {
                $stmt = $pdo->prepare('SELECT id FROM support_tickets WHERE id = ?');
                $stmt->execute([$ticketId]);
                if (!$stmt->fetchColumn()) {
                    $error = 'Support ticket not found.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO support_ticket_messages (ticket_id, sender_type, sender_id, message) VALUES (?, 'admin', ?, ?)");
                    $stmt->execute([$ticketId, (int)$_SESSION['admin_id'], $message]);
                    $stmt = $pdo->prepare("UPDATE support_tickets SET status = CASE WHEN status = 'resolved' THEN 'in_progress' ELSE status END WHERE id = ?");
                    $stmt->execute([$ticketId]);
                    $log = $pdo->prepare('INSERT INTO admin_action_logs (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)');
                    $log->execute([(int)$_SESSION['admin_id'], 'support_reply', 'Replied to ticket #' . $ticketId, $_SERVER['REMOTE_ADDR'] ?? null]);
                    header('Location: support.php?ticket=' . $ticketId . '&sent=1'); exit;
                }
            }
        } elseif ($action === 'status') {
            $status = (string)($_POST['status'] ?? 'open');
            if (!in_array($status, ['open','in_progress','resolved'], true)) {
                $error = 'Invalid ticket status.';
            } else {
                $stmt = $pdo->prepare('UPDATE support_tickets SET status = ? WHERE id = ?');
                $stmt->execute([$status, $ticketId]);
                $log = $pdo->prepare('INSERT INTO admin_action_logs (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)');
                $log->execute([(int)$_SESSION['admin_id'], 'support_status_change', 'Ticket #' . $ticketId . ' set to ' . $status, $_SERVER['REMOTE_ADDR'] ?? null]);
                header('Location: support.php?ticket=' . $ticketId . '&updated=1'); exit;
            }
        }
    }
}
if (isset($_GET['sent'])) $notice = 'Reply sent to the student.';
if (isset($_GET['updated'])) $notice = 'Ticket status updated.';

$filter = (string)($_GET['status'] ?? 'all');
$where = '';
$params = [];
if (in_array($filter, ['open','in_progress','resolved'], true)) { $where = 'WHERE t.status = ?'; $params[] = $filter; }
$stmt = $pdo->prepare("SELECT t.id,t.category,t.subject,t.status,t.created_at,t.updated_at,u.first_name,u.last_name,u.email FROM support_tickets t INNER JOIN users u ON u.id=t.user_id $where ORDER BY FIELD(t.status,'open','in_progress','resolved'), t.updated_at DESC");
$stmt->execute($params); $tickets = $stmt->fetchAll();
$selectedId = (int)($_GET['ticket'] ?? ($tickets[0]['id'] ?? 0));
$selected = null; $messages = [];
if ($selectedId > 0) {
    $stmt = $pdo->prepare('SELECT t.*,u.first_name,u.last_name,u.email FROM support_tickets t INNER JOIN users u ON u.id=t.user_id WHERE t.id = ?');
    $stmt->execute([$selectedId]); $selected = $stmt->fetch();
    if ($selected) { $stmt = $pdo->prepare('SELECT sender_type,message,created_at FROM support_ticket_messages WHERE ticket_id = ? ORDER BY created_at ASC,id ASC'); $stmt->execute([$selectedId]); $messages = $stmt->fetchAll(); }
}
$openCount = (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status='open'")->fetchColumn();
$progressCount = (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status='in_progress'")->fetchColumn();
$resolvedCount = (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status='resolved'")->fetchColumn();
$openSupportTickets = $openCount + $progressCount;
$categories = ['login'=>'Login','account'=>'Account','ai_roadmap'=>'AI roadmap','ai_lesson'=>'AI lesson','progress'=>'Progress','other'=>'Other'];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Student Support | StudyPath Admin</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{background:#f5f7fb;color:#202938}.panel{background:#fff;border:1px solid #e7eaf0;border-radius:14px;padding:18px}.ticket-link{display:block;text-decoration:none;color:inherit;padding:12px;border-radius:10px}.ticket-link.active,.ticket-link:hover{background:#edf4ff}.chat-message{max-width:92%;white-space:pre-wrap;overflow-wrap:anywhere;padding:12px 14px;border-radius:12px;margin-bottom:12px}.student-message{background:#f1f3f5;margin-right:auto}.admin-message{background:#edf4ff;margin-left:auto}.muted{font-size:.88rem;color:#6b7280}</style></head><body>
<?php require __DIR__ . '/_nav.php'; ?>
<main class="container-fluid px-4 py-4"><h1 class="h3 mb-1">Student Support Center</h1><p class="muted mb-4">Reply to student reports and track account or learning issues.</p>
<?php if ($notice): ?><div class="alert alert-success"><?= admin_support_h($notice) ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?= admin_support_h($error) ?></div><?php endif; ?>
<div class="row g-3 mb-4"><div class="col-sm-4"><div class="panel"><div class="muted">Open</div><div class="display-6 fw-semibold"><?= $openCount ?></div></div></div><div class="col-sm-4"><div class="panel"><div class="muted">In progress</div><div class="display-6 fw-semibold"><?= $progressCount ?></div></div></div><div class="col-sm-4"><div class="panel"><div class="muted">Resolved</div><div class="display-6 fw-semibold"><?= $resolvedCount ?></div></div></div></div>
<div class="row g-4"><div class="col-xl-4"><section class="panel"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">Student requests</h2><form method="get"><select name="status" class="form-select form-select-sm" onchange="this.form.submit()"><option value="all" <?= $filter==='all'?'selected':'' ?>>All statuses</option><option value="open" <?= $filter==='open'?'selected':'' ?>>Open</option><option value="in_progress" <?= $filter==='in_progress'?'selected':'' ?>>In progress</option><option value="resolved" <?= $filter==='resolved'?'selected':'' ?>>Resolved</option></select></form></div><?php if (!$tickets): ?><p class="text-secondary">No support tickets found.</p><?php else: ?><?php foreach ($tickets as $t): ?><a class="ticket-link <?= $selected && (int)$selected['id']===(int)$t['id']?'active':'' ?>" href="support.php?status=<?= admin_support_h($filter) ?>&ticket=<?= (int)$t['id'] ?>"><div class="fw-semibold">#<?= (int)$t['id'] ?> · <?= admin_support_h($t['subject']) ?></div><div class="muted"><?= admin_support_h(trim($t['first_name'].' '.$t['last_name'])) ?> · <?= admin_support_h($t['email']) ?></div><div class="muted"><?= admin_support_h($categories[$t['category']] ?? 'Other') ?> · <?= admin_support_h(str_replace('_',' ',ucfirst($t['status']))) ?></div></a><?php endforeach; ?><?php endif; ?></section></div>
<div class="col-xl-8"><section class="panel"><?php if (!$selected): ?><h2 class="h5">Ticket conversation</h2><p class="text-secondary">Select a student request to review and reply.</p><?php else: ?><div class="d-flex flex-wrap justify-content-between gap-3"><div><h2 class="h5 mb-1">#<?= (int)$selected['id'] ?> · <?= admin_support_h($selected['subject']) ?></h2><div class="muted"><?= admin_support_h(trim($selected['first_name'].' '.$selected['last_name'])) ?> · <?= admin_support_h($selected['email']) ?></div><div class="muted">Category: <?= admin_support_h($categories[$selected['category']] ?? 'Other') ?> · Created <?= admin_support_h($selected['created_at']) ?></div></div><form method="post" class="d-flex align-items-start gap-2"><input type="hidden" name="csrf_token" value="<?= admin_support_h($csrf) ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="ticket_id" value="<?= (int)$selected['id'] ?>"><select class="form-select form-select-sm" name="status"><option value="open" <?= $selected['status']==='open'?'selected':'' ?>>Open</option><option value="in_progress" <?= $selected['status']==='in_progress'?'selected':'' ?>>In progress</option><option value="resolved" <?= $selected['status']==='resolved'?'selected':'' ?>>Resolved</option></select><button class="btn btn-outline-primary btn-sm">Update</button></form></div><hr><div class="border rounded-3 p-3 mb-3" style="min-height:180px;max-height:520px;overflow:auto"><?php foreach ($messages as $m): ?><div class="chat-message <?= $m['sender_type']==='admin'?'admin-message':'student-message' ?>"><div class="muted mb-1"><?= $m['sender_type']==='admin'?'You (Admin)':'Student' ?> · <?= admin_support_h($m['created_at']) ?></div><?= admin_support_h($m['message']) ?></div><?php endforeach; ?></div><form method="post"><input type="hidden" name="csrf_token" value="<?= admin_support_h($csrf) ?>"><input type="hidden" name="action" value="reply"><input type="hidden" name="ticket_id" value="<?= (int)$selected['id'] ?>"><label class="form-label" for="reply">Reply to student</label><textarea class="form-control mb-3" id="reply" name="message" rows="3" maxlength="5000" required></textarea><button class="btn btn-primary">Send Reply</button></form><?php endif; ?></section></div></div></main></body></html>
