<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/layout.php';
require_login();

$uid = current_user_id();
$base = base_url();
$search = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'asc';
$order = $sort === 'desc' ? 'DESC' : 'ASC';

if (is_logged_in()) update_last_active($pdo, $uid);

$where = "WHERE u.id != ? AND u.role = 'Student' AND u.approved = 1";
$params = [$uid];

if ($search) {
    $where .= ' AND (u.full_name LIKE ? OR u.matric_id LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$stmt = $pdo->prepare("
    SELECT u.*,
    (SELECT status FROM friends WHERE (sender_id = ? AND receiver_id = u.id) OR (sender_id = u.id AND receiver_id = ?) LIMIT 1) AS friend_status,
    (SELECT sender_id FROM friends WHERE (sender_id = ? AND receiver_id = u.id) OR (sender_id = u.id AND receiver_id = ?) LIMIT 1) AS friend_sender
    FROM users u
    $where
    ORDER BY u.full_name $order
    LIMIT 50
");
$stmt->execute(array_merge([$uid, $uid, $uid, $uid], $params));
$users = $stmt->fetchAll();

render_head('Find People');
render_nav();
?>
<div class="mobile_topbar">
    <button class="mobile_toggle" onclick="document.querySelector('.sidebar').classList.toggle('open');document.querySelector('.sidebar_overlay').classList.toggle('open')">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <span class="mobile_topbar_title"><?= is_admin() ? 'People' : 'Find People' ?></span>
</div>
<div class="sidebar_overlay" onclick="document.querySelector('.sidebar').classList.remove('open');this.classList.remove('open')"></div>
<main class="main_content">
    <?php render_flash(); ?>
    <h1 class="page_title" style="margin-bottom:20px;"><?= is_admin() ? 'People' : 'Find People' ?></h1>

    <form method="GET" class="search_bar">
        <input class="field_input" type="text" name="q" value="<?= e($search) ?>" placeholder="Search by name or matric ID...">
        <input type="hidden" name="sort" value="<?= e($sort) ?>">
        <button type="submit" class="btn btn_primary">Search</button>
    </form>

    <div class="filter_row">
        <a href="?q=<?= urlencode($search) ?>&sort=asc" class="btn <?= $sort === 'asc' ? 'btn_primary' : 'btn_ghost' ?> btn_sm">A-Z</a>
        <a href="?q=<?= urlencode($search) ?>&sort=desc" class="btn <?= $sort === 'desc' ? 'btn_primary' : 'btn_ghost' ?> btn_sm">Z-A</a>
    </div>

    <?php if (empty($users)): ?>
    <div class="empty_state">
        <h3>No people found</h3>
        <p>Try a different search term.</p>
    </div>
    <?php else: ?>
    <div class="people_grid">
        <?php foreach ($users as $u): ?>
        <div class="person_card">
            <img src="<?= avatar_url($u['avatar']) ?>" alt="" class="person_avatar">
            <div class="person_info">
                <span class="person_name"><a href="<?= $base ?>/php/profile/view.php?id=<?= $u['id'] ?>"><?= e($u['full_name']) ?></a></span>
                <span class="person_id"><?= e($u['matric_id']) ?> <?= $u['program'] ? '- ' . e($u['program']) : '' ?></span>
                <?php if ($u['bio']): ?>
                <p class="person_bio"><?= e(mb_strimwidth($u['bio'] ?? '', 0, 100, '...')) ?></p>
                <?php endif; ?>
                <?php if (!is_admin()): ?>
                <div class="person_actions">
                    <?php if (!$u['friend_status']): ?>
                    <form method="POST" action="<?= $base ?>/php/api/friends.php" style="display:inline;">
                        <input type="hidden" name="action" value="send">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                        <button type="submit" class="btn btn_primary btn_sm">Add Friend</button>
                    </form>
                    <?php elseif ($u['friend_status'] === 'pending' && (int)$u['friend_sender'] === $u['id']): ?>
                    <form method="POST" action="<?= $base ?>/php/api/friends.php" style="display:inline;">
                        <input type="hidden" name="action" value="accept">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                        <button type="submit" class="btn btn_primary btn_sm">Accept</button>
                    </form>
                    <?php elseif ($u['friend_status'] === 'pending'): ?>
                    <form method="POST" action="<?= $base ?>/php/api/friends.php" style="display:inline;" id="cancel_form_<?= $u['id'] ?>">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                        <button type="button" class="btn btn_warning btn_sm" onclick="GSF.confirm('Cancel friend request to <?= e($u['full_name']) ?>?',function(){document.getElementById('cancel_form_<?= $u['id'] ?>').submit();})">Pending</button>
                    </form>
                    <?php elseif ($u['friend_status'] === 'accepted'): ?>
                    <span class="badge badge_info">Friends</span>
                    <?php endif; ?>
                    <button type="button" class="btn btn_ghost btn_sm" onclick="GSF.modal.open('report_modal');document.getElementById('report_user_id').value='<?= $u['id'] ?>';document.getElementById('report_matric').value='<?= e($u['matric_id']) ?>'">Report</button>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>

<?php if (!is_admin()): ?>
<div class="modal_overlay" id="report_modal">
    <div class="modal_content">
        <div class="modal_header">
            <h2 class="modal_title">Report User</h2>
            <button type="button" class="modal_close" onclick="GSF.modal.close('report_modal')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form method="POST" action="<?= $base ?>/php/api/reports.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="user_id" id="report_user_id">
            <input type="hidden" name="reported_matric" id="report_matric">
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