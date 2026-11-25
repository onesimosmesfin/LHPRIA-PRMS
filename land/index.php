<?php
// index.php - Entry point for LHPRIA / PRMS
require_once __DIR__ . '/config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: " . base_url('login.php'));
    exit;
}

switch ($_SESSION['role'] ?? '') {
    case 'admin':
        header("Location: " . base_url('admin/users.php'));
        break;
    case 'planner':
        header("Location: " . base_url('planner/plans.php'));
        break;
    case 'subcity_director':
        header("Location: " . base_url('director/review_plans.php'));
        break;
    case 'reporter':
        header("Location: " . base_url('reporter/create_report.php'));
        break;
    case 'hq_director':
        header("Location: " . base_url('hq/dashboard.php'));
        break;
    default:
        header("Location: " . base_url('login.php'));
        break;
}
exit;
