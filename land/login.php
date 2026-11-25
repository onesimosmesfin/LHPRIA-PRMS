<?php
// login.php

require_once 'config/db.php';

$error = '';

// Ensure session is active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username !== '' && $password !== '') {

        try {

            // FIX: Use unique named placeholders for PDO
            $stmt = $pdo->prepare("
                SELECT
                    u.*,
                    l.name AS location_name,
                    l.type AS location_type
                FROM users u
                JOIN locations l ON u.location_id = l.id
                WHERE u.username = :username
                   OR u.email = :email
                LIMIT 1
            ");

            $stmt->execute([
                'username' => $username,
                'email'    => $username
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {

                // Check account status
                if ($user['status'] !== 'active') {

                    $error = 'Your account has been deactivated. Please contact the administrator.';
                } else {

                    // Prevent session fixation
                    session_regenerate_id(true);

                    // Store user session data
                    $_SESSION['user_id']       = $user['id'];
                    $_SESSION['fullname']       = $user['fullname'];
                    $_SESSION['role']           = $user['role'];
                    $_SESSION['location_id']    = $user['location_id'];
                    $_SESSION['location_name']  = $user['location_name'];
                    $_SESSION['location_type']  = $user['location_type'];

                    // Role-based redirection
                    switch ($user['role']) {

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
                            header("Location: " . base_url('reporter/submit_report.php'));
                            break;

                        case 'hq_director':
                            header("Location: " . base_url('hq/dashboard.php'));
                            break;

                        default:
                            header("Location: " . base_url('index.php'));
                            break;
                    }

                    exit;
                }
            } else {

                $error = 'Invalid username or password.';
            }
        } catch (PDOException $e) {

            // Log technical details; do not expose them to users
            error_log("Login database error: " . $e->getMessage());

            $error = 'Unable to process login at this time. Please try again.';
        }
    } else {

        $error = 'Please fill in both username and password.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login - PRMS Portal</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;

            background: linear-gradient(135deg,
                    #0f172a,
                    #1e293b 50%,
                    #0284c7);

            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        .login-card {
            width: 100%;
            max-width: 450px;
            background: rgba(255, 255, 255, 0.97);
            border-radius: 22px;
            padding: 40px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.3);
        }

        .brand-badge {
            width: 70px;
            height: 70px;
            margin: 0 auto 20px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 18px;
            background: linear-gradient(135deg, #0284c7, #2563eb);

            color: white;
            font-size: 30px;

            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.3);
        }

        .form-control {
            padding: 12px 15px;
            border-radius: 10px;
            border: 1px solid #d1d5db;
        }

        .input-group-text {
            border-radius: 10px 0 0 10px;
            background: #fff;
            border-color: #d1d5db;
        }

        .input-group .form-control {
            border-radius: 0 10px 10px 0;
        }

        .form-control:focus {
            border-color: #0284c7;
            box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.12);
        }

        .btn-primary {
            padding: 13px;
            border: none;
            border-radius: 10px;
            font-weight: 600;

            background: linear-gradient(135deg, #0284c7, #2563eb);
            transition: 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            background: linear-gradient(135deg, #0369a1, #1d4ed8);
        }

        .password-toggle {
            border: 1px solid #d1d5db;
            border-left: 0;
            background: white;
            border-radius: 0 10px 10px 0;
            padding: 0 14px;
            color: #64748b;
        }

        .password-toggle:hover {
            background: #f8fafc;
        }

        .footer-text {
            color: #64748b;
            font-size: 12px;
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 28px 22px;
            }
        }
    </style>
</head>

<body>

    <div class="login-card">

        <!-- Logo -->
        <div class="brand-badge">
            <i class="bi bi-file-earmark-bar-graph"></i>
        </div>

        <!-- System title -->
        <div class="text-center mb-4">

            <h3 class="fw-bold text-dark mb-1">LHPRIA-PRMS Portal</h3>

            <p class="text-muted small mb-2">
                Plan & Report Management System
            </p>

            <span class="badge bg-light text-primary border rounded-pill px-3 py-2">
                <i class="bi bi-building me-1"></i>
                11 Subcities & Headquarter
            </span>
        </div>

        <!-- Error message -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 px-3 rounded-3 small">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <!-- Login form -->
        <form method="POST" action="" autocomplete="on">

            <!-- Username -->
            <div class="mb-3">

                <label for="username" class="form-label small fw-semibold text-secondary">
                    Username or Email
                </label>

                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-person text-muted"></i>
                    </span>

                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="form-control"
                        placeholder="Enter username or email"
                        value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        autocomplete="username"
                        required
                        autofocus>
                </div>

            </div>

            <!-- Password -->
            <div class="mb-4">

                <label for="password" class="form-label small fw-semibold text-secondary">
                    Password
                </label>

                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-lock text-muted"></i>
                    </span>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required>

                    <button
                        type="button"
                        class="password-toggle"
                        id="togglePassword"
                        aria-label="Show password">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>

            </div>

            <!-- Sign in button -->
            <button type="submit" class="btn btn-primary w-100 shadow-sm">
                Sign In
                <i class="bi bi-arrow-right ms-1"></i>
            </button>

        </form>

        <!-- Footer -->
        <div class="text-center mt-4">
            <p class="footer-text mb-1">
                <i class="bi bi-shield-lock me-1"></i>
                Secure Access | PRMS
            </p>

            <p class="footer-text mb-0">
                Authorized personnel only
            </p>
        </div>

    </div>

    <!-- Password visibility toggle -->
    <script>
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');

        togglePassword.addEventListener('click', function() {

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('bi-eye');
                eyeIcon.classList.add('bi-eye-slash');
                togglePassword.setAttribute('aria-label', 'Hide password');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('bi-eye-slash');
                eyeIcon.classList.add('bi-eye');
                togglePassword.setAttribute('aria-label', 'Show password');
            }

        });
    </script>

</body>

</html>