<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/db.php';

if (is_logged_in()) {
    update_last_active($pdo, current_user_id());
}
json_response(['ok' => true]);
