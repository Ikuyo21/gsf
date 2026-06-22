<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/layout.php';
require_student();

$uid = current_user_id();
$base = base_url();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM group_members WHERE user_id = ?');
$stmt->execute([$uid]);
$groups_count = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM friends WHERE (sender_id = ? OR receiver_id = ?) AND status = "accepted"');
$stmt->execute([$uid, $uid]);
$friends_count = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM friends WHERE receiver_id = ? AND status = "pending"');
$stmt->execute([$uid]);
$pending_count = (int) $stmt->fetchColumn();

$member_ids = [$uid];
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

    <?php if (!empty($recommended)): ?>
    <h2 class="section_title">Recommended Study Groups</h2>
    <div class="groups_grid">
        <?php foreach ($recommended as $g): ?>
        <div class="group_card">
            <div class="group_card_name"><a href="<?= $base ?>/php/groups/view.php?id=<?= $g['id'] ?>"><?= e($g['name']) ?></a></div>
            <div class="group_card_meta"><?= $g['member_count'] ?> / <?= $g['max_members'] ?> Members</div>
            <div class="group_card_footer">
                <form method="POST" action="<?= $base ?>/php/api/groups.php">
                    <input type="hidden" name="action" value="join">
                    <input type="hidden" name="group_id" value="<?= $g['id'] ?>">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
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