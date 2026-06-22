<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/layout.php';
require_student();

$uid = current_user_id();
$base = base_url();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf'] ?? '';
    if (!verify_csrf($csrf)) {
        set_flash('error', 'Invalid token.');
        header('Location: ' . $base . '/php/progress/');
        exit;
    }

    if ($action === 'create_tracker') {
        $title = trim($_POST['title'] ?? '');
        $goals_raw = $_POST['goals'] ?? [];
        if ($title && !empty($goals_raw)) {
            $stmt = $pdo->prepare('INSERT INTO study_trackers (user_id, title) VALUES (?, ?)');
            $stmt->execute([$uid, $title]);
            $tid = $pdo->lastInsertId();
            $ins = $pdo->prepare('INSERT INTO tracker_goals (tracker_id, title) VALUES (?, ?)');
            foreach ($goals_raw as $g) {
                $g = trim($g);
                if ($g) $ins->execute([$tid, $g]);
            }
            set_flash('success', 'Tracker created.');
        } else {
            set_flash('error', 'Title and at least one goal required.');
        }
        header('Location: ' . $base . '/php/progress/');
        exit;
    }

    if ($action === 'add_goal') {
        $tid = (int) ($_POST['tracker_id'] ?? 0);
        $title = trim($_POST['goal_title'] ?? '');
        $chk = $pdo->prepare('SELECT id FROM study_trackers WHERE id = ? AND user_id = ?');
        $chk->execute([$tid, $uid]);
        if ($chk->fetch() && $title) {
            $pdo->prepare('INSERT INTO tracker_goals (tracker_id, title) VALUES (?, ?)')->execute([$tid, $title]);
            set_flash('success', 'Goal added.');
        }
        header('Location: ' . $base . '/php/progress/');
        exit;
    }

    if ($action === 'delete_tracker') {
        $tid = (int) ($_POST['tracker_id'] ?? 0);
        $chk = $pdo->prepare('SELECT id FROM study_trackers WHERE id = ? AND user_id = ?');
        $chk->execute([$tid, $uid]);
        if ($chk->fetch()) {
            $pdo->prepare('DELETE FROM tracker_goals WHERE tracker_id = ?')->execute([$tid]);
            $pdo->prepare('DELETE FROM study_trackers WHERE id = ?')->execute([$tid]);
            set_flash('success', 'Tracker deleted.');
        }
        header('Location: ' . $base . '/php/progress/');
        exit;
    }

    if ($action === 'delete_goal') {
        $gid = (int) ($_POST['goal_id'] ?? 0);
        $chk = $pdo->prepare('SELECT tg.id FROM tracker_goals tg JOIN study_trackers st ON st.id = tg.tracker_id WHERE tg.id = ? AND st.user_id = ?');
        $chk->execute([$gid, $uid]);
        if ($chk->fetch()) {
            $pdo->prepare('DELETE FROM tracker_goals WHERE id = ?')->execute([$gid]);
            set_flash('success', 'Goal removed.');
        }
        header('Location: ' . $base . '/php/progress/');
        exit;
    }
}

