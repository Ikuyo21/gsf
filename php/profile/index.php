<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/layout.php';
require_student();

$uid = current_user_id();
$base = base_url();
$error = '';
$success = '';

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$uid]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? 'profile';

    if ($action === 'profile') {
        $bio = trim($_POST['bio'] ?? '');
        $new_pass = $_POST['new_password'] ?? '';

        $allowed_img_ext = ['jpg','jpeg','png','gif','webp'];
        $invalid_format = false;

        $avatar_name = $user['avatar'];
        if (!empty($_FILES['avatar']['name'])) {
            $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_img_ext)) {
                $error = 'Avatar image format not supported. Please try another format (JPG, JPEG, PNG, GIF, or WEBP).';
                $invalid_format = true;
            } elseif ($_FILES['avatar']['size'] <= 5242880) {
                $avatar_name = $uid . '_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['avatar']['tmp_name'], __DIR__ . '/../../uploads/avatars/' . $avatar_name);
            }
        }

        $banner_name = $user['banner'];
        if (!$invalid_format && !empty($_FILES['banner']['name'])) {
            $ext = strtolower(pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_img_ext)) {
                $error = 'Banner image format not supported. Please try another format (JPG, JPEG, PNG, GIF, or WEBP).';
                $invalid_format = true;
            } elseif ($_FILES['banner']['size'] <= 5242880) {
                $banner_name = $uid . '_banner_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['banner']['tmp_name'], __DIR__ . '/../../uploads/banners/' . $banner_name);
            }
        }

        if (!$invalid_format) {
            $stmt = $pdo->prepare('UPDATE users SET bio = ?, avatar = ?, banner = ? WHERE id = ?');
            $stmt->execute([$bio ?: null, $avatar_name, $banner_name, $uid]);

            if ($new_pass && strlen($new_pass) >= 6) {
                $hash = password_hash($new_pass, PASSWORD_BCRYPT);
                $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$hash, $uid]);
            }

            $_SESSION['user_avatar'] = $avatar_name ?? '';
            $success = 'Profile updated.';

            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$uid]);
            $user = $stmt->fetch();
        }
    }

    if ($action === 'preferences') {
        $primary = $_POST['primary_color'] ?? '#009688';
        $secondary = $_POST['secondary_color'] ?? '#ffffff';
        $text_color = $_POST['text_color'] ?? '#333333';
        $font = $_POST['font_choice'] ?? 'Satoshi';

        if (!in_array($font, ['Satoshi', 'JetBrains Mono', 'Caveat'])) $font = 'Satoshi';

        $stmt = $pdo->prepare('UPDATE users SET primary_color = ?, secondary_color = ?, text_color = ?, font_choice = ? WHERE id = ?');
        $stmt->execute([$primary, $secondary, $text_color, $font, $uid]);

        load_user_prefs($pdo, $uid);
        $success = 'Preferences saved. Refresh to see full changes.';

        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$uid]);
        $user = $stmt->fetch();
    }

    if ($action === 'background') {
        $bg_type = $_POST['bg_type'] ?? 'default';

        if ($bg_type === 'color') {
            $bg_color = $_POST['bg_color'] ?? '#f0f2f5';
            $pdo->prepare('UPDATE users SET bg_type = ?, bg_color = ? WHERE id = ?')->execute([$bg_type, $bg_color, $uid]);
        } else {
            $pdo->prepare('UPDATE users SET bg_type = ? WHERE id = ?')->execute(['default', $uid]);
        }

        load_user_prefs($pdo, $uid);
        $success = 'Background updated.';

        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$uid]);
        $user = $stmt->fetch();
    }
}

$stmt = $pdo->prepare("SELECT g.name, g.id FROM groups_ g JOIN group_members gm ON gm.group_id = g.id WHERE gm.user_id = ? ORDER BY g.name ASC");
$stmt->execute([$uid]);
$groups = $stmt->fetchAll();

$banner = banner_url($user['banner']);

$bg_swatches = ['#f0f2f5','#1a1a2e','#16213e','#0f3460','#533483','#2c3e50','#1b4332','#3c1642','#2d2d2d','#f5e6cc','#fce4ec','#e8f5e9','#fff3e0','#e3f2fd','#f3e5f5','#fffde7'];

