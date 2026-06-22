<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/layout.php';
require_login();

$uid = current_user_id();
$base = base_url();
$pid = (int) ($_GET['id'] ?? 0);

if (!$pid || $pid === $uid) {
    header('Location: ' . $base . '/php/profile/');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'Student'");
$stmt->execute([$pid]);
$user = $stmt->fetch();

if (!$user) {
    set_flash('error', 'User not found.');
    header('Location: ' . $base . '/php/people/');
    exit;
}

$friend_status = null;
$friend_sender = null;
if (is_student()) {
    $stmt = $pdo->prepare('SELECT status, sender_id FROM friends WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) LIMIT 1');
    $stmt->execute([$uid, $pid, $pid, $uid]);
    $fr = $stmt->fetch();
    if ($fr) {
        $friend_status = $fr['status'];
        $friend_sender = (int) $fr['sender_id'];
    }
}

$stmt = $pdo->prepare("SELECT g.name, g.id FROM groups_ g JOIN group_members gm ON gm.group_id = g.id WHERE gm.user_id = ? ORDER BY g.name ASC");
$stmt->execute([$pid]);
$groups = $stmt->fetchAll();

$banner = banner_url($user['banner']);

render_head($user['full_name']);
render_nav();
?>
<div class="mobile_topbar">
    <button class="mobile_toggle" onclick="document.querySelector('.sidebar').classList.toggle('open');document.querySelector('.sidebar_overlay').classList.toggle('open')">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <span class="mobile_topbar_title"><?= e($user['full_name']) ?></span>
</div>
<div class="sidebar_overlay" onclick="document.querySelector('.sidebar').classList.remove('open');this.classList.remove('open')"></div>
<main class="main_content">
    <?php render_flash(); ?>

    <?php if ($banner): ?>
    <img src="<?= $banner ?>" alt="" class="profile_banner">
    <?php else: ?>
    <div class="profile_banner"></div>
    <?php endif; ?>

    <div class="profile_header">
        <div class="profile_avatar_wrap">
            <img src="<?= avatar_url($user['avatar']) ?>" alt="" class="profile_avatar">
        </div>
        <h1 class="profile_name"><?= e($user['full_name']) ?></h1>
        <span class="profile_matric"><?= e($user['matric_id']) ?> <?= $user['program'] ? '- ' . e($user['program']) : '' ?></span>
        <?php if ($user['bio']): ?>
        <p class="profile_bio"><?= e($user['bio']) ?></p>
        <?php endif; ?>

        <?php if (is_student()): ?>
        <div class="profile_actions" style="margin-top:14px;display:flex;gap:8px;justify-content:center;">
            <?php if (!$friend_status): ?>
            <form method="POST" action="<?= $base ?>/php/api/friends.php">
                <input type="hidden" name="action" value="send">
                <input type="hidden" name="user_id" value="<?= $pid ?>">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="redirect" value="<?= $base ?>/php/profile/view.php?id=<?= $pid ?>">
                <button type="submit" class="btn btn_primary btn_sm">Add Friend</button>
            </form>
            <?php elseif ($friend_status === 'pending' && $friend_sender === $pid): ?>
            <form method="POST" action="<?= $base ?>/php/api/friends.php">
                <input type="hidden" name="action" value="accept">
                <input type="hidden" name="user_id" value="<?= $pid ?>">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="redirect" value="<?= $base ?>/php/profile/view.php?id=<?= $pid ?>">
                <button type="submit" class="btn btn_primary btn_sm">Accept Request</button>
            </form>
            <?php elseif ($friend_status === 'pending'): ?>
            <form method="POST" action="<?= $base ?>/php/api/friends.php" id="cancel_request_form_<?= $pid ?>">
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="user_id" value="<?= $pid ?>">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="redirect" value="<?= $base ?>/php/profile/view.php?id=<?= $pid ?>">
            </form>
            <button type="button" class="btn btn_ghost btn_sm" onclick="GSF.confirm('Cancel this friend request?',function(){document.getElementById('cancel_request_form_<?= $pid ?>').submit();})">Pending (cancel)</button>
            <?php elseif ($friend_status === 'accepted'): ?>
            <span class="badge badge_info">Friends</span>
            <form method="POST" action="<?= $base ?>/php/api/friends.php" id="remove_friend_form_<?= $pid ?>">
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="user_id" value="<?= $pid ?>">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="redirect" value="<?= $base ?>/php/profile/view.php?id=<?= $pid ?>">
            </form>
            <button type="button" class="btn btn_danger btn_sm" onclick="GSF.confirm('Remove this friend?',function(){document.getElementById('remove_friend_form_<?= $pid ?>').submit();})">Remove</button>
            <?php endif; ?>
            <button type="button" class="btn btn_ghost btn_sm" onclick="GSF.modal.open('report_modal')">Report</button>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($groups)): ?>
    <div class="profile_section">
        <h3 class="profile_section_title">Groups</h3>
        <?php foreach ($groups as $g): ?>
        <div class="friend_row" style="margin-bottom:6px;">
            <div class="friend_row_info">
                <a href="<?= $base ?>/php/groups/view.php?id=<?= $g['id'] ?>" class="friend_row_name" style="color:var(--primary);"><?= e($g['name']) ?></a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>

<?php if (is_student()): ?>
<div class="modal_overlay" id="report_modal">
    <div class="modal_content">
        <div class="modal_header">
            <h2 class="modal_title">Report <?= e($user['full_name']) ?></h2>
            <button type="button" class="modal_close" onclick="GSF.modal.close('report_modal')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form method="POST" action="<?= $base ?>/php/api/reports.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="user_id" value="<?= $pid ?>">
            <input type="hidden" name="reported_matric" value="<?= e($user['matric_id']) ?>">
            <div class="modal_body">
                <div class="field_group">
                    <label class="field_label">Reason *</label>
                    <textarea class="field_textarea" name="reason" placeholder="Describe the issue..." required></textarea>
                </div>
                <div class="field_group">
                    <label class="field_label">Attach Image (optional)</label>
                    <div class="file_upload">
                        <div class="file_upload_icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        </div>
                        <div class="file_upload_text">
                            <span class="file_upload_label">Choose image</span>
                            <span class="file_upload_hint">PNG, JPG up to 5MB</span>
                        </div>
                        <img class="file_upload_preview" alt="">
                        <input type="file" name="report_image" accept="image/*">
                    </div>
                </div>
            </div>
            <div class="modal_footer">
                <button type="button" class="btn btn_ghost" onclick="GSF.modal.close('report_modal')">Cancel</button>
                <button type="submit" class="btn btn_danger">Submit Report</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php render_footer(); ?>
