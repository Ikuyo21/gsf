<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/layout.php';
require_student();

$uid = current_user_id();
$base = base_url();

$stmt = $pdo->query('SELECT subject_code, subject_name FROM subjects ORDER BY subject_name ASC');
$subjects = $stmt->fetchAll();

render_head('Create Group');
render_nav();
?>
<div class="mobile_topbar">
    <button class="mobile_toggle" onclick="document.querySelector('.sidebar').classList.toggle('open');document.querySelector('.sidebar_overlay').classList.toggle('open')">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <span class="mobile_topbar_title">Create Group</span>
</div>
<div class="sidebar_overlay" onclick="document.querySelector('.sidebar').classList.remove('open');this.classList.remove('open')"></div>
<main class="main_content">
    <?php render_flash(); ?>
    <h1 class="page_title" style="margin-bottom:20px;">Create a Study Group</h1>
    <div class="profile_section" style="max-width:600px;">
        <form method="POST" action="<?= $base ?>/php/api/groups.php">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <div class="field_group">
                <label class="field_label" for="name">Group Name *</label>
                <input class="field_input" type="text" id="name" name="name" placeholder="e.g. Data Communication Study Group" required>
            </div>
            <div class="field_group">
                <label class="field_label" for="description">Description</label>
                <textarea class="field_textarea" id="description" name="description" placeholder="What will this group focus on?" rows="3"></textarea>
            </div>
            <div class="field_group">
                <label class="field_label" for="subject_code">Subject (optional)</label>
                <select class="field_select" id="subject_code" name="subject_code">
                    <option value="">No specific subject</option>
                    <?php foreach ($subjects as $s): ?>
                    <option value="<?= e($s['subject_code']) ?>"><?= e($s['subject_code']) ?> - <?= e($s['subject_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field_group">
                <label class="field_label" for="visibility">Visibility</label>
                <select class="field_select" id="visibility" name="visibility">
                    <option value="public">Public</option>
                    <option value="private">Private</option>
                </select>
            </div>
            <div class="field_group">
                <label class="field_label" for="max_members">Max Members</label>
                <input class="field_input" type="number" id="max_members" name="max_members" value="15" min="2" max="50">
            </div>
            <button type="submit" class="btn btn_primary">Create Group</button>
        </form>
    </div>
</main>
<?php render_footer(); ?>
