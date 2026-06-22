<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/db.php';
require_login();

$action = $_POST['action'] ?? '';
$csrf = $_POST['csrf'] ?? '';
$uid = current_user_id();
$base = base_url();

if (!verify_csrf($csrf)) {
    set_flash('error', 'Invalid request.');
    header('Location: ' . $base . '/php/dashboard/');
    exit;
}

if ($action === 'create') {
    require_login(); // Allow both Student and Admin to report
    $target = (int) ($_POST['user_id'] ?? 0);
    $reported_matric = trim($_POST['reported_matric'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    if (!$target || !$reason) {
        set_flash('error', 'Reason is required.');
        header('Location: ' . $base . '/php/people/');
        exit;
    }

    $image_path = null;
    if (!empty($_FILES['report_image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['report_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp']) && $_FILES['report_image']['size'] <= 5242880) {
            $image_path = $uid . '_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['report_image']['tmp_name'], __DIR__ . '/../../uploads/reports/' . $image_path);
        }
    }

    $reason = filter_bad_words($pdo, $reason);

    $stmt = $pdo->prepare('INSERT INTO reports (reporter_id, reported_id, reported_matric, reason, image_path, status, created_at) VALUES (?, ?, ?, ?, ?, "pending", NOW())');
    $stmt->execute([$uid, $target, $reported_matric, $reason, $image_path]);

    set_flash('success', 'Report submitted.');
    header('Location: ' . $base . '/php/people/');
    exit;
}

if ($action === 'resolve') {
    require_admin();
    $rid = (int) ($_POST['report_id'] ?? 0);
    $pdo->prepare('UPDATE reports SET status = "resolved", resolved_at = NOW() WHERE id = ?')->execute([$rid]);
    set_flash('success', 'Report resolved.');
    header('Location: ' . $base . '/php/admin/');
    exit;
}

if ($action === 'ban') {
    require_admin();
    $target = (int) ($_POST['user_id'] ?? 0);
    $rid = (int) ($_POST['report_id'] ?? 0);
    $ban_until = date('Y-m-d H:i:s', strtotime('+3 days'));

    $pdo->prepare('UPDATE users SET banned_until = ? WHERE id = ? AND role = "Student"')->execute([$ban_until, $target]);

    if ($rid) {
        $pdo->prepare('UPDATE reports SET status = "resolved", resolved_at = NOW() WHERE id = ?')->execute([$rid]);
    }

    set_flash('success', 'User banned for 3 days.');
    header('Location: ' . $base . '/php/admin/');
    exit;
}

if ($action === 'unban') {
    require_admin();
    $target = (int) ($_POST['user_id'] ?? 0);
    $pdo->prepare('UPDATE users SET banned_until = NULL WHERE id = ?')->execute([$target]);
    set_flash('success', 'User unbanned.');
    header('Location: ' . $base . '/php/admin/');
    exit;
}

header('Location: ' . $base . '/php/dashboard/');