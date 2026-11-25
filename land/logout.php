<?php
// logout.php
require_once 'config/db.php';
$_SESSION = [];
session_destroy();
header("Location: " . base_url('login.php'));
exit;
