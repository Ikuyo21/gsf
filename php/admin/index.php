<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/layout.php';
require_admin();

$base = base_url();
$tab = $_GET['tab'] ?? 'reports';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['action'] ?? ''), ['approve_user', 'reject_user'], true)) {
    $csrf = $_POST['csrf'] ?? '';
    if (!verify_csrf($csrf)) {
        set_flash('error', 'Invalid token.');
    } else {
        $target = (int) ($_POST['user_id'] ?? 0);
        if ($target <= 0) {
            set_flash('error', 'Invalid user.');
        } elseif ($_POST['action'] === 'approve_user') {
            $stmt = $pdo->prepare('UPDATE users SET approved = 1 WHERE id = ? AND approved = 0 AND role = "Student"');
            $stmt->execute([$target]);
            set_flash('success', $stmt->rowCount() ? 'Student approved.' : 'No pending student matched.');
        } else {
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = ? AND approved = 0 AND role = "Student"');
            $stmt->execute([$target]);
            set_flash('success', $stmt->rowCount() ? 'Registration rejected.' : 'No pending student matched.');
        }
    }
    header('Location: ' . $base . '/php/admin/?tab=pending');
    exit;
}

$stmt = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'");
$pending_reports = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Student' AND approved = 1");
$total_users = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Student' AND approved = 0");
$pending_approvals = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Student' AND approved = 1 AND banned_until IS NOT NULL AND banned_until > NOW()");
$banned_users = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM groups_");
$total_groups = (int) $stmt->fetchColumn();

