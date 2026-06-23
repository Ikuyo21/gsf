<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/layout.php';
require_student();

$uid  = current_user_id();
$gid  = (int)($_GET['id'] ?? 0);
$base = base_url();

$stmt = $pdo->prepare('SELECT bg_type, bg_color FROM users WHERE id = ?');
$stmt->execute([$uid]);
$user_bg = $stmt->fetch();
$bg_color = ($user_bg && $user_bg['bg_type'] === 'color' && $user_bg['bg_color']) ? $user_bg['bg_color'] : null;

update_last_active($pdo, $uid);

if (!$gid) { header('Location: '.$base.'/php/groups/'); exit; }

$stmt = $pdo->prepare('SELECT g.*, u.full_name AS leader_name FROM groups_ g JOIN users u ON u.id = g.leader_id WHERE g.id = ?');
$stmt->execute([$gid]);
$group = $stmt->fetch();

if (!$group) { set_flash('error','Group not found.'); header('Location: '.$base.'/php/groups/'); exit; }

$stmt = $pdo->prepare('SELECT COUNT(*) FROM group_members WHERE group_id = ? AND user_id = ?');
$stmt->execute([$gid, $uid]);
$is_member = (bool)$stmt->fetchColumn();
$is_leader = (int)$group['leader_id'] === $uid;

if (!$is_member && $group['visibility'] === 'private') {
    set_flash('error','This is a private group.');
    header('Location: '.$base.'/php/groups/'); exit;
}

$stmt = $pdo->prepare('SELECT gm.*, u.full_name AS name, u.matric_id, u.avatar, u.last_active FROM group_members gm JOIN users u ON u.id = gm.user_id WHERE gm.group_id = ? ORDER BY u.full_name ASC');
$stmt->execute([$gid]);
$members      = $stmt->fetchAll();
$member_count = count($members);

$friends_not_in_group = [];
if ($is_member) {
    $member_ids    = array_column($members, 'user_id');
    $ph_m          = implode(',', array_fill(0, count($member_ids), '?'));
    $stmt          = $pdo->prepare("SELECT u.id, u.full_name, u.matric_id, u.avatar FROM friends f JOIN users u ON u.id = CASE WHEN f.sender_id = ? THEN f.receiver_id ELSE f.sender_id END WHERE (f.sender_id = ? OR f.receiver_id = ?) AND f.status = 'accepted' AND u.id NOT IN ($ph_m) ORDER BY u.full_name ASC");
    $stmt->execute(array_merge([$uid, $uid, $uid], $member_ids));
    $friends_not_in_group = $stmt->fetchAll();
}

render_head($group['name']);
render_nav();
?>


<style>
<?php if ($bg_color): ?>
.chat_sidebar { background: <?= e($bg_color) ?> !important; }
.chat_layout { background: <?= e($bg_color) ?> !important; }
.chat_main { background: <?= e($bg_color) ?> !important; }
<?php endif; ?>
</style>

<style>
@media (max-width: 768px) {
    .main_content.chat_view_main { padding-top: 56px !important; }
}
</style>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">

<div class="mobile_topbar">
    <button class="mobile_toggle" onclick="toggleMainSidebar()">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <span class="mobile_topbar_title"><b>Live chat</b></span>
    <button class="mobile_toggle" onclick="toggleChatSidebar()" style="margin-left:auto;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
    </button>
</div>

