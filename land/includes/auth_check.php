<?php
// includes/auth_check.php
require_once __DIR__ . '/../config/db.php';

function check_auth($allowed_roles = [])
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . base_url('login.php'));
        exit;
    }

    if (!empty($allowed_roles) && !in_array($_SESSION['role'], $allowed_roles)) {
        header("Location: " . base_url('login.php?error=unauthorized'));
        exit;
    }
}
