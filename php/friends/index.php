<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/layout.php';
require_student();

$uid = current_user_id();
$base = base_url();

$stmt = $pdo->prepare('SELECT f.*, u.full_name AS name, u.matric_id, u.avatar FROM friends f JOIN users u ON u.id = f.sender_id WHERE f.receiver_id = ? AND f.status = "pending" ORDER BY f.created_at DESC');
$stmt->execute([$uid]);
$pending = $stmt->fetchAll();

$stmt = $pdo->prepare('
    SELECT u.id, u.full_name AS name, u.matric_id, u.avatar, u.bio
    FROM friends f
    JOIN users u ON (u.id = CASE WHEN f.sender_id = ? THEN f.receiver_id ELSE f.sender_id END)
    WHERE (f.sender_id = ? OR f.receiver_id = ?) AND f.status = "accepted"
    ORDER BY u.full_name ASC
');
$stmt->execute([$uid, $uid, $uid]);
$friends = $stmt->fetchAll();

render_head('Friends');
render_nav();
?>
<div class="mobile_topbar">
    <button class="mobile_toggle" onclick="document.querySelector('.sidebar').classList.toggle('open');document.querySelector('.sidebar_overlay').classList.toggle('open')">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <span class="mobile_topbar_title">Friends</span>
</div>
<div class="sidebar_overlay" onclick="document.querySelector('.sidebar').classList.remove('open');this.classList.remove('open')"></div>
<main class="main_content">
    <?php render_flash(); ?>
    <h1 class="page_title" style="margin-bottom:20px;">Friends</h1>

    <?php if (!empty($pending)): ?>
    <h2 class="section_title">Pending Requests (<?= count($pending) ?>)</h2>
    <div style="margin-bottom:28px;">
        <?php foreach ($pending as $r): ?>
        <div class="friend_row">
            <img src="<?= avatar_url($r['avatar']) ?>" alt="" class="friend_row_avatar">
            <div class="friend_row_info">
                <span class="friend_row_name"><?= e($r['name']) ?></span>
                <span class="friend_row_id"><?= e($r['matric_id']) ?></span>
            </div>
            <form method="POST" action="<?= $base ?>/php/api/friends.php" style="display:inline;">
                <input type="hidden" name="action" value="accept">
                <input type="hidden" name="user_id" value="<?= $r['sender_id'] ?>">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <button type="submit" class="btn btn_primary btn_sm">Accept</button>
            </form>
            <form method="POST" action="<?= $base ?>/php/api/friends.php" style="display:inline;">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="user_id" value="<?= $r['sender_id'] ?>">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <button type="submit" class="btn btn_ghost btn_sm">Decline</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <h2 class="section_title">All Friends (<?= count($friends) ?>)</h2>
    <?php if (empty($friends)): ?>
    <div class="empty_state">
        <h3>No friends yet</h3>
        <p><a href="<?= $base ?>/php/people/" style="color:var(--primary);font-weight:500;">Find people</a> to add as friends.</p>
    </div>
    <?php else: ?>
    <div class="people_grid">
        <?php foreach ($friends as $f): ?>
        <div class="person_card">
            <img src="<?= avatar_url($f['avatar']) ?>" alt="" class="person_avatar">
            <div class="person_info">
                <span class="person_name"><a href="<?= $base ?>/php/profile/view.php?id=<?= $f['id'] ?>"><?= e($f['name']) ?></a></span>
                <span class="person_id"><?= e($f['matric_id']) ?></span>
                <?php if ($f['bio']): ?>
                <p class="person_bio"><?= e(mb_strimwidth($f['bio'] ?? '', 0, 80, '...')) ?></p>
                <?php endif; ?>
                <div class="person_actions">
                    <form method="POST" action="<?= $base ?>/php/api/friends.php" style="display:inline;">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="user_id" value="<?= $f['id'] ?>">
                        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                        <button type="submit" class="btn btn_ghost btn_sm" onclick="return confirm('Remove friend?')">Remove</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>
<?php render_footer(); ?>
