<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/layout.php';
require_admin();

$base = base_url();
$tab = $_GET['tab'] ?? 'reports';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register_student') {
    $csrf = $_POST['csrf'] ?? '';
    if (!verify_csrf($csrf)) {
        set_flash('error', 'Invalid token.');
    } else {
        $matric = strtoupper(trim($_POST['matric_id'] ?? ''));
        $name = trim($_POST['full_name'] ?? '');
        $program = trim($_POST['program'] ?? '');
        $password = $_POST['password'] ?? 'gsf12345';

        if (!$matric || !$name) {
            set_flash('error', 'Matric ID and name are required.');
        } elseif (!preg_match('/^(RC|CI|CN|CS|CM)\d{5}$/', $matric)) {
            set_flash('error', 'id unknown');
        } else {
            $chk = $pdo->prepare('SELECT id FROM users WHERE matric_id = ?');
            $chk->execute([$matric]);
            if ($chk->fetch()) {
                set_flash('error', 'Matric ID already exists.');
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare('INSERT INTO users (matric_id, full_name, program, password, role) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$matric, $name, $program ?: null, $hash, 'Student']);
                set_flash('success', 'Student ' . $matric . ' registered with default password.');
            }
        }
    }
    header('Location: ' . $base . '/php/admin/?tab=users');
    exit;
}

$stmt = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'");
$pending_reports = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Student'");
$total_users = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Student' AND banned_until IS NOT NULL AND banned_until > NOW()");
$banned_users = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM groups_");
$total_groups = (int) $stmt->fetchColumn();

if ($tab === 'users') {
    $search = trim($_GET['q'] ?? '');
    $where = "WHERE u.role = 'Student'";
    $params = [];
    if ($search) {
        $where .= ' AND (u.full_name LIKE ? OR u.matric_id LIKE ?)';
        $params = ["%$search%", "%$search%"];
    }
    $stmt = $pdo->prepare("SELECT u.* FROM users u $where ORDER BY u.full_name ASC LIMIT 100");
    $stmt->execute($params);
    $users = $stmt->fetchAll();
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
    </div>

    <?php if ($tab === 'users'): ?>

    <div class="profile_section" style="margin-bottom:20px;">
        <h3 class="profile_section_title">Register New Student</h3>
        <form method="POST">
            <input type="hidden" name="action" value="register_student">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <div class="pref_row">
                <div class="field_group">
                    <label class="field_label">Matric ID *</label>
                    <input class="field_input" type="text" name="matric_id" placeholder="e.g. RC24163" required>
                </div>
                <div class="field_group">
                    <label class="field_label">Full Name *</label>
                    <input class="field_input" type="text" name="full_name" placeholder="Student full name" required>
                </div>
                <div class="field_group">
                    <label class="field_label">Program</label>
                    <select class="field_input" name="program">
                        <option value="">-- Select --</option>
                        <?php foreach ($programs as $p): ?>
                        <option value="<?= e($p) ?>"><?= e($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="field_group">
                <label class="field_label">Password (default: gsf12345)</label>
                <input class="field_input" type="text" name="password" value="gsf12345" placeholder="Initial password">
            </div>
            <button type="submit" class="btn btn_primary">Register Student</button>
        </form>
    </div>

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