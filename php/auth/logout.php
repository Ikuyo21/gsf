<?php
require_once __DIR__ . '/../core/auth.php';
session_destroy();
header('Location: ' . base_url() . '/php/auth/login.php');
exit;