render_head('Profile');
render_nav();
?>
<div class="mobile_topbar">
    <button class="mobile_toggle" onclick="document.querySelector('.sidebar').classList.toggle('open');document.querySelector('.sidebar_overlay').classList.toggle('open')">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <span class="mobile_topbar_title">Profile</span>
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
    </div>

    <?php if ($error): ?>
    <div class="flash flash_error" style="position:static;margin-bottom:16px;"><?= e($error) ?></div>
    <?php elseif ($success): ?>
    <div class="flash flash_success" style="position:static;margin-bottom:16px;"><?= e($success) ?></div>
    <?php endif; ?>

    <div class="profile_section">
        <h3 class="profile_section_title">Edit Profile</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="form_action" value="profile">
            <div class="field_group">
                <label class="field_label">Bio</label>
                <textarea class="field_textarea" name="bio" rows="3" placeholder="Tell us about yourself..."><?= e($user['bio'] ?? '') ?></textarea>
            </div>
            <div class="pref_row">
                <div class="field_group">
                    <label class="field_label">Avatar</label>
                    <div class="file_upload">
                        <div class="file_upload_icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </div>
                        <div class="file_upload_text">
                            <span class="file_upload_label">Change avatar</span>
                            <span class="file_upload_hint">PNG, JPG up to 5MB</span>
                        </div>
                        <img class="file_upload_preview" alt="">
                        <input type="file" name="avatar" accept="image/*">
                    </div>
                </div>
                <div class="field_group">
                    <label class="field_label">Banner</label>
                    <div class="file_upload">
                        <div class="file_upload_icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        </div>
                        <div class="file_upload_text">
                            <span class="file_upload_label">Change banner</span>
                            <span class="file_upload_hint">PNG, JPG up to 5MB</span>
                        </div>
                        <img class="file_upload_preview" alt="">
                        <input type="file" name="banner" accept="image/*">
                    </div>
                </div>
            </div>
            <div class="field_group">
                <label class="field_label">New Password (leave blank to keep current)</label>
                <input class="field_input" type="password" name="new_password" placeholder="Minimum 6 characters">
            </div>
            <button type="submit" class="btn btn_primary">Save Profile</button>
        </form>
    </div>

    <div class="profile_section">
        <h3 class="profile_section_title">Customize Appearance</h3>
        <form method="POST">
            <input type="hidden" name="form_action" value="preferences">
            <div class="pref_row">
                <div class="field_group">
                    <label class="field_label">Primary Color</label>
                    <div class="color_sync_group">
                        <input type="color" name="primary_color" class="color_sync_picker" value="<?= e($user['primary_color'] ?? '#009688') ?>">
                        <input type="text" class="color_hex_input color_sync_hex" value="<?= e($user['primary_color'] ?? '#009688') ?>" maxlength="7" spellcheck="false">
                    </div>
                </div>
                <div class="field_group">
                    <label class="field_label">Secondary Color (cards)</label>
                    <div class="color_sync_group">
                        <input type="color" name="secondary_color" class="color_sync_picker" value="<?= e($user['secondary_color'] ?? '#ffffff') ?>">
                        <input type="text" class="color_hex_input color_sync_hex" value="<?= e($user['secondary_color'] ?? '#ffffff') ?>" maxlength="7" spellcheck="false">
                    </div>
                </div>
                <div class="field_group">
                    <label class="field_label">Text Color</label>
                    <div class="color_sync_group">
                        <input type="color" name="text_color" class="color_sync_picker" value="<?= e($user['text_color'] ?? '#333333') ?>">
                        <input type="text" class="color_hex_input color_sync_hex" value="<?= e($user['text_color'] ?? '#333333') ?>" maxlength="7" spellcheck="false">
                    </div>
                </div>
            </div>
            <div class="field_group">
                <label class="field_label">Font</label>
                <div class="font_picker">
                    <?php
                    $fonts = ['Satoshi', 'JetBrains Mono', 'Caveat'];
                    $current_font = $user['font_choice'] ?? 'Satoshi';
                    foreach ($fonts as $f):
                    ?>
                    <label class="font_option<?= $current_font === $f ? ' active' : '' ?>" data-font="<?= $f ?>" style="font-family:'<?= $f ?>',sans-serif;">
                        <input type="radio" name="font_choice" value="<?= $f ?>" <?= $current_font === $f ? 'checked' : '' ?> style="display:none;">
                        <?= $f ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <button type="submit" class="btn btn_primary" style="margin-top:8px;">Save Preferences</button>
        </form>
    </div>

    <div class="profile_section">
        <h3 class="profile_section_title">Background</h3>
        <form method="POST">
            <input type="hidden" name="form_action" value="background">
            <div class="field_group">
                <label class="field_label">Background Type</label>
                <div class="card_style_picker">
                    <?php $cur_bg = $user['bg_type'] ?? 'default'; ?>
                    <label class="card_style_option<?= $cur_bg === 'default' ? ' active' : '' ?>">
                        <input type="radio" name="bg_type" value="default" <?= $cur_bg === 'default' ? 'checked' : '' ?> style="display:none;" onchange="document.getElementById('bg_color_section').style.display='none';">
                        Default
                    </label>
                    <label class="card_style_option<?= $cur_bg === 'color' ? ' active' : '' ?>">
                        <input type="radio" name="bg_type" value="color" <?= $cur_bg === 'color' ? 'checked' : '' ?> style="display:none;" onchange="document.getElementById('bg_color_section').style.display='block';">
                        Solid Color
                    </label>
                </div>
            </div>

            <div class="field_group" id="bg_color_section" style="display:<?= $cur_bg === 'color' ? 'block' : 'none' ?>;">
                <label class="field_label">Pick a Color</label>
                <div class="bg_option_grid">
                    <?php foreach ($bg_swatches as $sw): ?>
                    <label class="bg_color_swatch<?= ($user['bg_color'] ?? '') === $sw ? ' active' : '' ?>" style="background:<?= $sw ?>;">
                        <input type="radio" name="bg_color" value="<?= $sw ?>" <?= ($user['bg_color'] ?? '') === $sw ? 'checked' : '' ?> style="display:none;">
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="color_sync_group" style="margin-top:10px;">
                    <input type="color" class="color_sync_picker" value="<?= e($user['bg_color'] ?? '#f0f2f5') ?>" onchange="this.nextElementSibling.value=this.value;document.querySelector('input[name=bg_color]:checked')&&(document.querySelector('input[name=bg_color]:checked').checked=false);">
                    <input type="text" class="color_hex_input color_sync_hex" value="<?= e($user['bg_color'] ?? '#f0f2f5') ?>" maxlength="7" spellcheck="false" onchange="this.previousElementSibling.value=this.value;">
                </div>
            </div>

            <button type="submit" class="btn btn_primary" style="margin-top:8px;">Save Background</button>
        </form>
    </div>

    <?php if (!empty($groups)): ?>
    <div class="profile_section">
        <h3 class="profile_section_title">My Groups</h3>
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
<script>
document.addEventListener('DOMContentLoaded', function() {
    function hexLuminance(hex) {
        hex = hex.replace('#', '');
        if (hex.length !== 6) return 0;
        var r = parseInt(hex.substr(0,2),16)/255;
        var g = parseInt(hex.substr(2,2),16)/255;
        var b = parseInt(hex.substr(4,2),16)/255;
        r = r <= 0.03928 ? r/12.92 : Math.pow((r+0.055)/1.055, 2.4);
        g = g <= 0.03928 ? g/12.92 : Math.pow((g+0.055)/1.055, 2.4);
        b = b <= 0.03928 ? b/12.92 : Math.pow((b+0.055)/1.055, 2.4);
        return 0.2126*r + 0.7152*g + 0.0722*b;
    }

    function updateSidebarTextColor(primary) {
        var sidebar = document.getElementById('main_sidebar');
        if (!sidebar) return;
        if (hexLuminance(primary) > 0.45) {
            sidebar.classList.add('sidebar_dark_text');
        } else {
            sidebar.classList.remove('sidebar_dark_text');
        }
    }

    var primaryPicker = document.querySelector('input[name="primary_color"]');
    var secondaryPicker = document.querySelector('input[name="secondary_color"]');
    var textPicker = document.querySelector('input[name="text_color"]');

    if (primaryPicker) {
        primaryPicker.addEventListener('input', function() {
            var val = this.value;
            document.documentElement.style.setProperty('--primary', val);
            document.documentElement.style.setProperty('--primary-dark', val);
            document.documentElement.style.setProperty('--primary-light', val + '33');
            updateSidebarTextColor(val);
        });
    }
    if (secondaryPicker) {
        secondaryPicker.addEventListener('input', function() {
            document.documentElement.style.setProperty('--secondary', this.value);
        });
    }
    if (textPicker) {
        textPicker.addEventListener('input', function() {
            document.documentElement.style.setProperty('--text', this.value);
        });
    }

    document.querySelectorAll('.font_option').forEach(function(opt) {
        opt.addEventListener('click', function() {
            var fontName = opt.getAttribute('data-font');
            document.documentElement.style.setProperty('--font', "'" + fontName + "', sans-serif");
        });
    });

    document.querySelectorAll('.color_sync_group').forEach(function(group) {
        var colorInput = group.querySelector('.color_sync_picker');
        var hexInput = group.querySelector('.color_sync_hex');
        if (colorInput && hexInput) {
            colorInput.addEventListener('input', function() {
                hexInput.value = colorInput.value;
            });
            hexInput.addEventListener('input', function() {
                if (/^#[0-9a-fA-F]{6}$/.test(hexInput.value)) {
                    colorInput.value = hexInput.value;
                    colorInput.dispatchEvent(new Event('input', {bubbles:true}));
                }
            });
        }
    });
});
</script>
<?php render_footer(); ?>