<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/db.php';
require_student();

$action = $_POST['action'] ?? '';
$csrf = $_POST['csrf'] ?? '';
$uid = current_user_id();
$base = base_url();
$target = (int) ($_POST['user_id'] ?? 0);
$redirect = $_POST['redirect'] ?? '';

if (!verify_csrf($csrf) || !$target) {
    set_flash('error', 'Invalid request.');
    header('Location: ' . $base . '/php/people/');
    exit;
}

$back = $redirect ?: $base . '/php/people/';

if ($action === 'send') {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM friends WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)');
    $stmt->execute([$uid, $target, $target, $uid]);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->prepare('INSERT INTO friends (sender_id, receiver_id, status, created_at) VALUES (?, ?, "pending", NOW())')->execute([$uid, $target]);
        set_flash('success', 'Friend request sent.');
    }
    header('Location: ' . $back);
    exit;
}

if ($action === 'cancel') {
    $pdo->prepare('DELETE FROM friends WHERE sender_id = ? AND receiver_id = ? AND status = "pending"')->execute([$uid, $target]);
    set_flash('success', 'Friend request cancelled.');
    header('Location: ' . $back);
    exit;
}

if ($action === 'accept') {
    $pdo->prepare('UPDATE friends SET status = "accepted" WHERE sender_id = ? AND receiver_id = ? AND status = "pending"')->execute([$target, $uid]);
    set_flash('success', 'Friend request accepted.');
    header('Location: ' . $back);
    exit;
}

if ($action === 'reject') {
    $pdo->prepare('DELETE FROM friends WHERE sender_id = ? AND receiver_id = ? AND status = "pending"')->execute([$target, $uid]);
    set_flash('success', 'Request declined.');
    header('Location: ' . $back);
    exit;
}

if ($action === 'remove') {
    $pdo->prepare('DELETE FROM friends WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)')->execute([$uid, $target, $target, $uid]);
    set_flash('success', 'Friend removed.');
    header('Location: ' . $back);
    exit;
}

header('Location: ' . $base . '/php/people/');
