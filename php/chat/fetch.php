<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/db.php';
require_student();

header('Content-Type: application/json');

$uid = current_user_id();
$gid = (int) ($_GET['group_id'] ?? 0);
$after = (int) ($_GET['after'] ?? 0);

if (!$gid) {
    json_response(['error' => 'Missing group.'], 400);
}

$stmt = $pdo->prepare('SELECT COUNT(*) FROM group_members WHERE group_id = ? AND user_id = ?');
$stmt->execute([$gid, $uid]);
if ((int) $stmt->fetchColumn() === 0) {
    json_response(['error' => 'Not a member.'], 403);
}

if ($after > 0) {
    $stmt = $pdo->prepare('SELECT m.id, m.content, m.created_at, m.user_id, m.attachment, m.attachment_name, u.full_name, u.avatar FROM messages m JOIN users u ON u.id = m.user_id WHERE m.group_id = ? AND m.id > ? ORDER BY m.id ASC LIMIT 100');
    $stmt->execute([$gid, $after]);
} else {
    $stmt = $pdo->prepare('SELECT m.id, m.content, m.created_at, m.user_id, m.attachment, m.attachment_name, u.full_name, u.avatar FROM messages m JOIN users u ON u.id = m.user_id WHERE m.group_id = ? ORDER BY m.id DESC LIMIT 50');
    $stmt->execute([$gid]);
}

$messages = $stmt->fetchAll();

if ($after === 0) {
    $messages = array_reverse($messages);
}

$base = base_url();
foreach ($messages as &$m) {
    $m['avatar_url'] = avatar_url($m['avatar']);
    $m['is_me'] = ((int) $m['user_id'] === $uid);
    if ($m['attachment']) {
        $m['attachment_url'] = $base . '/uploads/chat_files/' . $m['attachment'];
    }
    unset($m['avatar']);
}

json_response(['messages' => $messages]);
