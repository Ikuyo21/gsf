<?php
function render_head(string $title = 'GSFinder'): void {
    $base = base_url();
    $font = e(user_pref('font_choice', 'Satoshi'));
    $primary = e(user_pref('primary_color', '#009688'));
    $secondary = e(user_pref('secondary_color', '#ffffff'));
    $text = e(user_pref('text_color', '#333333'));
    $bg_type = e(user_pref('bg_type', 'default'));
    $bg_color = e(user_pref('bg_color', '#f0f2f5'));
    $bg_style = '';
    if ($bg_type === 'color') {
        $bg_style = 'background:' . $bg_color . ';';
    }
    $primary_is_light = is_light_color($primary);
    $GLOBALS['_primary_is_light'] = $primary_is_light;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> - GSFinder</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Satoshi:wght@300;400;500;700;900&family=JetBrains+Mono:wght@400;500;700&family=Caveat:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $base ?>/css/main.css">
    <link rel="stylesheet" href="<?= $base ?>/css/components.css">
    <style>
        :root {
            --primary: <?= $primary ?>;
            --primary-dark: color-mix(in srgb, <?= $primary ?> 80%, #000);
            --primary-light: color-mix(in srgb, <?= $primary ?> 20%, #fff);
            --secondary: <?= $secondary ?>;
            --text: <?= $text ?>;
            --font: '<?= $font ?>', sans-serif;
        }
        <?php if ($bg_style): ?>
        body.has_custom_bg { <?= $bg_style ?> }
        <?php endif; ?>
    </style>
</head>
<body class="<?= $bg_type !== 'default' ? 'has_custom_bg' : '' ?>" data-font="<?= $font ?>" data-primary-light="<?= $primary_is_light ? '1' : '0' ?>">
<?php
}

function render_nav(): void {
    $base = base_url();
    $name = e(current_user_name());
    $avatar = avatar_url(current_user_avatar());
    $role = current_user_role();
    $current = $_SERVER['REQUEST_URI'] ?? '';
?>
<aside class="sidebar<?= (isset($GLOBALS['_primary_is_light']) && $GLOBALS['_primary_is_light']) ? ' sidebar_dark_text' : '' ?>" id="main_sidebar">
    <div class="sidebar_top">
        <div class="sidebar_brand">
            <img src="<?= $base ?>/css/logo_umpsa.png" alt="UMPSA" class="sidebar_logo_img">
            <span class="sidebar_title">Study Group Finder</span>
        </div>
    </div>
    <nav class="sidebar_nav">
        <?php if ($role === 'Admin'): ?>
        <a href="<?= $base ?>/php/admin/" class="sidebar_link<?= str_contains($current, '/admin') ? ' active' : '' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
            <span class="sidebar_link_text">Dashboard</span>
        </a>
        <a href="<?= $base ?>/php/people/" class="sidebar_link<?= str_contains($current, '/people') ? ' active' : '' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
            <span class="sidebar_link_text">People</span>
        </a>
        <?php else: ?>
        <a href="<?= $base ?>/php/dashboard/" class="sidebar_link<?= str_contains($current, '/dashboard') ? ' active' : '' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
            <span class="sidebar_link_text">Dashboard</span>
        </a>
        <a href="<?= $base ?>/php/groups/" class="sidebar_link<?= str_contains($current, '/groups') && !str_contains($current, '/create') ? ' active' : '' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
            <span class="sidebar_link_text">Study Groups</span>
        </a>
        <a href="<?= $base ?>/php/groups/create.php" class="sidebar_link<?= str_contains($current, '/create') ? ' active' : '' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
            <span class="sidebar_link_text">Create Group</span>
        </a>
        <a href="<?= $base ?>/php/people/" class="sidebar_link<?= str_contains($current, '/people') ? ' active' : '' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <span class="sidebar_link_text">Find People</span>
        </a>
        <a href="<?= $base ?>/php/friends/" class="sidebar_link<?= str_contains($current, '/friends') ? ' active' : '' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
            <span class="sidebar_link_text">Friends</span>
        </a>
        <a href="<?= $base ?>/php/progress/" class="sidebar_link<?= str_contains($current, '/progress') ? ' active' : '' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            <span class="sidebar_link_text">Study Progress</span>
        </a>
        <a href="<?= $base ?>/php/profile/" class="sidebar_link<?= str_contains($current, '/profile') ? ' active' : '' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span class="sidebar_link_text">Profile</span>
        </a>
        <?php endif; ?>
    </nav>
    <div class="sidebar_bottom">
        <button class="sidebar_collapse_btn" id="sidebar_collapse_btn" title="Collapse sidebar">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="11 17 6 12 11 7"/><polyline points="18 17 13 12 18 7"/></svg>
        </button>
        <div class="sidebar_user">
            <img src="<?= $avatar ?>" alt="" class="sidebar_avatar">
            <div class="sidebar_user_info">
                <span class="sidebar_user_name"><?= $name ?></span>
                <span class="sidebar_user_role"><?= e($role) ?></span>
            </div>
        </div>
        <a href="<?= $base ?>/php/auth/logout.php" class="sidebar_link sidebar_logout">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            <span class="sidebar_link_text">Logout</span>
        </a>
    </div>
</aside>
<?php
}

function render_flash(): void {
    $flash = get_flash();
    if ($flash): ?>
    <div class="flash flash_<?= e($flash['type']) ?>" id="flash_msg">
        <span><?= e($flash['msg']) ?></span>
        <button type="button" class="flash_close" onclick="this.parentElement.remove()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
    <?php endif;
}

function render_footer(): void {
    $base = base_url();
?>
    <script>window.BASE_URL = '<?= $base ?>';</script>
    <script src="<?= $base ?>/js/components.js"></script>
    <script src="<?= $base ?>/js/app.js"></script>
</body>
</html>
<?php
}