<div class="sidebar_overlay" onclick="toggleMainSidebar()"></div>
<main class="main_content chat_view_main" style="transition:margin-left 280ms var(--ease-out);">
    <div class="chat_layout">
        <div class="chat_sidebar_overlay" onclick="toggleChatSidebar()"></div>

        <div class="chat_sidebar">
            <div class="chat_sidebar_header">
                <div>
                    <h2 class="chat_group_name"><?= e($group['name']) ?></h2>
                    <span class="badge badge_<?= $group['visibility'] ?>"><?= $group['visibility'] ?></span>
                </div>
            </div>
            <p class="chat_group_desc"><?= e($group['description'] ?? '') ?></p>
            <div class="chat_sidebar_section">
                <h3 class="chat_sidebar_label">Members (<?= $member_count ?>/<?= $group['max_members'] ?>)</h3>
                <div class="chat_member_list">
                    <?php foreach ($members as $m):
                        $is_online = $m['last_active'] && (time() - strtotime($m['last_active'])) < 300;
                    ?>
                    <div class="chat_member">
                        <div class="chat_member_avatar_wrap">
                            <img src="<?= avatar_url($m['avatar']) ?>" alt="" class="chat_member_avatar">
                            <span class="online_dot<?= $is_online ? '' : ' offline' ?>"></span>
                        </div>
                        <div>
                            <span class="chat_member_name"><?= e($m['name']) ?><?php if ((int)$m['user_id'] === (int)$group['leader_id']): ?><span class="leader_badge">Leader</span><?php endif; ?></span>
                            <span class="chat_member_id"><?= e($m['matric_id']) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="chat_main">
            <div class="chat_topbar">
                <button type="button" class="chat_sidebar_toggle" onclick="toggleChatSidebar()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <span class="chat_topbar_name"><b>Live chat</b></span>
                <span class="chat_topbar_members">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                    <?= $member_count ?>
                </span>
                <?php if ($is_member): ?>
                <div style="position:relative;">
                    <button type="button" class="chat_three_dots" onclick="this.nextElementSibling.classList.toggle('open')">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
                    </button>
                    <div class="chat_dots_menu">
                        <?php
                        $can_add = ($group['visibility'] === 'public' && $is_member) || ($group['visibility'] === 'private' && $is_leader);
                        ?>
                        <?php if ($can_add && !empty($friends_not_in_group)): ?>
                        <button type="button" class="chat_dots_menu_item" onclick="GSF.modal.open('add_people_modal');this.closest('.chat_dots_menu').classList.remove('open')">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                            Add People
                        </button>
                        <?php endif; ?>
                        <?php if ($is_leader && $member_count > 1): ?>
                        <button type="button" class="chat_dots_menu_item" onclick="GSF.modal.open('kick_member_modal');this.closest('.chat_dots_menu').classList.remove('open')">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="18" y1="8" x2="23" y2="13"/><line x1="23" y1="8" x2="18" y2="13"/></svg>
                            Kick Member
                        </button>
                        <?php endif; ?>
                        <?php if ($is_leader && $member_count > 1): ?>
                        <button type="button" class="chat_dots_menu_item danger" onclick="this.closest('.chat_dots_menu').classList.remove('open');GSF.confirm('Are you sure you want to leave? A random member will become the new leader.',function(){document.getElementById('leave_form').submit();})">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            Leave Group
                        </button>
                        <?php endif; ?>
                        <?php if (!$is_leader): ?>
                        <button type="button" class="chat_dots_menu_item danger" onclick="this.closest('.chat_dots_menu').classList.remove('open');GSF.confirm('Are you sure you want to leave this group?',function(){document.getElementById('leave_form').submit();})">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            Leave Group
                        </button>
                        <?php endif; ?>
                        <?php if ($is_leader): ?>
                        <button type="button" class="chat_dots_menu_item danger" onclick="this.closest('.chat_dots_menu').classList.remove('open');GSF.confirm('Delete this group permanently? All messages will be lost.',function(){document.getElementById('delete_form').submit();})">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                            Delete Group
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="chat_messages" id="chat_messages" data-group-id="<?= $gid ?>">
                <div class="chat_welcome">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity=".4"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                    <h3>Welcome to <?= e($group['name']) ?></h3>
                    <p>This is the beginning of your conversation.</p>
                </div>
            </div>

            <button type="button" class="chat_scroll_bottom" id="chat_scroll_bottom" title="Jump to latest">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
            </button>

            <?php if ($is_member): ?>
            <div class="chat_input_area">
                <div class="chat_file_preview" id="chat_file_preview" style="display:none;"></div>
                <form id="chat_form" class="chat_form" autocomplete="off" enctype="multipart/form-data">
                    <input type="hidden" name="group_id" value="<?= $gid ?>">
                    <input type="file" id="chat_file_input" name="attachment" style="display:none;"
                           accept="image/*,.pdf,.doc,.docx,.txt,.zip,.rar,.pptx,.xlsx,.csv,.mp4,.mp3">

                    <button type="button" class="chat_attach_btn" id="chat_attach_btn" title="Attach file (image, doc, .mp4, .mp3)">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg>
                    </button>

                    <?php if ($is_leader): ?>
                    <button type="button" class="chat_session_btn" id="chat_session_btn" title="Schedule Study Session">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </button>
                    <?php endif; ?>

                    <input class="field_input chat_input" type="text" name="content" id="chat_input"  maxlength="2000">
                    <button type="submit" class="chat_send_btn">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    </button>
                </form>
            </div>
            <?php else: ?>
            <div class="chat_input_locked">
                <p>Join this group to send messages.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($is_member): ?>
    <form id="leave_form" method="POST" action="<?= $base ?>/php/api/groups.php" style="display:none;">
        <input type="hidden" name="action"   value="leave">
        <input type="hidden" name="group_id" value="<?= $gid ?>">
        <input type="hidden" name="csrf"     value="<?= csrf_token() ?>">
    </form>
    <?php endif; ?>
    <?php if ($is_leader): ?>
    <form id="delete_form" method="POST" action="<?= $base ?>/php/api/groups.php" style="display:none;">
        <input type="hidden" name="action"   value="delete">
        <input type="hidden" name="group_id" value="<?= $gid ?>">
        <input type="hidden" name="csrf"     value="<?= csrf_token() ?>">
    </form>
    <form id="kick_form" method="POST" action="<?= $base ?>/php/api/groups.php" style="display:none;">
        <input type="hidden" name="action"   value="kick">
        <input type="hidden" name="group_id" value="<?= $gid ?>">
        <input type="hidden" name="user_id"  id="kick_user_id" value="">
        <input type="hidden" name="csrf"     value="<?= csrf_token() ?>">
    </form>
    <?php endif; ?>
