<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/db.php';
require_student();

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$csrf = $_POST['csrf'] ?? '';
$uid = current_user_id();
$base = base_url();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf($csrf)) {
    set_flash('error', 'Invalid request.');
    header('Location: ' . $base . '/php/progress/');
    exit;
}

if ($action === 'create_tracker') {
    $title = trim($_POST['title'] ?? '');
    $goals = array_filter(array_map('trim', $_POST['goals'] ?? []), function($g) { return $g !== ''; });

    if (!$title || empty($goals)) {
        set_flash('error', 'Tracker title and at least one goal are required.');
        header('Location: ' . $base . '/php/progress/');
        exit;
    }

    $stmt = $pdo->prepare('INSERT INTO study_trackers (user_id, title, created_at) VALUES (?, ?, NOW())');
    $stmt->execute([$uid, $title]);
    $tracker_id = (int) $pdo->lastInsertId();

    foreach ($goals as $goal) {
        $pdo->prepare('INSERT INTO tracker_goals (tracker_id, title, completed, created_at) VALUES (?, ?, 0, NOW())')->execute([$tracker_id, $goal]);
    }

    set_flash('success', 'Tracker created.');
    header('Location: ' . $base . '/php/progress/');
    exit;
}

if ($action === 'toggle_goal') {
    $goal_id = (int) ($_POST['goal_id'] ?? 0);

    $stmt = $pdo->prepare('SELECT tg.*, st.user_id FROM tracker_goals tg JOIN study_trackers st ON st.id = tg.tracker_id WHERE tg.id = ?');
    $stmt->execute([$goal_id]);
    $goal = $stmt->fetch();

    if ($goal && (int) $goal['user_id'] === $uid) {
        $new_val = $goal['completed'] ? 0 : 1;
        $pdo->prepare('UPDATE tracker_goals SET completed = ? WHERE id = ?')->execute([$new_val, $goal_id]);
    }

    header('Location: ' . $base . '/php/progress/');
    exit;
}

if ($action === 'delete_tracker') {
    $tracker_id = (int) ($_POST['tracker_id'] ?? 0);

    $stmt = $pdo->prepare('SELECT user_id FROM study_trackers WHERE id = ?');
    $stmt->execute([$tracker_id]);
    $tracker = $stmt->fetch();

    if ($tracker && (int) $tracker['user_id'] === $uid) {
        $pdo->prepare('DELETE FROM tracker_goals WHERE tracker_id = ?')->execute([$tracker_id]);
        $pdo->prepare('DELETE FROM study_trackers WHERE id = ?')->execute([$tracker_id]);
        set_flash('success', 'Tracker deleted.');
    }

    header('Location: ' . $base . '/php/progress/');
    exit;
}

if ($action === 'delete_goal') {
    $goal_id = (int) ($_POST['goal_id'] ?? 0);

    $stmt = $pdo->prepare('SELECT tg.*, st.user_id FROM tracker_goals tg JOIN study_trackers st ON st.id = tg.tracker_id WHERE tg.id = ?');
    $stmt->execute([$goal_id]);
    $goal = $stmt->fetch();

    if ($goal && (int) $goal['user_id'] === $uid) {
        $pdo->prepare('DELETE FROM tracker_goals WHERE id = ?')->execute([$goal_id]);
    }

    header('Location: ' . $base . '/php/progress/');
    exit;
}

header('Location: ' . $base . '/php/progress/');
