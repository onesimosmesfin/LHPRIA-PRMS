<?php
// index.php - Entry point for LHPRIA / PRMS
if (file_exists(__DIR__ . '/config/db.php')) {
    require_once __DIR__ . '/config/db.php';
} elseif (file_exists(__DIR__ . '/land/config/db.php')) {
    require_once __DIR__ . '/land/config/db.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$login_url = function_exists('base_url') ? base_url('login.php') : '/land/login.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . $login_url);
    exit;
}

switch ($_SESSION['role'] ?? '') {
    case 'admin':
        header("Location: " . (function_exists('base_url') ? base_url('admin/users.php') : '/land/admin/users.php'));
        break;
    case 'planner':
        header("Location: " . (function_exists('base_url') ? base_url('planner/plans.php') : '/land/planner/plans.php'));
        break;
    case 'subcity_director':
        header("Location: " . (function_exists('base_url') ? base_url('director/review_plans.php') : '/land/director/review_plans.php'));
        break;
    case 'reporter':
        header("Location: " . (function_exists('base_url') ? base_url('reporter/create_report.php') : '/land/reporter/create_report.php'));
        break;
    case 'hq_director':
        header("Location: " . (function_exists('base_url') ? base_url('hq/dashboard.php') : '/land/hq/dashboard.php'));
        break;
    default:
        header("Location: " . $login_url);
        break;
}
exit;
