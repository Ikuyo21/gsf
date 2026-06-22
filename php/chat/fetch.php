<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/db.php';
require_student();

header('Content-Type: application/json');

$uid   = current_user_id();
$gid   = (int)($_GET['group_id'] ?? 0);
$after = (int)($_GET['after']    ?? 0);

if (!$gid) { json_response(['error' => 'Missing group.'], 400); }

$stmt = $pdo->prepare('SELECT COUNT(*) FROM group_members WHERE group_id = ? AND user_id = ?');
$stmt->execute([$gid, $uid]);
if ((int)$stmt->fetchColumn() === 0) { json_response(['error' => 'Not a member.'], 403); }

$select = 'SELECT m.id, m.content, m.created_at, m.user_id, m.attachment, m.attachment_name,
                  COALESCE(m.message_type,"text") AS message_type, m.session_id,
                  u.full_name, u.avatar';

if ($after > 0) {
    $stmt = $pdo->prepare("$select FROM messages m JOIN users u ON u.id = m.user_id WHERE m.group_id = ? AND m.id > ? ORDER BY m.id ASC LIMIT 100");
    $stmt->execute([$gid, $after]);
} else {
    $stmt = $pdo->prepare("$select FROM messages m JOIN users u ON u.id = m.user_id WHERE m.group_id = ? ORDER BY m.id DESC LIMIT 50");
    $stmt->execute([$gid]);
}

$messages = $stmt->fetchAll();
if ($after === 0) { $messages = array_reverse($messages); }

$base = base_url();

$session_cache = [];

foreach ($messages as &$m) {
    $m['avatar_url'] = avatar_url($m['avatar']);
    $m['is_me']      = ((int)$m['user_id'] === $uid);

    if ($m['attachment']) {
        $m['attachment_url'] = $base.'/uploads/chat_files/'.$m['attachment'];
    }

    if ($m['message_type'] === 'study_session' && $m['session_id']) {
        $sid = (int)$m['session_id'];

        if (!isset($session_cache[$sid])) {
            $ss = $pdo->prepare("
                SELECT ss.*, u.full_name AS creator_name,
                       (SELECT COUNT(*) FROM study_session_attendees WHERE session_id = ss.id) AS attendee_count
                FROM   study_sessions ss
                JOIN   users u ON u.id = ss.creator_id
                WHERE  ss.id = ?
            ");
            $ss->execute([$sid]);
            $sess = $ss->fetch();

            if ($sess) {
                $my = $pdo->prepare('SELECT COUNT(*) FROM study_session_attendees WHERE session_id = ? AND user_id = ?');
                $my->execute([$sid, $uid]);
                $sess['is_joined']     = (bool)$my->fetchColumn();
                $sess['attendee_count'] = (int)$sess['attendee_count'];
            }
            $session_cache[$sid] = $sess ?: null;
        }

        $m['session'] = $session_cache[$sid];
    }

    unset($m['avatar']);
}
unset($m);

json_response(['messages' => $messages]);