</main>

<!-- ── Add People Modal ── -->
<?php if ($can_add && !empty($friends_not_in_group)): ?>
<div class="modal_overlay" id="add_people_modal">
    <div class="modal_content">
        <div class="modal_header">
            <h2 class="modal_title">Add People</h2>
            <button type="button" class="modal_close" onclick="GSF.modal.close('add_people_modal')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="add_people_search">
            <input type="text" id="add_people_search_input" placeholder="Search friends..." oninput="filterAddPeople(this.value)">
        </div>
        <div class="add_people_list" id="add_people_list">
            <?php foreach ($friends_not_in_group as $f): ?>
            <div class="add_people_item" data-name="<?= e(strtolower($f['full_name'])) ?>" data-matric="<?= e(strtolower($f['matric_id'])) ?>">
                <img src="<?= avatar_url($f['avatar']) ?>" alt="">
                <div class="add_people_item_info">
                    <div class="add_people_item_name"><?= e($f['full_name']) ?></div>
                    <div class="add_people_item_id"><?= e($f['matric_id']) ?></div>
                </div>
                <form method="POST" action="<?= $base ?>/php/api/groups.php">
                    <input type="hidden" name="action"   value="add_member">
                    <input type="hidden" name="group_id" value="<?= $gid ?>">
                    <input type="hidden" name="user_id"  value="<?= $f['id'] ?>">
                    <input type="hidden" name="csrf"     value="<?= csrf_token() ?>">
                    <button type="submit" class="btn btn_primary btn_sm">Add</button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Kick Member Modal ── -->