if ($tab === 'users') {
    $search = trim($_GET['q'] ?? '');
    $where = "WHERE u.role = 'Student' AND u.approved = 1";
    $params = [];
    if ($search) {
        $where .= ' AND (u.full_name LIKE ? OR u.matric_id LIKE ?)';
        $params = ["%$search%", "%$search%"];
    }
    $stmt = $pdo->prepare("SELECT u.* FROM users u $where ORDER BY u.full_name ASC LIMIT 100");
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} elseif ($tab === 'pending') {
    $stmt = $pdo->query("SELECT * FROM users WHERE role = 'Student' AND approved = 0 ORDER BY created_at ASC LIMIT 200");
    $pending_users = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("
        SELECT r.*, 
            reporter.full_name AS reporter_name, reporter.matric_id AS reporter_matric_id,
            reported.full_name AS reported_name, reported.matric_id AS reported_matric_display,
            reported.id AS reported_user_id, reported.banned_until
        FROM reports r
        JOIN users reporter ON reporter.id = r.reporter_id
        JOIN users reported ON reported.id = r.reported_id
        ORDER BY r.status ASC, r.created_at DESC
        LIMIT 100
    ");
    $stmt->execute();
    $reports = $stmt->fetchAll();
}

$programs = get_programs();

render_head('Admin Dashboard');
render_nav();
?>
<div class="mobile_topbar">
    <button class="mobile_toggle" onclick="document.querySelector('.sidebar').classList.toggle('open');document.querySelector('.sidebar_overlay').classList.toggle('open')">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <span class="mobile_topbar_title">Admin</span>
</div>
<div class="sidebar_overlay" onclick="document.querySelector('.sidebar').classList.remove('open');this.classList.remove('open')"></div>
<main class="main_content">
    <?php render_flash(); ?>

    <h1 class="page_title">Admin Dashboard</h1>

    <div class="stats_grid">
        <div class="stat_card">
            <div class="stat_value color_1"><?= $pending_reports ?></div>
            <div class="stat_label">Pending Reports</div>
        </div>
        <div class="stat_card">
            <div class="stat_value color_2"><?= $pending_approvals ?></div>
            <div class="stat_label">Pending Approvals</div>
        </div>
        <div class="stat_card">
            <div class="stat_value color_2"><?= $total_users ?></div>
            <div class="stat_label">Total Students</div>
        </div>
        <div class="stat_card">
            <div class="stat_value color_3"><?= $banned_users ?></div>
            <div class="stat_label">Banned Users</div>
        </div>
        <div class="stat_card">
            <div class="stat_value color_4"><?= $total_groups ?></div>
            <div class="stat_label">Total Groups</div>
        </div>
    </div>

    <div class="admin_tabs">
        <a href="?tab=reports" class="admin_tab<?= $tab === 'reports' ? ' active' : '' ?>">Reports</a>
        <a href="?tab=users" class="admin_tab<?= $tab === 'users' ? ' active' : '' ?>">Users</a>
        <a href="?tab=pending" class="admin_tab<?= $tab === 'pending' ? ' active' : '' ?>">Pending<?= $pending_approvals > 0 ? ' (' . $pending_approvals . ')' : '' ?></a>
    </div>

    <?php if ($tab === 'users'): ?>



    <form method="GET" class="search_bar" style="margin-bottom:16px;">
        <input type="hidden" name="tab" value="users">
        <input class="field_input" type="text" name="q" value="<?= e($search ?? '') ?>" placeholder="Search by name or matric...">
        <button type="submit" class="btn btn_primary">Search</button>
    </form>

    <?php if (empty($users)): ?>
    <div class="empty_state"><h3>No users found</h3></div>
    <?php else: ?>
    <div class="admin_table_wrap">
        <table class="admin_table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Matric</th>
                    <th>Program</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u):
                    $is_banned = $u['banned_until'] && strtotime($u['banned_until']) > time();
                ?>
                <tr>
                    <td><?= e($u['full_name']) ?></td>
                    <td><?= e($u['matric_id']) ?></td>
                    <td><?= e($u['program'] ?? '-') ?></td>
                    <td>
                        <?php if ($is_banned): ?>
                        <span class="badge badge_danger">Banned until <?= date('M j, H:i', strtotime($u['banned_until'])) ?></span>
                        <?php else: ?>
                        <span class="badge badge_info">Active</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($is_banned): ?>
                        <form method="POST" action="<?= $base ?>/php/api/reports.php" style="display:inline;">
                            <input type="hidden" name="action" value="unban">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                            <button type="submit" class="btn btn_primary btn_sm">Unban</button>
                        </form>
                        <?php else: ?>
                        <form method="POST" action="<?= $base ?>/php/api/reports.php" id="ban_form_<?= $u['id'] ?>" style="display:inline;">
                            <input type="hidden" name="action" value="ban">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                        </form>
                        <button type="button" class="btn btn_danger btn_sm" onclick="GSF.confirm('Ban this user for 3 days?',function(){document.getElementById('ban_form_<?= $u['id'] ?>').submit();})">Ban</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php elseif ($tab === 'pending'): ?>

    <?php if (empty($pending_users)): ?>
    <div class="empty_state"><h3>No pending registrations</h3><p>Self-registered students will appear here for your approval.</p></div>
    <?php else: ?>
    <div class="admin_table_wrap">
        <table class="admin_table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Matric</th>
                    <th>Program</th>
                    <th>Submitted</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_users as $pu): ?>
                <tr>
                    <td><?= e($pu['full_name']) ?></td>
                    <td><?= e($pu['matric_id']) ?></td>
                    <td><?= e($pu['program'] ?? '-') ?></td>
                    <td><?= date('M j, Y H:i', strtotime($pu['created_at'])) ?></td>
                    <td>
                        <div style="display:flex;gap:4px;flex-wrap:wrap;">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="approve_user">
                                <input type="hidden" name="user_id" value="<?= $pu['id'] ?>">
                                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                <button type="submit" class="btn btn_primary btn_sm">Approve</button>
                            </form>
                            <form method="POST" id="reject_form_<?= $pu['id'] ?>" style="display:inline;">
                                <input type="hidden" name="action" value="reject_user">
                                <input type="hidden" name="user_id" value="<?= $pu['id'] ?>">
                                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                            </form>
                            <button type="button" class="btn btn_danger btn_sm" onclick="GSF.confirm('Reject and delete this registration?',function(){document.getElementById('reject_form_<?= $pu['id'] ?>').submit();})">Reject</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php else: ?>

    <?php if (empty($reports)): ?>
    <div class="empty_state"><h3>No reports yet</h3></div>
    <?php else: ?>
    <div class="admin_table_wrap">
        <table class="admin_table">
            <thead>
                <tr>
                    <th>Reporter</th>
                    <th>Reported</th>
                    <th>Matric</th>
                    <th>Reason</th>
                    <th>Evidence</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $r):
                    $reported_banned = $r['banned_until'] && strtotime($r['banned_until']) > time();
                ?>
                <tr>
                    <td>
                        <div class="admin_cell_name"><?= e($r['reporter_name']) ?></div>
                        <div class="admin_cell_sub"><?= e($r['reporter_matric_id']) ?></div>
                    </td>
                    <td>
                        <div class="admin_cell_name"><?= e($r['reported_name']) ?></div>
                    </td>
                    <td><?= e($r['reported_matric'] ?? $r['reported_matric_display']) ?></td>
                    <td class="admin_cell_reason"><?= e($r['reason']) ?></td>
                    <td>
                        <?php if ($r['image_path']): ?>
                        <img src="<?= $base ?>/uploads/reports/<?= e($r['image_path']) ?>" alt="evidence" class="report_image">
                        <?php else: ?>
                        -
                        <?php endif; ?>
                    </td>
                    <td><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
                    <td>
                        <?php if ($r['status'] === 'pending'): ?>
                        <span class="badge badge_pending">Pending</span>
                        <?php else: ?>
                        <span class="badge badge_info">Resolved</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($r['status'] === 'pending'): ?>
                        <div style="display:flex;gap:4px;flex-wrap:wrap;">
                            <form method="POST" action="<?= $base ?>/php/api/reports.php" style="display:inline;">
                                <input type="hidden" name="action" value="resolve">
                                <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                <button type="submit" class="btn btn_primary btn_sm">Resolve</button>
                            </form>
                            <?php if (!$reported_banned): ?>
                            <form method="POST" action="<?= $base ?>/php/api/reports.php" id="ban_report_<?= $r['id'] ?>" style="display:inline;">
                                <input type="hidden" name="action" value="ban">
                                <input type="hidden" name="user_id" value="<?= $r['reported_user_id'] ?>">
                                <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                            </form>
                            <button type="button" class="btn btn_danger btn_sm" onclick="GSF.confirm('Ban for 3 days and resolve?',function(){document.getElementById('ban_report_<?= $r['id'] ?>').submit();})">Ban</button>
                            <?php else: ?>
                            <span class="badge badge_danger" style="font-size:11px;">Banned</span>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        -
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</main>
<?php render_footer(); ?>