$stmt = $pdo->prepare('SELECT * FROM study_trackers WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$uid]);
$trackers = $stmt->fetchAll();

$tracker_goals = [];
if (!empty($trackers)) {
    $tids = array_column($trackers, 'id');
    $placeholders = implode(',', array_fill(0, count($tids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM tracker_goals WHERE tracker_id IN ($placeholders) ORDER BY created_at ASC");
    $stmt->execute($tids);
    foreach ($stmt->fetchAll() as $g) {
        $tracker_goals[$g['tracker_id']][] = $g;
    }
}

render_head('Study Progress');
render_nav();
?>
<div class="mobile_topbar">
    <button class="mobile_toggle" onclick="document.querySelector('.sidebar').classList.toggle('open');document.querySelector('.sidebar_overlay').classList.toggle('open')">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <span class="mobile_topbar_title">Study Progress</span>
</div>
<div class="sidebar_overlay" onclick="document.querySelector('.sidebar').classList.remove('open');this.classList.remove('open')"></div>
<main class="main_content">
    <?php render_flash(); ?>

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
        <h1 class="page_title" style="margin:0;">Study Progress</h1>
        <button type="button" class="btn btn_primary" onclick="GSF.modal.open('add_tracker_modal')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px;margin-right:4px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Tracker
        </button>
    </div>

    <?php if (empty($trackers)): ?>
    <div class="empty_state">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="1.5"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
        <h3>No trackers yet</h3>
        <p>Create a tracker to start monitoring your study progress.</p>
    </div>
    <?php else: ?>
    <div class="tracker_grid">
        <?php foreach ($trackers as $t):
            $goals = $tracker_goals[$t['id']] ?? [];
            $total = count($goals);
            $done = 0;
            foreach ($goals as $g) { if ($g['completed']) $done++; }
            $pct = $total > 0 ? round(($done / $total) * 100) : 0;
        ?>
        <div class="tracker_card">
            <div class="tracker_card_header">
                <h3 class="tracker_card_title"><?= e($t['title']) ?></h3>
                <form method="POST" id="del_tracker_<?= $t['id'] ?>">
                    <input type="hidden" name="action" value="delete_tracker">
                    <input type="hidden" name="tracker_id" value="<?= $t['id'] ?>">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                </form>
                <button type="button" class="tracker_delete_btn" onclick="GSF.confirm('Delete this tracker and all its goals?',function(){document.getElementById('del_tracker_<?= $t['id'] ?>').submit();})">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                </button>
            </div>

            <div class="tracker_progress_wrap">
                <div class="tracker_progress_bar">
                    <div class="tracker_progress_fill" style="width:<?= $pct ?>%;"></div>
                </div>
                <span class="tracker_progress_text"><?= $done ?>/<?= $total ?> goals</span>
            </div>

            <div class="tracker_goals_list">
                <?php foreach ($goals as $g): ?>
                <div class="tracker_goal<?= $g['completed'] ? ' completed' : '' ?>">
                    <label class="tracker_checkbox">
                        <input type="checkbox" <?= $g['completed'] ? 'checked' : '' ?> onchange="toggleGoal(<?= $g['id'] ?>,this.checked)">
                        <span class="tracker_checkbox_mark">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                        </span>
                    </label>
                    <span class="tracker_goal_text"><?= e($g['title']) ?></span>
                    <form method="POST" id="del_goal_<?= $g['id'] ?>" style="margin-left:auto;">
                        <input type="hidden" name="action" value="delete_goal">
                        <input type="hidden" name="goal_id" value="<?= $g['id'] ?>">
                        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    </form>
                    <button type="button" class="tracker_goal_delete" onclick="GSF.confirm('Remove this goal?',function(){document.getElementById('del_goal_<?= $g['id'] ?>').submit();})">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="tracker_add_goal">
                <form method="POST" style="display:flex;gap:8px;align-items:center;">
                    <input type="hidden" name="action" value="add_goal">
                    <input type="hidden" name="tracker_id" value="<?= $t['id'] ?>">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    <input type="text" name="goal_title" class="field_input" placeholder="Add a goal..." style="flex:1;padding:8px 12px;font-size:13px;">
                    <button type="submit" class="btn btn_primary btn_sm">Add</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>

<div class="modal_overlay" id="add_tracker_modal">
    <div class="modal_content">
        <div class="modal_header">
            <h2 class="modal_title">New Tracker</h2>
            <button type="button" class="modal_close" onclick="GSF.modal.close('add_tracker_modal')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create_tracker">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <div class="modal_body">
                <div class="field_group">
                    <label class="field_label">Tracker Title</label>
                    <input type="text" name="title" class="field_input" placeholder="e.g. Data Structures Final" required>
                </div>
                <div class="field_group">
                    <label class="field_label">Goals</label>
                    <div id="goals_container">
                        <div class="tracker_goal_input_row">
                            <input type="text" name="goals[]" class="field_input" placeholder="Goal 1" required>
                        </div>
                    </div>
                    <button type="button" class="btn btn_ghost btn_sm" style="margin-top:8px;" onclick="addGoalField()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px;margin-right:2px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add another goal
                    </button>
                </div>
            </div>
            <div class="modal_footer">
                <button type="button" class="btn btn_ghost" onclick="GSF.modal.close('add_tracker_modal')">Cancel</button>
                <button type="submit" class="btn btn_primary">Create Tracker</button>
            </div>
        </form>
    </div>
</div>

<script>
var goalCount = 1;
function addGoalField() {
    goalCount++;
    var row = document.createElement('div');
    row.className = 'tracker_goal_input_row';
    row.style.marginTop = '8px';
    row.innerHTML = '<input type="text" name="goals[]" class="field_input" placeholder="Goal ' + goalCount + '">';
    document.getElementById('goals_container').appendChild(row);
    row.querySelector('input').focus();
}

function toggleGoal(goalId, checked) {
    var fd = new FormData();
    fd.append('action', 'toggle_goal');
    fd.append('goal_id', goalId);
    fd.append('completed', checked ? '1' : '0');
    fd.append('csrf', '<?= csrf_token() ?>');
    fetch(window.BASE_URL + '/php/api/tracker.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.ok) location.reload();
        });
}
</script>

<?php render_footer(); ?>