<?php if ($is_leader && $member_count > 1): ?>
<div class="modal_overlay" id="kick_member_modal">
    <div class="modal_content">
        <div class="modal_header">
            <h2 class="modal_title">Kick Member</h2>
            <button type="button" class="modal_close" onclick="GSF.modal.close('kick_member_modal')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="add_people_search">
            <input type="text" placeholder="Search members..." oninput="filterKickMembers(this.value)">
        </div>
        <div class="kick_member_list" id="kick_member_list">
            <?php foreach ($members as $m): if ((int)$m['user_id'] === $uid) continue; ?>
            <div class="kick_member_item" data-name="<?= e(strtolower($m['name'])) ?>" data-matric="<?= e(strtolower($m['matric_id'])) ?>" onclick="confirmKick(<?= (int)$m['user_id'] ?>, '<?= e(addslashes($m['name'])) ?>')">
                <img src="<?= avatar_url($m['avatar']) ?>" alt="">
                <div class="kick_member_item_info">
                    <div class="kick_member_item_name"><?= e($m['name']) ?></div>
                    <div class="kick_member_item_id"><?= e($m['matric_id']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Study Session Modal (leader only) ── -->
<?php if ($is_leader): ?>
<div class="modal_overlay ss_modal_overlay" id="study_session_modal">
    <div class="modal_content ss_modal_content">
        <div class="modal_header">
            <div class="ss_modal_head_icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <h2 class="modal_title">Schedule Study Session</h2>
            <button type="button" class="modal_close" onclick="document.getElementById('study_session_modal').classList.remove('open')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="study_session_form" class="ss_form">
            <div class="field_group">
                <label class="field_label">Session Title <span class="field_optional">(optional)</span></label>
                <input type="text" id="ss_title" class="field_input" placeholder="e.g. Final Exam Review" maxlength="200">
            </div>
            <div class="ss_date_row">
                <div class="field_group">
                    <label class="field_label">Date <span class="field_req">*</span></label>
                    <input type="date" id="ss_date" class="field_input" required>
                </div>
            </div>
            <div class="ss_date_row">
                <div class="field_group">
                    <label class="field_label">Start Time <span class="field_req">*</span></label>
                    <input type="time" id="ss_time" class="field_input" required>
                </div>
                <div class="field_group">
                    <label class="field_label">End Time <span class="field_req">*</span></label>
                    <input type="time" id="ss_end_time" class="field_input" required>
                </div>
            </div>
            <p class="ss_time_hint">If the end time is earlier than the start time, the session is treated as ending the next day.</p>
            <div class="field_group">
                <label class="field_label">Location <span class="field_req">*</span></label>
                <input type="text" id="ss_location" class="field_input" placeholder="Library Room 3, Zoom, etc." required maxlength="500">
            </div>
            <div class="field_group">
                <label class="field_label">Meeting Link <span class="field_optional">(optional)</span></label>
                <input type="url" id="ss_link" class="field_input" placeholder="https://zoom.us/j/...">
            </div>
            <div class="ss_form_actions">
                <button type="button" class="ss_cancel_btn" onclick="document.getElementById('study_session_modal').classList.remove('open')">
                    Cancel
                </button>
                <button type="submit" class="ss_send_btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    Send Invitation
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="<?= $base ?>/js/chat.js"></script>
<script>
function toggleMainSidebar() {
    var sidebar   = document.querySelector('.sidebar');
    var overlay   = document.querySelector('.sidebar_overlay');
    var chatSb    = document.querySelector('.chat_sidebar');
    var chatOv    = document.querySelector('.chat_sidebar_overlay');
    if (chatSb && chatSb.classList.contains('open')) { chatSb.classList.remove('open'); if (chatOv) chatOv.classList.remove('open'); }
    if (sidebar) sidebar.classList.toggle('open');
    if (overlay) overlay.classList.toggle('open');
    updateBodyScroll();
}
function toggleChatSidebar() {
    var sidebar = document.querySelector('.sidebar');
    var overlay = document.querySelector('.sidebar_overlay');
    var chatSb  = document.querySelector('.chat_sidebar');
    var chatOv  = document.querySelector('.chat_sidebar_overlay');
    if (sidebar && sidebar.classList.contains('open')) { sidebar.classList.remove('open'); if (overlay) overlay.classList.remove('open'); }
    if (chatSb) chatSb.classList.toggle('open');
    if (chatOv) chatOv.classList.toggle('open');
    updateBodyScroll();
}
function closeAllSidebars() {
    ['sidebar','sidebar_overlay','chat_sidebar','chat_sidebar_overlay'].forEach(function (cls) {
        var el = document.querySelector('.' + cls);
        if (el) el.classList.remove('open');
    });
    updateBodyScroll();
}
function updateBodyScroll() {
    var sb  = document.querySelector('.sidebar');
    var csb = document.querySelector('.chat_sidebar');
    var any = (sb && sb.classList.contains('open')) || (csb && csb.classList.contains('open'));
    document.body.classList.toggle('no-scroll', any);
}
document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAllSidebars(); });
document.addEventListener('click', function (e) {
    var csb = document.querySelector('.chat_sidebar');
    var cov = document.querySelector('.chat_sidebar_overlay');
    var ctg = document.querySelector('.chat_sidebar_toggle');
    if (csb && csb.classList.contains('open') && !csb.contains(e.target) && !(ctg && ctg.contains(e.target))) {
        csb.classList.remove('open');
        if (cov) cov.classList.remove('open');
        updateBodyScroll();
    }
});
document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('chat_messages');
    if (el && typeof GSF !== 'undefined' && GSF.chat) {
        GSF.chat.init(el.dataset.groupId, '<?= $base ?>', <?= $uid ?>, <?= $is_leader ? 'true' : 'false' ?>);
    }
    var sb = document.getElementById('main_sidebar');
    if (sb && localStorage.getItem('gsf_sidebar_collapsed') === '1') {
        sb.classList.add('collapsed');
        document.body.classList.add('sidebar_is_collapsed');
        var mc = document.querySelector('.main_content');
        if (mc) mc.style.marginLeft = 'var(--sidebar-collapsed)';
    }
});
function filterAddPeople(q) {
    q = q.toLowerCase();
    document.querySelectorAll('.add_people_item').forEach(function (item) {
        item.style.display = (item.dataset.name.includes(q) || item.dataset.matric.includes(q)) ? '' : 'none';
    });
}
function filterKickMembers(q) {
    q = q.toLowerCase();
    document.querySelectorAll('.kick_member_item').forEach(function (item) {
        item.style.display = (item.dataset.name.includes(q) || item.dataset.matric.includes(q)) ? '' : 'none';
    });
}
function confirmKick(userId, userName) {
    if (typeof GSF !== 'undefined' && GSF.modal) GSF.modal.close('kick_member_modal');
    if (typeof GSF !== 'undefined' && GSF.confirm) {
        GSF.confirm('Remove <strong>' + userName + '</strong> from this group?', function () {
            document.getElementById('kick_user_id').value = userId;
            document.getElementById('kick_form').submit();
        });
    } else if (confirm('Remove ' + userName + ' from this group?')) {
        document.getElementById('kick_user_id').value = userId;
        document.getElementById('kick_form').submit();
    }
}
</script>
<?php render_footer(); ?>
