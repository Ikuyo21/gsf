<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/db.php';
require_student();

header('Content-Type: application/json');

$uid          = current_user_id();
$is_multipart = !empty($_FILES['attachment']);

if ($is_multipart) {
    $gid     = (int)($_POST['group_id'] ?? 0);
    $content = trim($_POST['content']  ?? '');
} else {
    $data    = json_decode(file_get_contents('php://input'), true);
    $gid     = (int)($data['group_id'] ?? 0);
    $content = trim($data['content']  ?? '');
}

if (!$gid) { json_response(['error' => 'Missing data.'], 400); }

$stmt = $pdo->prepare('SELECT COUNT(*) FROM group_members WHERE group_id = ? AND user_id = ?');
$stmt->execute([$gid, $uid]);
if ((int)$stmt->fetchColumn() === 0) { json_response(['error' => 'Not a member.'], 403); }

$muted_until = check_muted($pdo, $uid);
if ($muted_until) {
    $remaining = max(0, strtotime($muted_until) - time());
    json_response(['error' => 'You are muted for '.$remaining.' seconds.', 'muted' => true, 'muted_seconds' => $remaining], 403);
}

$attachment      = null;
$attachment_name = null;

if ($is_multipart && !empty($_FILES['attachment']['name'])) {
    $file = $_FILES['attachment'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $mime = $file['type'] ?? '';

    $is_video = str_starts_with($mime, 'video/') || $ext === 'mp4';
    $is_audio = str_starts_with($mime, 'audio/') || $ext === 'mp3';

    if ($is_video && $ext !== 'mp4') {
        json_response(['error' => 'Only .mp4 video files are allowed.', 'format_error' => true], 422);
    }
    if ($is_audio && $ext !== 'mp3') {
        json_response(['error' => 'Only .mp3 audio files are allowed.', 'format_error' => true], 422);
    }

    $max_size = 20971520;

    $allowed = ['jpg','jpeg','png','gif','webp','pdf','doc','docx','txt','zip','rar','pptx','xlsx','csv','jfif','mp4','mp3'];
    if (!in_array($ext, $allowed)) {
        json_response(['error' => 'File type not supported.', 'format_error' => true], 422);
    }
    if ($file['size'] > $max_size) {
        $type_label = $is_video ? 'Video file' : ($is_audio ? 'Audio file' : 'File');
        json_response(['error' => $type_label . ' exceeds the 20 MB limit.', 'format_error' => true], 422);
    }
    $attachment      = $uid.'_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
    $attachment_name = $file['name'];
    move_uploaded_file($file['tmp_name'], __DIR__.'/../../uploads/chat_files/'.$attachment);
}

if (!$content && !$attachment) { json_response(['error' => 'Empty message.'], 400); }

if ($content) {
    if (contains_bad_words($pdo, $content)) {
        mute_user($pdo, $uid, 60);
        json_response(['error' => 'Inappropriate language detected. You are muted for 1 minute.', 'muted' => true, 'muted_seconds' => 60], 403);
    }
    $content = mb_substr($content, 0, 2000);
}

$stmt = $pdo->prepare('INSERT INTO messages (group_id, user_id, content, attachment, attachment_name) VALUES (?,?,?,?,?)');
$stmt->execute([$gid, $uid, $content ?: null, $attachment, $attachment_name]);

update_last_active($pdo, $uid);
json_response(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
