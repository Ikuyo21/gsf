<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/db.php';
require_student();

header('Content-Type: application/json');

$uid    = current_user_id();
$method = $_SERVER['REQUEST_METHOD'];

/* ─── GET: list or get ─── */
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'list') {
        $stmt = $pdo->prepare("
            SELECT ss.*, g.name AS group_name,
                   (SELECT COUNT(*) FROM study_session_attendees WHERE session_id = ss.id) AS attendee_count,
                   CONCAT(ss.session_date,' ',ss.session_time) AS session_datetime
            FROM   study_sessions ss
            JOIN   study_session_attendees ssa ON ssa.session_id = ss.id
            JOIN   groups_ g ON g.id = ss.group_id
            WHERE  ssa.user_id = ?
              AND  CONCAT(ss.session_date,' ',ss.session_time) >= NOW()
            ORDER  BY ss.session_date ASC, ss.session_time ASC
        ");
        $stmt->execute([$uid]);
        json_response(['sessions' => $stmt->fetchAll()]);

    } elseif ($action === 'get') {
        $sid  = (int)($_GET['session_id'] ?? 0);
        $stmt = $pdo->prepare("
            SELECT ss.*, g.name AS group_name,
                   (SELECT COUNT(*) FROM study_session_attendees WHERE session_id = ss.id) AS attendee_count
            FROM   study_sessions ss
            JOIN   groups_ g ON g.id = ss.group_id
            WHERE  ss.id = ?
        ");
        $stmt->execute([$sid]);
        $session = $stmt->fetch();
        if (!$session) { json_response(['error' => 'Session not found.'], 404); }

        $my = $pdo->prepare('SELECT COUNT(*) FROM study_session_attendees WHERE session_id = ? AND user_id = ?');
        $my->execute([$sid, $uid]);
        $session['is_joined'] = (bool)$my->fetchColumn();

        json_response(['session' => $session]);
    }

    json_response(['error' => 'Unknown action.'], 400);
}

/* ─── POST: join / leave / create ─── */
if ($method === 'POST') {
    $raw    = file_get_contents('php://input');
    $data   = json_decode($raw, true) ?? [];
    $action = $data['action'] ?? '';
    $sid    = (int)($data['session_id'] ?? 0);

    /* JOIN */
    if ($action === 'join') {
        if (!$sid) { json_response(['error' => 'Missing session_id.'], 400); }

        $s = $pdo->prepare('SELECT group_id FROM study_sessions WHERE id = ?');
        $s->execute([$sid]);
        $sess = $s->fetch();
        if (!$sess) { json_response(['error' => 'Session not found.'], 404); }

        $mem = $pdo->prepare('SELECT COUNT(*) FROM group_members WHERE group_id = ? AND user_id = ?');
        $mem->execute([$sess['group_id'], $uid]);
        if (!(bool)$mem->fetchColumn()) { json_response(['error' => 'Not a group member.'], 403); }

        $pdo->prepare('INSERT IGNORE INTO study_session_attendees (session_id, user_id) VALUES (?,?)')->execute([$sid, $uid]);

        $cnt = $pdo->prepare('SELECT COUNT(*) FROM study_session_attendees WHERE session_id = ?');
        $cnt->execute([$sid]);
        json_response(['ok' => true, 'is_joined' => true, 'attendee_count' => (int)$cnt->fetchColumn()]);
    }

    /* LEAVE */
    if ($action === 'leave') {
        if (!$sid) { json_response(['error' => 'Missing session_id.'], 400); }

        $pdo->prepare('DELETE FROM study_session_attendees WHERE session_id = ? AND user_id = ?')->execute([$sid, $uid]);

        $cnt = $pdo->prepare('SELECT COUNT(*) FROM study_session_attendees WHERE session_id = ?');
        $cnt->execute([$sid]);
        json_response(['ok' => true, 'is_joined' => false, 'attendee_count' => (int)$cnt->fetchColumn()]);
    }

    /* CREATE (leader only) */
    if ($action === 'create') {
        $gid      = (int)($data['group_id'] ?? 0);
        $date     = trim($data['date']     ?? '');
        $time     = trim($data['time']     ?? '');
        $location = trim($data['location'] ?? '');
        $link     = trim($data['link']     ?? '');
        $title    = trim($data['title']    ?? '') ?: 'Study Session';

        if (!$gid || !$date || !$time || !$location) {
            json_response(['error' => 'Date, time and location are required.'], 400);
        }

        /* validate date format */
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
            json_response(['error' => 'Invalid date or time format.'], 400);
        }

        $g = $pdo->prepare('SELECT leader_id FROM groups_ WHERE id = ?');
        $g->execute([$gid]);
        $group = $g->fetch();
        if (!$group || (int)$group['leader_id'] !== $uid) {
            json_response(['error' => 'Only the group leader can schedule sessions.'], 403);
        }

        /* Insert session */
        $pdo->prepare('INSERT INTO study_sessions (group_id, creator_id, title, session_date, session_time, location, link) VALUES (?,?,?,?,?,?,?)')
            ->execute([$gid, $uid, $title, $date, $time, $location, $link ?: null]);
        $new_sid = (int)$pdo->lastInsertId();

        /* Leader auto-joins */
        $pdo->prepare('INSERT IGNORE INTO study_session_attendees (session_id, user_id) VALUES (?,?)')->execute([$new_sid, $uid]);

        /* Post chat card */
        $pdo->prepare("INSERT INTO messages (group_id, user_id, content, message_type, session_id) VALUES (?,?,'','study_session',?)")
            ->execute([$gid, $uid, $new_sid]);
        $mid = (int)$pdo->lastInsertId();

        $pdo->prepare('UPDATE study_sessions SET message_id = ? WHERE id = ?')->execute([$mid, $new_sid]);

        update_last_active($pdo, $uid);

        json_response(['ok' => true, 'session_id' => $new_sid, 'message_id' => $mid]);
    }

    json_response(['error' => 'Unknown action.'], 400);
}

json_response(['error' => 'Method not allowed.'], 405);
