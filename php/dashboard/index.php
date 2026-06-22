<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/layout.php';
require_student();

$uid  = current_user_id();
$base = base_url();

update_last_active($pdo, $uid);

$stmt = $pdo->prepare('SELECT COUNT(*) FROM group_members WHERE user_id = ?');
$stmt->execute([$uid]);
$groups_count = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM friends WHERE (sender_id = ? OR receiver_id = ?) AND status = "accepted"');
$stmt->execute([$uid, $uid]);
$friends_count = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM friends WHERE receiver_id = ? AND status = "pending"');
$stmt->execute([$uid]);
$pending_count = (int) $stmt->fetchColumn();

$joined_ids = [];
$stmt = $pdo->prepare('SELECT group_id FROM group_members WHERE user_id = ?');
$stmt->execute([$uid]);
$joined_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (!empty($joined_ids)) {
    $placeholders = implode(',', array_fill(0, count($joined_ids), '?'));
    $stmt = $pdo->prepare("SELECT g.*, u.full_name AS leader_name, (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) AS member_count FROM groups_ g JOIN users u ON u.id = g.leader_id WHERE g.id NOT IN ($placeholders) AND g.visibility = 'public' ORDER BY g.created_at DESC LIMIT 6");
    $stmt->execute($joined_ids);
} else {
    $stmt = $pdo->prepare("SELECT g.*, u.full_name AS leader_name, (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) AS member_count FROM groups_ g JOIN users u ON u.id = g.leader_id WHERE g.visibility = 'public' ORDER BY g.created_at DESC LIMIT 6");
    $stmt->execute();
}
$recommended = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT sp.*, (SELECT COUNT(*) FROM tracker_goals WHERE tracker_id = sp.id) AS total_goals, (SELECT COUNT(*) FROM tracker_goals WHERE tracker_id = sp.id AND completed = 1) AS completed_goals FROM study_trackers sp WHERE sp.user_id = ? ORDER BY sp.created_at DESC LIMIT 5');
$stmt->execute([$uid]);
$progress = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT g.*, (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) AS member_count FROM groups_ g JOIN group_members gm ON gm.group_id = g.id WHERE gm.user_id = ? ORDER BY g.created_at DESC LIMIT 4");
$stmt->execute([$uid]);
$my_groups = $stmt->fetchAll();

