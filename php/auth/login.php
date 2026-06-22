<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/db.php';
require_guest();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $matric = trim($_POST['matric_id'] ?? '');
    $pass = $_POST['password'] ?? '';

    if (!$matric || !$pass) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE matric_id = ?');
        $stmt->execute([$matric]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($pass, $user['password'])) {
            $error = 'Invalid matric ID or password.';
        } elseif ($user['banned_until'] && strtotime($user['banned_until']) > time()) {
            $remaining = (new DateTime())->diff(new DateTime($user['banned_until']));
            $error = 'Your account is banned for ' . $remaining->days . ' day(s). Reason: ' . ($user['ban_reason'] ?? 'Policy violation');
        } else {
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_avatar'] = $user['avatar'] ?? '';
            $_SESSION['user_matric'] = $user['matric_id'];
            load_user_prefs($pdo, (int) $user['id']);
            update_last_active($pdo, (int) $user['id']);

            if ($user['role'] === 'Admin') {
                header('Location: ' . base_url() . '/php/admin/');
            } else {
                header('Location: ' . base_url() . '/php/dashboard/');
            }
            exit;
        }
    }
}
$base = base_url();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - GSFinder</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Satoshi:wght@300;400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $base ?>/css/main.css">
</head>
<body class="auth_page">
    <div class="auth_card">
        <div class="auth_logo">
            <img src="<?= $base ?>/css/logo_umpsa.png" alt="UMPSA" class="auth_logo_img">
            <span class="auth_logo_text">Group Study Finder</span>
        </div>
        <h1 class="auth_title">Welcome back</h1>
        <p class="auth_subtitle">Sign in to start connecting with your peers.</p>
        <?php if ($error): ?>
        <div class="auth_error"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="field_group">
                <label class="field_label" for="matric_id">Matric ID</label>
                <input class="field_input" type="text" id="matric_id" name="matric_id" placeholder="Enter your matric id" value="<?= e($_POST['matric_id'] ?? '') ?>" required>
            </div>
            <div class="field_group">
                <label class="field_label" for="password">Password</label>
                <input class="field_input" type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>
            <button type="submit" class="btn btn_primary btn_full" style="margin-top:8px;">Sign In</button>
        </form>
        <p class="auth_footer">Contact your administrator if you don't have an account.</p>
        <p class="auth_footer">This website was made specificly for FKOM students</p>
    </div>
</body>
</html>
