<?php
// admin/users.php
require_once '../includes/auth_check.php';
check_auth(['admin']);

$msg = '';
$err = '';
$revealed_password = '';
$revealed_username = '';

// 1. Handle Status Toggle (Activate / Deactivate)
if (isset($_GET['toggle_status'], $_GET['user_id'])) {
    $uid = (int)$_GET['user_id'];
    $new_status = $_GET['toggle_status'] === 'active' ? 'active' : 'inactive';
    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND id != ?");
    $stmt->execute([$new_status, $uid, $_SESSION['user_id']]);
    header("Location: users.php?msg=User status updated successfully");
    exit;
}

// 2. Handle Password Reset by Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password_action'])) {
    $target_uid = (int)$_POST['target_user_id'];
    $new_plain_pass = trim($_POST['new_password'] ?? '');
    $target_uname = trim($_POST['target_username'] ?? '');

    if (!empty($new_plain_pass) && strlen($new_plain_pass) >= 4) {
        $hashed = password_hash($new_plain_pass, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed, $target_uid]);

        $revealed_username = $target_uname;
        $revealed_password = $new_plain_pass;
        $msg = "Password reset successfully!";
    } else {
        $err = "Password must be at least 4 characters long.";
    }
}

// 3. Handle Add New User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $fullname = trim($_POST['fullname']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $location_id = (int)$_POST['location_id'];

    if (!empty($fullname) && !empty($username) && !empty($email) && !empty($password)) {
        try {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("
                INSERT INTO users (fullname, username, email, password, role, location_id, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'active')
            ");
            $stmt->execute([$fullname, $username, $email, $hashed, $role, $location_id]);

            $revealed_username = $username;
            $revealed_password = $password;
            $msg = "User account created successfully!";
        } catch (PDOException $e) {
            $err = "Error: Username or Email already exists in the system.";
        }
    } else {
        $err = "Please fill in all required fields.";
    }
}

