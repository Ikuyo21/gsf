<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/layout.php';
require_student();

$uid = current_user_id();
$base = base_url();
$search = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'all';

$where = [];
$params = [];

if ($search) {
    $where[] = '(g.name LIKE ? OR g.description LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filter === 'joined') {
    $where[] = 'gm_user.user_id IS NOT NULL';
} elseif ($filter === 'leading') {
    $where[] = 'g.leader_id = ?';
    $params[] = $uid;
} elseif ($filter === 'public') {
    $where[] = "g.visibility = 'public'";
} elseif ($filter === 'private') {
    $where[] = "g.visibility = 'private'";
    $where[] = 'gm_user.user_id IS NOT NULL';
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT g.*, u.full_name AS leader_name,
    (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) AS member_count,
    gm_user.user_id AS is_member
    FROM groups_ g
    JOIN users u ON u.id = g.leader_id
    LEFT JOIN group_members gm_user ON gm_user.group_id = g.id AND gm_user.user_id = ?
    $where_sql
    ORDER BY g.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge([$uid], $params));
$groups = $stmt->fetchAll();

$visible_groups = array_filter($groups, function($g) {
    return $g['visibility'] === 'public' || $g['is_member'];
});

$filters = ['all' => 'All', 'joined' => 'Joined', 'leading' => 'Leading', 'public' => 'Public'];

render_head('Study Groups');
render_nav();
?>
<div class="mobile_topbar">
    <button class="mobile_toggle" onclick="document.querySelector('.sidebar').classList.toggle('open');document.querySelector('.sidebar_overlay').classList.toggle('open')">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <span class="mobile_topbar_title">Study Groups</span>
</div>
<div class="sidebar_overlay" onclick="document.querySelector('.sidebar').classList.remove('open');this.classList.remove('open')"></div>
<main class="main_content">
    <?php render_flash(); ?>
    <div class="page_header">
        <h1 class="page_title">Study Groups</h1>
        <a href="<?= $base ?>/php/groups/create.php" class="btn btn_primary">Create Group</a>
    </div>

    <form method="GET" class="search_bar">
        <input type="hidden" name="filter" value="<?= e($filter) ?>">
        <input class="field_input" type="text" name="q" value="<?= e($search) ?>" placeholder="Search groups...">
        <button type="submit" class="btn btn_primary">Search</button>
    </form>

    <div class="filter_row">
        <?php foreach ($filters as $key => $label): ?>
        <a href="?filter=<?= $key ?><?= $search ? '&q=' . urlencode($search) : '' ?>" class="btn <?= $filter === $key ? 'btn_primary' : 'btn_ghost' ?> btn_sm"><?= $label ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($visible_groups)): ?>
    <div class="empty_state">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        <h3>No groups found</h3>
        <p>Try a different search or create your own group.</p>
    </div>
    <?php else: ?>
    <div class="groups_grid">
        <?php foreach ($visible_groups as $g): ?>
        <div class="group_card">
            <div style="display:flex;align-items:center;gap:8px;">
                <span class="group_card_name"><a href="<?= $base ?>/php/groups/view.php?id=<?= $g['id'] ?>"><?= e($g['name']) ?></a></span>
                <span class="badge badge_<?= $g['visibility'] ?>"><?= $g['visibility'] ?></span>
            </div>
            <div class="group_card_desc"><?= e(mb_strimwidth($g['description'] ?? 'No description', 0, 120, '...')) ?></div>
            <div class="group_card_meta"><?= $g['member_count'] ?> / <?= $g['max_members'] ?> Members</div>
            <div class="group_card_footer">
                <span style="font-size:12px;color:var(--text-muted);">Led by <?= e($g['leader_name']) ?></span>
                <?php if (!$g['is_member']): ?>
                <form method="POST" action="<?= $base ?>/php/api/groups.php">
                    <input type="hidden" name="action" value="join">
                    <input type="hidden" name="group_id" value="<?= $g['id'] ?>">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    <button type="submit" class="btn btn_warning btn_sm">Join</button>
                </form>
                <?php else: ?>
                <span class="badge badge_info">Joined</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>
<?php render_footer(); ?>
