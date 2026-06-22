<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/db.php';
require_guest();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    $matric = strtoupper(trim($_POST['matric_id'] ?? ''));
    $name = trim($_POST['full_name'] ?? '');
    $pass = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';
    $program = trim($_POST['program'] ?? '');

    if (!verify_csrf($csrf)) {
        $error = 'Invalid token. Please try again.';
    } elseif (!$matric || !$name || !$pass) {
        $error = 'Please fill in all required fields.';
    } elseif (!preg_match(get_matric_pattern(), $matric)) {
        $error = 'id unknown';
    } elseif (strlen($pass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($pass !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE matric_id = ?');
        $stmt->execute([$matric]);
        if ($stmt->fetch()) {
            $error = 'This matric ID is already registered.';
        } else {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('INSERT INTO users (matric_id, full_name, password, role, approved, program) VALUES (?, ?, ?, "Student", 0, ?)');
            $stmt->execute([$matric, $name, $hash, $program ?: null]);
            $success = 'Account submitted. Please wait for admin approval before you can sign in.';
        }
    }
}
$base = base_url();
$programs = get_programs();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - GSFinder</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $base ?>/css/main.css">
</head>
<body class="auth_page">
    <div class="auth_card">
        <div class="auth_logo">
            <img src="<?= $base ?>/css/logo_umpsa.png" alt="UMPSA" class="auth_logo_img">
            <span class="auth_logo_text">Group Study Finder</span>
        </div>
        <h1 class="auth_title">Create account</h1>
        <p class="auth_subtitle">Register with your matric ID. An admin will review your request.</p>
        <?php if ($error): ?>
        <div class="auth_error"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="auth_error" style="background:#e6f7ee;color:#0a7d3e;border-color:#bfe6cf;"><?= e($success) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <div class="field_group">
                <label class="field_label" for="matric_id">Matric ID *</label>
                <input class="field_input" type="text" id="matric_id" name="matric_id" placeholder="e.g. RC24163" value="<?= e($_POST['matric_id'] ?? '') ?>" style="text-transform:uppercase;" required>
            </div>
            <div class="field_group">
                <label class="field_label" for="full_name">Full Name *</label>
                <input class="field_input" type="text" id="full_name" name="full_name" placeholder="Your full name" value="<?= e($_POST['full_name'] ?? '') ?>" required>
            </div>
            <div class="field_group">
                <label class="field_label" for="program">Program</label>
                <input class="field_input" type="text" id="program" name="program" value="<?= e($_POST['program'] ?? '') ?>" placeholder="Auto-filled from matric ID" readonly tabindex="-1">
            </div>
            <div class="field_group">
                <label class="field_label" for="password">Password *</label>
                <input class="field_input" type="password" id="password" name="password" placeholder="Minimum 6 characters" required>
            </div>
            <div class="field_group">
                <label class="field_label" for="password_confirm">Confirm Password *</label>
                <input class="field_input" type="password" id="password_confirm" name="password_confirm" placeholder="Re-enter password" required>
            </div>
            <button type="submit" class="btn btn_primary btn_full" style="margin-top:8px;">Submit for Approval</button>
        </form>
        <p class="auth_footer">Already have an account? <a href="<?= $base ?>/php/auth/login.php">Sign in</a></p>
    </div>
    <script>
    (function() {
        const map = <?= json_encode(get_prefix_program_map()) ?>;
        const matricInput = document.getElementById('matric_id');
        const programInput = document.getElementById('program');
        if (!matricInput || !programInput) return;
        matricInput.addEventListener('input', function() {
            const prefix = (this.value || '').substring(0, 2).toUpperCase();
            programInput.value = map[prefix] || '';
        });
    })();
    </script>
</body>
</html>