/* ── Upcoming study sessions ── */
$upcoming_sessions = [];
try {
    $stmt = $pdo->prepare("
        SELECT ss.*, g.name AS group_name, g.id AS gid,
               (SELECT COUNT(*) FROM study_session_attendees WHERE session_id = ss.id) AS attendee_count
        FROM   study_sessions ss
        JOIN   study_session_attendees ssa ON ssa.session_id = ss.id
        JOIN   groups_ g ON g.id = ss.group_id
        WHERE  ssa.user_id = ?
          AND  CONCAT(ss.session_date,' ',ss.session_time) >= NOW()
        ORDER  BY ss.session_date ASC, ss.session_time ASC
        LIMIT  10
    ");
    $stmt->execute([$uid]);
    $upcoming_sessions = $stmt->fetchAll();
} catch (Exception $e) { /* table may not exist yet */ }

/* Tag sessions starting within 15 min */
$now_ts = time();
foreach ($upcoming_sessions as &$s) {
    $sess_ts      = strtotime($s['session_date'] . ' ' . $s['session_time']);
    $diff_min     = ($sess_ts - $now_ts) / 60;
    $s['_soon']   = $diff_min > 0 && $diff_min <= 15;
    $s['_v_soon'] = $diff_min > 0 && $diff_min <= 5;
    $s['_started'] = $diff_min <= 0 && $diff_min >= -120;
}
unset($s);

render_head('Dashboard');
render_nav();
?>
<div class="mobile_topbar">
    <button class="mobile_toggle" onclick="document.querySelector('.sidebar').classList.toggle('open');document.querySelector('.sidebar_overlay').classList.toggle('open')">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <span class="mobile_topbar_title">GSFinder</span>
</div>
<div class="sidebar_overlay" onclick="document.querySelector('.sidebar').classList.remove('open');this.classList.remove('open')"></div>

<!-- Session notification banner (starting soon) -->
<?php foreach ($upcoming_sessions as $s): if ($s['_soon'] || $s['_started']): ?>
<div class="session_notify_banner <?= $s['_v_soon'] || $s['_started'] ? 'urgent' : '' ?>" id="notify_banner_<?= (int)$s['id'] ?>">
    <div class="snb_inner">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
        <span>
            <?php if ($s['_started']): ?>
                <strong>Now happening:</strong> <?= e($s['title']) ?> in <strong><?= e($s['group_name']) ?></strong>
            <?php elseif ($s['_v_soon']): ?>
                <strong>Starting very soon!</strong> <?= e($s['title']) ?> — <?= e($s['group_name']) ?>
            <?php else: ?>
                <strong>Starting soon:</strong> <?= e($s['title']) ?> in 15 min — <?= e($s['group_name']) ?>
            <?php endif; ?>
        </span>
        <?php if (!empty($s['link'])): ?>
        <a href="<?= e($s['link']) ?>" target="_blank" class="snb_join_link">Open Meeting</a>
        <?php endif; ?>
        <a href="<?= $base ?>/php/groups/view.php?id=<?= (int)$s['gid'] ?>" class="snb_join_link">Go to Group</a>
        <button onclick="this.closest('.session_notify_banner').remove()" class="snb_close">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
</div>
<?php endif; endforeach; ?>

<main class="main_content">
    <?php render_flash(); ?>

    <div class="welcome_banner">
        <h1>Welcome Back, <?= e(current_user_name()) ?></h1>
        <p>Continue learning, collaborating and achieving success with your peers.</p>
    </div>

    <div class="stats_grid">
        <div class="stat_card">
            <div class="stat_value color_1"><?= $groups_count ?></div>
            <div class="stat_label">Groups Joined</div>
        </div>
        <div class="stat_card">
            <div class="stat_value color_2"><?= count($my_groups) ?></div>
            <div class="stat_label">My Groups</div>
        </div>
        <div class="stat_card">
            <div class="stat_value color_3"><?= $friends_count ?></div>
            <div class="stat_label">Friends</div>
        </div>
        <div class="stat_card">
            <div class="stat_value color_4"><?= $pending_count ?></div>
            <div class="stat_label">Pending Requests</div>
        </div>
    </div>

    <?php if (!empty($upcoming_sessions)): ?>
    <h2 class="section_title">Upcoming Study Sessions</h2>
    <div class="dash_sessions_grid">
        <?php foreach ($upcoming_sessions as $s):
            $sess_dt  = new DateTime($s['session_date'] . ' ' . $s['session_time']);
            $diff_min = ($sess_dt->getTimestamp() - $now_ts) / 60;
        ?>
        <div class="dash_session_card<?= $s['_soon'] || $s['_started'] ? ' dash_sc_soon' : '' ?>">
            <?php if ($s['_started']): ?>
            <div class="dsc_badge badge_live">● Live Now</div>
            <?php elseif ($s['_v_soon']): ?>
            <div class="dsc_badge badge_vsoon">⚡ Starts in &lt; 5 min</div>
            <?php elseif ($s['_soon']): ?>
            <div class="dsc_badge badge_soon">⏰ Starting Soon</div>
            <?php endif; ?>

            <div class="dsc_header">
                <div class="dsc_cal_icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                </div>
                <div>
                    <div class="dsc_title"><?= e($s['title']) ?></div>
                    <div class="dsc_group"><?= e($s['group_name']) ?></div>
                </div>
            </div>
            <div class="dsc_details">
                <div class="dsc_row">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <?= e($sess_dt->format('D, M j, Y')) ?> · <?= e($sess_dt->format('g:i A')) ?>
                </div>
                <div class="dsc_row">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <?= e($s['location']) ?>
                </div>
                <div class="dsc_row">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                    <?= (int)$s['attendee_count'] ?> member<?= $s['attendee_count'] != 1 ? 's' : '' ?> joined
                </div>
            </div>
            <div class="dsc_footer">
                <?php if (!empty($s['link'])): ?>
                <a href="<?= e($s['link']) ?>" target="_blank" class="btn btn_primary btn_sm">Open Meeting</a>
                <?php endif; ?>
                <a href="<?= $base ?>/php/groups/view.php?id=<?= (int)$s['gid'] ?>" class="btn btn_ghost btn_sm">View Group</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($recommended)): ?>
    <h2 class="section_title">Recommended Study Groups</h2>
    <div class="groups_grid">
        <?php foreach ($recommended as $g): ?>
        <div class="group_card">
            <div class="group_card_name"><a href="<?= $base ?>/php/groups/view.php?id=<?= $g['id'] ?>"><?= e($g['name']) ?></a></div>
            <div class="group_card_meta"><?= $g['member_count'] ?> / <?= $g['max_members'] ?> Members</div>
            <div class="group_card_footer">
                <form method="POST" action="<?= $base ?>/php/api/groups.php">
                    <input type="hidden" name="action"   value="join">
                    <input type="hidden" name="group_id" value="<?= $g['id'] ?>">
                    <input type="hidden" name="csrf"     value="<?= csrf_token() ?>">
                    <button type="submit" class="btn btn_warning btn_sm">Join Group</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($progress)): ?>
    <h2 class="section_title">Study Progress</h2>
    <div class="progress_list">
        <?php foreach ($progress as $p): ?>
        <?php $pct = $p['total_goals'] > 0 ? round(($p['completed_goals'] / $p['total_goals']) * 100) : 0; ?>
        <div>
            <div class="progress_item_label"><?= e($p['title']) ?> - <?= $pct ?>%</div>
            <div class="progress_bar_track">
                <div class="progress_bar_fill" style="width:<?= $pct ?>%"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($my_groups)): ?>
    <h2 class="section_title" style="margin-top:28px;">My Groups</h2>
    <div class="groups_grid">
        <?php foreach ($my_groups as $g): ?>
        <div class="group_card">
            <div class="group_card_name"><a href="<?= $base ?>/php/groups/view.php?id=<?= $g['id'] ?>"><?= e($g['name']) ?></a></div>
            <div class="group_card_meta"><?= $g['member_count'] ?> / <?= $g['max_members'] ?> Members</div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>
<?php render_footer(); ?>
