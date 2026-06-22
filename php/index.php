<?php
require_once __DIR__ . '/core/auth.php';

if (is_logged_in()) {
    if (is_admin()) {
        header('Location: ' . base_url() . '/php/admin/');
    } else {
        header('Location: ' . base_url() . '/php/dashboard/');
    }
    exit;
}

header('Location: ' . base_url() . '/php/auth/login.php');
exit;