// Fetch Locations & Users
$locations = $pdo->query("SELECT * FROM locations ORDER BY type DESC, name ASC")->fetchAll();
$users = $pdo->query("
    SELECT u.*, l.name AS location_name, l.type AS location_type 
    FROM users u 
    JOIN locations l ON u.location_id = l.id 
    ORDER BY u.created_at DESC
")->fetchAll();

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">User Management & Access Control</h3>
        <p class="text-secondary small mb-0">Assign roles, manage passwords, and activate/deactivate accounts across 11 subcities and HQ.</p>
    </div>
    <button class="btn btn-primary btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="bi bi-person-plus-fill me-1"></i> Add New User
    </button>
</div>

<!-- Password Revealed Banner (Shown after Reset or Creation) -->
<?php if ($revealed_password): ?>
    <div class="alert alert-warning border-warning border-2 p-3 rounded-3 mb-4 shadow-sm">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h6 class="fw-bold text-dark mb-1"><i class="bi bi-key-fill text-warning me-2"></i>Password Generated for @<?= htmlspecialchars($revealed_username) ?></h6>
                <div class="text-secondary small">Make sure to copy and send this password to the user:</div>
                <div class="mt-2">
                    <span class="badge bg-dark fs-6 py-2 px-3 user-select-all" id="copiedPass"><?= htmlspecialchars($revealed_password) ?></span>
                </div>
            </div>
            <button class="btn btn-dark btn-sm" onclick="copyPassword('<?= htmlspecialchars($revealed_password) ?>')">
                <i class="bi bi-clipboard-check me-1"></i> Copy Password
            </button>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($_GET['msg']) || ($msg && !$revealed_password)): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($_GET['msg'] ?? $msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($err) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="card card-custom">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-dark">Registered Users (<?= count($users) ?> Accounts)</h6>
        <span class="text-muted small">Manage credentials & active state</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Full Name / Username</th>
                        <th>Email</th>
                        <th>Assigned Jurisdiction</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?= htmlspecialchars($u['fullname']) ?></div>
                                <div class="text-muted small">@<?= htmlspecialchars($u['username']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td>
                                <span class="badge <?= $u['location_type'] === 'HQ' ? 'bg-dark' : 'bg-secondary' ?>">
                                    <?= htmlspecialchars($u['location_name']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-info text-dark text-capitalize">
                                    <?= str_replace('_', ' ', $u['role']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($u['status'] === 'active'): ?>
                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="bi bi-slash-circle me-1"></i> Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <!-- Reset Password Button -->
                                <button class="btn btn-sm btn-outline-warning me-1"
                                    onclick="openResetModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>', '<?= htmlspecialchars($u['fullname']) ?>')"
                                    title="Reset User Password">
                                    <i class="bi bi-key-fill me-1"></i> Reset Pass
                                </button>

                                <!-- Activate / Deactivate Toggle -->
                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                    <a href="users.php?toggle_status=<?= $u['status'] === 'active' ? 'inactive' : 'active' ?>&user_id=<?= $u['id'] ?>"
                                        class="btn btn-sm <?= $u['status'] === 'active' ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                                        onclick="return confirm('Change status for this user?')">
                                        <?= $u['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                    </a>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">You</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ==================== MODAL: RESET PASSWORD ==================== -->
<div class="modal fade" id="resetPassModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <form method="POST">
                <input type="hidden" name="reset_password_action" value="1">
                <input type="hidden" name="target_user_id" id="resetUid">
                <input type="hidden" name="target_username" id="resetUnameField">

                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-secondary small mb-3">
                        Set a new password for <strong class="text-dark" id="resetUserDisplay"></strong>.
                    </p>

                    <label class="form-label small fw-semibold">New Password</label>
                    <div class="input-group mb-2">
                        <input type="password" name="new_password" id="resetPassInput" class="form-control" placeholder="Enter new password" required>
                        <button type="button" class="btn btn-outline-secondary" onclick="togglePassVisibility('resetPassInput', 'resetEyeIcon')">
                            <i class="bi bi-eye" id="resetEyeIcon"></i>
                        </button>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="text-muted small">Minimum 4 characters</span>
                        <button type="button" class="btn btn-link btn-sm text-decoration-none p-0 fw-semibold" onclick="generateRandomPass('resetPassInput')">
                            <i class="bi bi-magic me-1"></i> Auto-Generate Strong Password
                        </button>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning px-4 fw-bold">
                        <i class="bi bi-check-lg me-1"></i> Update & Reveal Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== MODAL: ADD USER ==================== -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <form method="POST">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Create User Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Full Name</label>
                        <input type="text" name="fullname" class="form-control" placeholder="e.g. Abebe Bikila" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Username</label>
                            <input type="text" name="username" class="form-control" placeholder="abikila" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="user@subcity.gov.et" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label small fw-semibold mb-0">Initial Password</label>
                            <button type="button" class="btn btn-link btn-sm text-decoration-none p-0" onclick="generateRandomPass('newPassInput')">
                                <i class="bi bi-magic me-1"></i> Auto-Generate
                            </button>
                        </div>
                        <div class="input-group">
                            <input type="password" name="password" id="newPassInput" class="form-control" placeholder="••••••••" required>
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassVisibility('newPassInput', 'newEyeIcon')">
                                <i class="bi bi-eye" id="newEyeIcon"></i>
                            </button>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Role</label>
                            <select name="role" class="form-select" required>
                                <option value="planner">Planner (Subcity)</option>
                                <option value="subcity_director">Subcity Director</option>
                                <option value="reporter">Reporter (Subcity)</option>
                                <option value="hq_director">HQ Director / Officer</option>
                                <option value="admin">System Admin</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Assigned Subcity / HQ</label>
                            <select name="location_id" class="form-select" required>
                                <?php foreach ($locations as $loc): ?>
                                    <option value="<?= $loc['id'] ?>">
                                        <?= htmlspecialchars($loc['name']) ?> (<?= $loc['type'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_user" class="btn btn-primary px-4">Create Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Open Reset Modal
    function openResetModal(userId, username, fullname) {
        document.getElementById('resetUid').value = userId;
        document.getElementById('resetUnameField').value = username;
        document.getElementById('resetUserDisplay').innerText = fullname + ' (@' + username + ')';
        document.getElementById('resetPassInput').value = '';
        new bootstrap.Modal(document.getElementById('resetPassModal')).show();
    }

    // Toggle Show/Hide Password
    function togglePassVisibility(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'bi bi-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'bi bi-eye';
        }
    }

    // Auto-generate a secure memorable password
    function generateRandomPass(inputId) {
        const words = ['Prms', 'Land', 'Plan', 'Report', 'City', 'Apex'];
        const randomWord = words[Math.floor(Math.random() * words.length)];
        const randomNum = Math.floor(1000 + Math.random() * 9000);
        const chars = '!@#$%&*';
        const randomChar = chars[Math.floor(Math.random() * chars.length)];
        const pass = randomWord + '@' + randomNum + randomChar;

        const input = document.getElementById(inputId);
        input.value = pass;
        input.type = 'text'; // Make it visible so admin can see what was generated
    }

    // Copy to Clipboard
    function copyPassword(text) {
        navigator.clipboard.writeText(text).then(() => {
            alert('Password copied to clipboard: ' + text);
        });
    }
</script>

<?php require_once '../includes/footer.php'; ?>