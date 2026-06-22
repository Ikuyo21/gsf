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
    header('Location: ' . $base . '/php/groups/');
    exit;
}

if ($action === 'create') {
    require_student();
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $subject_code = trim($_POST['subject_code'] ?? '');
    $visibility = in_array($_POST['visibility'] ?? '', ['public', 'private']) ? $_POST['visibility'] : 'public';
    $max = max(2, min(50, (int) ($_POST['max_members'] ?? 15)));

    if (!$name) {
        set_flash('error', 'Group name is required.');
        header('Location: ' . $base . '/php/groups/create.php');
        exit;
    }

    $name = filter_bad_words($pdo, $name);
    $description = filter_bad_words($pdo, $description);

    $stmt = $pdo->prepare('INSERT INTO groups_ (name, description, subject_code, leader_id, visibility, max_members, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    $stmt->execute([$name, $description ?: null, $subject_code ?: null, $uid, $visibility, $max]);
    $gid = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO group_members (group_id, user_id, joined_at) VALUES (?, ?, NOW())')->execute([$gid, $uid]);

    set_flash('success', 'Group created.');
    header('Location: ' . $base . '/php/groups/view.php?id=' . $gid);
    exit;
}

if ($action === 'join') {
    require_student();
    $gid = (int) ($_POST['group_id'] ?? 0);

    $stmt = $pdo->prepare('SELECT * FROM groups_ WHERE id = ?');
    $stmt->execute([$gid]);
    $group = $stmt->fetch();

    if (!$group || $group['visibility'] !== 'public') {
        set_flash('error', 'Cannot join this group.');
        header('Location: ' . $base . '/php/groups/');
        exit;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM group_members WHERE group_id = ?');
    $stmt->execute([$gid]);
    if ((int) $stmt->fetchColumn() >= (int) $group['max_members']) {
        set_flash('error', 'Group is full.');
        header('Location: ' . $base . '/php/groups/');
        exit;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM group_members WHERE group_id = ? AND user_id = ?');
    $stmt->execute([$gid, $uid]);
    if ((int) $stmt->fetchColumn() > 0) {
        header('Location: ' . $base . '/php/groups/view.php?id=' . $gid);
        exit;
    }

    $pdo->prepare('INSERT INTO group_members (group_id, user_id, joined_at) VALUES (?, ?, NOW())')->execute([$gid, $uid]);
    set_flash('success', 'Joined group.');
    header('Location: ' . $base . '/php/groups/view.php?id=' . $gid);
    exit;
}

if ($action === 'leave') {
    $gid = (int) ($_POST['group_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT leader_id FROM groups_ WHERE id = ?');
    $stmt->execute([$gid]);
    $group = $stmt->fetch();

    if ($group) {
        $is_leader = (int) $group['leader_id'] === $uid;

        if ($is_leader) {
            $stmt = $pdo->prepare('SELECT user_id FROM group_members WHERE group_id = ? AND user_id != ? ORDER BY RAND() LIMIT 1');
            $stmt->execute([$gid, $uid]);
            $new_leader = $stmt->fetch();

            if ($new_leader) {
                $pdo->prepare('UPDATE groups_ SET leader_id = ? WHERE id = ?')->execute([$new_leader['user_id'], $gid]);
                $pdo->prepare('DELETE FROM group_members WHERE group_id = ? AND user_id = ?')->execute([$gid, $uid]);
                set_flash('success', 'You left the group. A new leader has been assigned.');
            } else {
                $pdo->prepare('DELETE FROM messages WHERE group_id = ?')->execute([$gid]);
                $pdo->prepare('DELETE FROM group_members WHERE group_id = ?')->execute([$gid]);
                $pdo->prepare('DELETE FROM groups_ WHERE id = ?')->execute([$gid]);
                set_flash('success', 'You were the last member. Group deleted.');
            }
        } else {
            $pdo->prepare('DELETE FROM group_members WHERE group_id = ? AND user_id = ?')->execute([$gid, $uid]);
            set_flash('success', 'Left group.');
        }
    }
    header('Location: ' . $base . '/php/groups/');
    exit;
}

if ($action === 'delete') {
    $gid = (int) ($_POST['group_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT leader_id FROM groups_ WHERE id = ?');
    $stmt->execute([$gid]);
    $group = $stmt->fetch();

    if ($group && (int) $group['leader_id'] === $uid) {
        $pdo->prepare('DELETE FROM messages WHERE group_id = ?')->execute([$gid]);
        $pdo->prepare('DELETE FROM group_members WHERE group_id = ?')->execute([$gid]);
        $pdo->prepare('DELETE FROM groups_ WHERE id = ?')->execute([$gid]);
        set_flash('success', 'Group deleted.');
    }
    header('Location: ' . $base . '/php/groups/');
    exit;
}

if ($action === 'kick') {
    require_student();
    $gid = (int) ($_POST['group_id'] ?? 0);
    $target = (int) ($_POST['user_id'] ?? 0);

    $stmt = $pdo->prepare('SELECT leader_id FROM groups_ WHERE id = ?');
    $stmt->execute([$gid]);
    $group = $stmt->fetch();

    if (!$group || (int) $group['leader_id'] !== $uid) {
        set_flash('error', 'Only the group leader can kick members.');
        header('Location: ' . $base . '/php/groups/view.php?id=' . $gid);
        exit;
    }

    if ($target === $uid) {
        set_flash('error', 'You cannot kick yourself.');
        header('Location: ' . $base . '/php/groups/view.php?id=' . $gid);
        exit;
    }

    $pdo->prepare('DELETE FROM group_members WHERE group_id = ? AND user_id = ?')->execute([$gid, $target]);
    set_flash('success', 'Member removed from group.');
    header('Location: ' . $base . '/php/groups/view.php?id=' . $gid);
    exit;
}

if ($action === 'add_member') {
    require_student();
    $gid = (int) ($_POST['group_id'] ?? 0);
    $target = (int) ($_POST['user_id'] ?? 0);

    $stmt = $pdo->prepare('SELECT leader_id, max_members, visibility FROM groups_ WHERE id = ?');
    $stmt->execute([$gid]);
    $group = $stmt->fetch();

    $is_leader = $group && (int) $group['leader_id'] === $uid;
    $is_member_check = false;
    if (!$is_leader) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM group_members WHERE group_id = ? AND user_id = ?');
        $stmt->execute([$gid, $uid]);
        $is_member_check = (int) $stmt->fetchColumn() > 0;
    }

    if (!$group || (!$is_leader && !$is_member_check)) {
        set_flash('error', 'You cannot add members to this group.');
        header('Location: ' . $base . '/php/groups/view.php?id=' . $gid);
        exit;
    }

    if ($group['visibility'] === 'private' && !$is_leader) {
        set_flash('error', 'Only the group leader can add members to a private group.');
        header('Location: ' . $base . '/php/groups/view.php?id=' . $gid);
        exit;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM friends WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) AND status = "accepted"');
    $stmt->execute([$uid, $target, $target, $uid]);
    if ((int) $stmt->fetchColumn() === 0) {
        set_flash('error', 'You can only add friends to the group.');
        header('Location: ' . $base . '/php/groups/view.php?id=' . $gid);
        exit;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM group_members WHERE group_id = ?');
    $stmt->execute([$gid]);
    if ((int) $stmt->fetchColumn() >= (int) $group['max_members']) {
        set_flash('error', 'Group is full.');
        header('Location: ' . $base . '/php/groups/view.php?id=' . $gid);
        exit;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM group_members WHERE group_id = ? AND user_id = ?');
    $stmt->execute([$gid, $target]);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->prepare('INSERT INTO group_members (group_id, user_id, joined_at) VALUES (?, ?, NOW())')->execute([$gid, $target]);
        set_flash('success', 'Member added.');
    }
    header('Location: ' . $base . '/php/groups/view.php?id=' . $gid);
    exit;
}

header('Location: ' . $base . '/php/groups/');
