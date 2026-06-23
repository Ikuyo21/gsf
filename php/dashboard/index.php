<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/layout.php';
require_student();

$uid  = current_user_id();
$base = base_url();

update_last_active($pdo, $uid);

/**
 * Friendly countdown label, e.g. "Begins in 3 days 2 hours".
 * Mirrors fmtCountdown() in chat.js so the wording matches everywhere.
 */
function ss_countdown_label(int $secs): string {
    if ($secs < 60) return 'Begins in less than a minute';
    $days  = intdiv($secs, 86400);
    $hours = intdiv($secs % 86400, 3600);
    $mins  = intdiv($secs % 3600, 60);
    if ($days >= 1) {
        $out = $days . ' day' . ($days > 1 ? 's' : '');
        if ($hours > 0) $out .= ' ' . $hours . ' hour' . ($hours > 1 ? 's' : '');
        return 'Begins in ' . $out;
    }
    if ($hours >= 1) {
        $out = $hours . ' hour' . ($hours > 1 ? 's' : '');
        if ($mins > 0) $out .= ' ' . $mins . ' min';
        return 'Begins in ' . $out;
    }
    return 'Begins in ' . $mins . ' min';
}

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

$upcoming_sessions = [];
try {
    $stmt = $pdo->prepare("
        SELECT ss.*, g.name AS group_name, g.id AS gid,
               (SELECT COUNT(*) FROM study_session_attendees WHERE session_id = ss.id) AS attendee_count
        FROM   study_sessions ss
        JOIN   study_session_attendees ssa ON ssa.session_id = ss.id
        JOIN   groups_ g ON g.id = ss.group_id
        WHERE  ssa.user_id = ?
          AND  CONCAT(COALESCE(ss.session_end_date, ss.session_date),' ',COALESCE(ss.session_end_time, ss.session_time)) >= NOW()
        ORDER  BY ss.session_date ASC, ss.session_time ASC
        LIMIT  10
    ");
    $stmt->execute([$uid]);
    $upcoming_sessions = $stmt->fetchAll();
} catch (Exception $e) {}

$now_ts = time();
foreach ($upcoming_sessions as &$s) {
    $start_ts = strtotime($s['session_date'] . ' ' . $s['session_time']);
    $end_ts   = strtotime(
        ($s['session_end_date'] ?: $s['session_date']) . ' ' .
        ($s['session_end_time'] ?: $s['session_time'])
    );
    $s['_start_ts'] = $start_ts;
    $s['_end_ts']   = $end_ts;
    $s['_live']     = ($now_ts >= $start_ts && $now_ts < $end_ts);
    $s['_status']   = $s['_live'] ? 'Now in session' : ss_countdown_label($start_ts - $now_ts);
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
            $start_dt    = new DateTime($s['session_date'] . ' ' . $s['session_time']);
            $end_dt      = new DateTime(($s['session_end_date'] ?: $s['session_date']) . ' ' . ($s['session_end_time'] ?: $s['session_time']));
            $crosses_day = $start_dt->format('Y-m-d') !== $end_dt->format('Y-m-d');
        ?>
        <div class="dash_session_card<?= $s['_live'] ? ' dash_sc_live' : '' ?>" data-session-ts="<?= $s['_start_ts'] * 1000 ?>" data-session-end-ts="<?= $s['_end_ts'] * 1000 ?>">
            <div class="dsc_status<?= $s['_live'] ? ' dsc_status_live' : ' dsc_status_upcoming' ?>"><?= e($s['_status']) ?></div>

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
                    <?= e($start_dt->format('D, M j, Y')) ?> · <?= e($start_dt->format('g:i A')) ?> – <?= e($end_dt->format('g:i A')) ?><?= $crosses_day ? ' (next day)' : '' ?>
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
<script>
(function () {
    // Same wording as chat.js fmtCountdown / the PHP helper.
    function fmtCountdown(ms) {
        var secs = Math.floor(ms / 1000);
        if (secs < 60) return 'Begins in less than a minute';
        var days  = Math.floor(secs / 86400);
        var hours = Math.floor((secs % 86400) / 3600);
        var mins  = Math.floor((secs % 3600) / 60);
        if (days >= 1) {
            var d = days + ' day' + (days > 1 ? 's' : '');
            if (hours > 0) d += ' ' + hours + ' hour' + (hours > 1 ? 's' : '');
            return 'Begins in ' + d;
        }
        if (hours >= 1) {
            var h = hours + ' hour' + (hours > 1 ? 's' : '');
            if (mins > 0) h += ' ' + mins + ' min';
            return 'Begins in ' + h;
        }
        return 'Begins in ' + mins + ' min';
    }

    function tick() {
        var now = Date.now();
        var cards = document.querySelectorAll('.dash_session_card[data-session-ts]');
        cards.forEach(function (card) {
            if (card.classList.contains('fading_out')) return;
            var startMs = parseInt(card.dataset.sessionTs);
            var endMs   = parseInt(card.dataset.sessionEndTs);
            if (!startMs) return;
            if (!endMs) endMs = startMs;

            var status = card.querySelector('.dsc_status');

            // Ended -> remove it from the dashboard (the record stays; chat greys it out).
            if (now >= endMs) {
                card.classList.add('fading_out');
                setTimeout(function () { card.remove(); }, 520);
                return;
            }

            // In session.
            if (now >= startMs) {
                card.classList.add('dash_sc_live');
                if (status) {
                    status.className = 'dsc_status dsc_status_live';
                    status.textContent = 'Now in session';
                }
                return;
            }

            // Upcoming -> live countdown.
            card.classList.remove('dash_sc_live');
            if (status) {
                status.className = 'dsc_status dsc_status_upcoming';
                status.textContent = fmtCountdown(startMs - now);
            }
        });
    }

    tick();
    setInterval(tick, 1000);
})();
</script>
<?php render_footer(); ?>
