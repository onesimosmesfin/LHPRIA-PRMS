<?php
// change_password.php
require_once 'includes/auth_check.php';

// Accessible by any logged-in user
check_auth(['admin', 'planner', 'subcity_director', 'reporter', 'hq_director']);

$msg = '';
$err = '';
$user_id = $_SESSION['user_id'] ?? 0;

// Fetch current username safely from DB
$stmt_u = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$stmt_u->execute([$user_id]);
$u_info = $stmt_u->fetch(PDO::FETCH_ASSOC);
$display_username = $u_info['username'] ?? $_SESSION['username'] ?? 'ተጠቃሚ';
$_SESSION['username'] = $display_username;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $err = "እባክዎ ሁሉንም መስኮች ይሙሉ (Please fill in all fields).";
    } elseif (strlen($new_password) < 6) {
        $err = "አዲሱ የይለፍ ቃል ቢያንስ 6 ፊደላት/ቁጥሮች መሆን አለበት (Password must be at least 6 characters).";
    } elseif ($new_password !== $confirm_password) {
        $err = "የአዲሱ የይለፍ ቃል ማረጋገጫ አይመሳሰልም (New password confirmation does not match).";
    } else {
        // Fetch current password hash
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($current_password, $user['password_hash'])) {
            $err = "ያስገቡት የነበረው የይለፍ ቃል ትክክል አይደለም (Current password is incorrect).";
        } elseif ($current_password === $new_password) {
            $err = "አዲሱ የይለፍ ቃል ከቀድሞው ጋር ተመሳሳይ መሆን አይችልም (New password cannot be the same as current password).";
        } else {
            // Update to new hashed password
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt_up = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
            $stmt_up->execute([$new_hash, $user_id]);

            $msg = "የይለፍ ቃልዎ በተሳካ ሁኔታ ተቀይሯል! (Password changed successfully!)";
        }
    }
}

include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow border-0 rounded-3">
                <div class="card-header bg-primary text-white py-3 text-center">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-shield-lock-fill me-2"></i>የይለፍ ቃል መቀየሪያ (Change Password)
                    </h5>
                    <small class="text-white-50">ተጠቃሚ: <strong><?= htmlspecialchars($display_username) ?></strong></small>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($msg)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($err)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($err) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="change_password.php">
                        <!-- Current Password -->
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark small">የነበረው የይለፍ ቃል (Current Password)</label>
                            <div class="input-group">
                                <input type="password" name="current_password" id="curPass" class="form-control" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePass('curPass', this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- New Password -->
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark small">አዲሱ የይለፍ ቃል (New Password)</label>
                            <div class="input-group">
                                <input type="password" name="new_password" id="newPass" class="form-control" minlength="6" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePass('newPass', this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="form-text small">ቢያንስ 6 ፊደላት ወይም ቁጥሮች መሆን አለበት።</div>
                        </div>

                        <!-- Confirm New Password -->
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark small">አዲሱን የይለፍ ቃል ያረጋግጡ (Confirm New Password)</label>
                            <div class="input-group">
                                <input type="password" name="confirm_password" id="confPass" class="form-control" minlength="6" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePass('confPass', this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" name="update_password" class="btn btn-success py-2 fw-bold shadow-sm">
                                <i class="bi bi-check-lg me-1"></i>የይለፍ ቃሉን ቀይር (Save Changes)
                            </button>
                            <a href="javascript:history.back()" class="btn btn-light border py-2">
                                <i class="bi bi-arrow-left me-1"></i>ተመለስ (Go Back)
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function togglePass(inputId, btn) {
        const input = document.getElementById(inputId);
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'bi bi-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'bi bi-eye';
        }
    }
</script>

<?php include 'includes/footer.php'; ?>