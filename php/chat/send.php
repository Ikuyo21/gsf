<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/db.php';
require_student();

header('Content-Type: application/json');

$uid = current_user_id();
$is_multipart = !empty($_FILES['attachment']);

if ($is_multipart) {
    $gid = (int) ($_POST['group_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');
} else {
    $data = json_decode(file_get_contents('php://input'), true);
    $gid = (int) ($data['group_id'] ?? 0);
    $content = trim($data['content'] ?? '');
}

if (!$gid) {
    json_response(['error' => 'Missing data.'], 400);
}

$stmt = $pdo->prepare('SELECT COUNT(*) FROM group_members WHERE group_id = ? AND user_id = ?');
$stmt->execute([$gid, $uid]);
if ((int) $stmt->fetchColumn() === 0) {
    json_response(['error' => 'Not a member.'], 403);
}

$muted_until = check_muted($pdo, $uid);
if ($muted_until) {
    $remaining = max(0, strtotime($muted_until) - time());
    json_response(['error' => 'You are muted for ' . $remaining . ' seconds due to inappropriate language.', 'muted' => true, 'muted_seconds' => $remaining], 403);
}

$attachment = null;
$attachment_name = null;

if ($is_multipart && !empty($_FILES['attachment']['name'])) {
    $file = $_FILES['attachment'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp','pdf','doc','docx','txt','zip','rar','pptx','xlsx','csv','jfif'];
    if (in_array($ext, $allowed) && $file['size'] <= 10485760) {
        $attachment = $uid . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $attachment_name = $file['name'];
        move_uploaded_file($file['tmp_name'], __DIR__ . '/../../uploads/chat_files/' . $attachment);
    }
}

if (!$content && !$attachment) {
    json_response(['error' => 'Empty message.'], 400);
}

if ($content) {
    if (contains_bad_words($pdo, $content)) {
        mute_user($pdo, $uid, 60);
        json_response(['error' => 'Your message contains inappropriate language. You have been muted for 1 minute.', 'muted' => true, 'muted_seconds' => 60], 403);
    }
    $content = mb_substr($content, 0, 2000);
}

$stmt = $pdo->prepare('INSERT INTO messages (group_id, user_id, content, attachment, attachment_name) VALUES (?, ?, ?, ?, ?)');
$stmt->execute([$gid, $uid, $content ?: null, $attachment, $attachment_name]);

update_last_active($pdo, $uid);

json_response(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);