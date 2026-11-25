<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';

// Safe User & Role Information
$current_user_id  = $_SESSION['user_id'] ?? 0;
$current_username = $_SESSION['username'] ?? 'User';
$current_role     = $_SESSION['role'] ?? 'guest';
$current_loc_id   = $_SESSION['location_id'] ?? 1;

// Fetch Subcity Name dynamically if not cached
$current_subcity = $_SESSION['location_name'] ?? '';
if (empty($current_subcity) && isset($pdo) && $current_loc_id > 0) {
    $stmt_loc_hdr = $pdo->prepare("SELECT name FROM locations WHERE id = ?");
    $stmt_loc_hdr->execute([$current_loc_id]);
    $loc_row = $stmt_loc_hdr->fetch(PDO::FETCH_ASSOC);
    if ($loc_row) {
        $current_subcity = $loc_row['name'];
        $_SESSION['location_name'] = $current_subcity;
    }
}
if (empty($current_subcity)) {
    $current_subcity = ($current_role === 'hq_director' || $current_role === 'admin') ? 'ዋና መ/ቤት (HQ)' : 'ክፍለ ከተማ';
}

// Role Display Badges
$role_meta = [
    'admin' => [
        'title' => 'ሲስተም አድሚን (Admin)',
        'badge' => 'bg-danger text-white',
        'icon'  => 'bi-shield-shaded'
    ],
    'hq_director' => [
        'title' => 'ዋና መ/ቤት ዳይሬክተር (HQ Director)',
        'badge' => 'bg-primary text-white',
        'icon'  => 'bi-building-check'
    ],
    'subcity_director' => [
        'title' => 'ክ/ከተማ ዳይሬክተር (Subcity Director)',
        'badge' => 'bg-success text-white',
        'icon'  => 'bi-person-check-fill'
    ],
    'planner' => [
        'title' => 'እቅድ አውጪ (Planner)',
        'badge' => 'bg-warning text-dark',
        'icon'  => 'bi-calendar-check'
    ],
    'reporter' => [
        'title' => 'ሪፖርተር (Reporter)',
        'badge' => 'bg-info text-dark',
        'icon'  => 'bi-file-earmark-bar-graph'
    ]
];
$my_role = $role_meta[$current_role] ?? ['title' => ucfirst($current_role), 'badge' => 'bg-secondary text-white', 'icon' => 'bi-person'];

// Helper to highlight active navigation link
$current_script = basename($_SERVER['PHP_SELF']);
function is_nav_active($pageName, $currentScript)
{
    return ($pageName === $currentScript) ? 'active shadow-sm' : '';
}
?>
<!DOCTYPE html>
<html lang="am">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LHPRIA - የእቅድና ሪፖርት አስተዳደር ስርዓት (PRMS)</title>

    <!-- Google Fonts: Inter & Noto Sans Ethiopic -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 & Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">

    <style>
        :root {
            --sidebar-width: 270px;
            --sidebar-bg: #0f172a;
            --sidebar-hover: #1e293b;
            --sidebar-active: #3b82f6;
            --brand-primary: #1e40af;
            --brand-gradient: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
        }

        body {
            font-family: 'Inter', 'Noto Sans Ethiopic', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f8fafc;
            color: #334155;
            min-height: 100vh;
        }

        /* Top Navigation Bar */
        .app-topbar {
            height: 68px;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            box-shadow: 0 2px 12px rgba(15, 23, 42, 0.04);
            z-index: 1020;
        }

        /* Sidebar Styling */
        .app-sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            min-height: calc(100vh - 68px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1010;
        }

        .sidebar-section-title {
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #94a3b8;
            font-weight: 700;
            margin: 1.2rem 1rem 0.5rem 1rem;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            margin: 3px 12px;
            border-radius: 8px;
            color: #cbd5e1;
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .sidebar-link i {
            font-size: 1.15rem;
            transition: transform 0.2s ease;
        }

        .sidebar-link:hover {
            background-color: var(--sidebar-hover);
            color: #ffffff;
            transform: translateX(3px);
        }

        .sidebar-link.active {
            background: var(--brand-gradient);
            color: #ffffff;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.35);
        }

        .sidebar-link.active i {
            color: #ffffff;
        }

        /* User Profile Pill */
        .user-dropdown-btn {
            border: 1px solid #e2e8f0;
            border-radius: 50px;
            padding: 6px 14px 6px 8px;
            background: #ffffff;
            transition: all 0.2s ease;
        }

        .user-dropdown-btn:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        .avatar-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--brand-gradient);
            color: #ffffff;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            box-shadow: 0 2px 6px rgba(59, 130, 246, 0.3);
        }

        .subcity-badge-pill {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            border-radius: 50px;
            padding: 5px 12px;
            font-weight: 600;
            font-size: 0.82rem;
        }

        /* Mobile Adjustments */
        @media (max-width: 991.98px) {
            .app-sidebar {
                position: fixed;
                top: 68px;
                left: -270px;
                height: calc(100vh - 68px);
            }

            .app-sidebar.show {
                left: 0;
            }
        }
    </style>
</head>

<body>

    <!-- TOPBAR -->
    <header class="app-topbar sticky-top d-flex align-items-center px-3 px-lg-4">
        <!-- Brand & Mobile Toggle -->
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-light d-lg-none border" type="button" onclick="toggleSidebar()">
                <i class="bi bi-list fs-5"></i>
            </button>
            <a href="<?= base_url() ?>" class="d-flex align-items-center gap-2 text-decoration-none">
                <div class="avatar-circle" style="background: linear-gradient(135deg, #1e3a8a, #d97706);">
                    <i class="bi bi-bank2"></i>
                </div>
                <div class="d-none d-sm-block">
                    <span class="fw-bold text-dark fs-5 tracking-tight d-block lh-1">LHPRIA - PRMS</span>
                    <span class="text-muted small" style="font-size: 0.68rem;">የመሬት ይዞታና መሬት ነክ ንብረት ምዝገባ እና መረጃ ኤጀንሲ</span>
                </div>
            </a>
        </div>

        <!-- Middle Info: Subcity & Fiscal Year Pill -->
        <div class="ms-auto me-3 d-flex align-items-center gap-2">
            <div class="subcity-badge-pill d-none d-md-flex align-items-center gap-1 shadow-sm">
                <i class="bi bi-geo-alt-fill text-primary"></i>
                <span><?= htmlspecialchars($current_subcity) ?></span>
            </div>
            <div class="badge bg-light text-secondary border px-3 py-2 rounded-pill d-none d-sm-inline-block">
                <i class="bi bi-calendar3 text-dark me-1"></i>2019 E.C. (ዓ.ም.)
            </div>
        </div>

        <!-- User Profile Dropdown -->
        <div class="dropdown">
            <button class="btn user-dropdown-btn d-flex align-items-center gap-2 shadow-sm dropdown-toggle text-dark" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="avatar-circle">
                    <?= strtoupper(substr($current_username, 0, 1)) ?>
                </div>
                <div class="text-start d-none d-md-block" style="line-height: 1.2;">
                    <div class="fw-bold small text-dark"><?= htmlspecialchars($current_username) ?></div>
                    <span class="badge <?= $my_role['badge'] ?> px-2 py-0" style="font-size: 0.68rem;"><?= $my_role['title'] ?></span>
                </div>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-2 mt-2" style="min-width: 240px; border-radius: 12px;">
                <li class="p-2 border-bottom mb-2 bg-light rounded">
                    <div class="fw-bold text-dark"><?= htmlspecialchars($current_username) ?></div>
                    <div class="small text-muted"><?= htmlspecialchars($current_subcity) ?></div>
                    <span class="badge <?= $my_role['badge'] ?> mt-1"><?= $my_role['title'] ?></span>
                </li>
                <li>
                    <a class="dropdown-item rounded py-2 d-flex align-items-center gap-2" href="<?= base_url('change_password.php') ?>">
                        <i class="bi bi-key-fill text-warning fs-6"></i>
                        <span>የይለፍ ቃል ቀይር (Change Password)</span>
                    </a>
                </li>
                <?php if ($current_role === 'admin'): ?>
                    <li>
                        <a class="dropdown-item rounded py-2 d-flex align-items-center gap-2" href="<?= base_url('admin/users.php') ?>">
                            <i class="bi bi-people-fill text-primary fs-6"></i>
                            <span>ተጠቃሚዎች ማስተዳደሪያ</span>
                        </a>
                    </li>
                <?php endif; ?>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li>
                    <a class="dropdown-item rounded py-2 d-flex align-items-center gap-2 text-danger fw-semibold" href="<?= base_url('logout.php') ?>">
                        <i class="bi bi-box-arrow-right fs-6"></i>
                        <span>ውጣ (Logout)</span>
                    </a>
                </li>
            </ul>
        </div>
    </header>

    <!-- WRAPPER: Sidebar + Main Content -->
    <div class="d-flex">
        <!-- SIDEBAR -->
        <aside class="app-sidebar py-3" id="appSidebar">
            <!-- Section 1: Dashboard / High Level -->
            <div class="sidebar-section-title">ዳሽቦርድ እና አጠቃላይ እይታ</div>

            <?php if (in_array($current_role, ['hq_director', 'admin'])): ?>
                <a href="<?= base_url('hq/dashboard.php') ?>" class="sidebar-link <?= is_nav_active('dashboard.php', $current_script) ?>">
                    <i class="bi bi-speedometer2 text-info"></i>
                    <span>የዋና መ/ቤት ዳሽቦርድ</span>
                </a>
            <?php endif; ?>

            <!-- Section 2: Planning (Planner & Director) -->
            <?php if ($current_role === 'planner'): ?>
                <div class="sidebar-section-title">የእቅድ ዝግጅት</div>
                <a href="<?= base_url('planner/plans.php') ?>" class="sidebar-link <?= is_nav_active('plans.php', $current_script) ?>">
                    <i class="bi bi-calendar2-range text-warning"></i>
                    <span>የእቅድ ዝግጅት (Plans)</span>
                </a>
            <?php endif; ?>

            <!-- Section 3: Subcity Director Reviews -->
            <?php if ($current_role === 'subcity_director'): ?>
                <div class="sidebar-section-title">የክፍለ ከተማ ዳይሬክተር</div>
                <a href="<?= base_url('director/review_plans.php') ?>" class="sidebar-link <?= is_nav_active('review_plans.php', $current_script) ?>">
                    <i class="bi bi-patch-check-fill text-success"></i>
                    <span>እቅዶችን መርምርና አጽድቅ</span>
                </a>
                <a href="<?= base_url('director/subcity_reports.php') ?>" class="sidebar-link <?= is_nav_active('subcity_reports.php', $current_script) ?>">
                    <i class="bi bi-send-check text-info"></i>
                    <span>የክ/ከተማ ሪፖርቶች ክለሳ</span>
                </a>
            <?php endif; ?>

            <!-- Section 4: Progress Reporting (Reporter) -->
            <?php if ($current_role === 'reporter'): ?>
                <div class="sidebar-section-title">የአፈፃፀም ሪፖርት</div>
                <a href="<?= base_url('reporter/create_report.php') ?>" class="sidebar-link <?= is_nav_active('create_report.php', $current_script) ?>">
                    <i class="bi bi-file-earmark-plus-fill text-primary"></i>
                    <span>አዲስ ሪፖርት ማዘጋጃ</span>
                </a>
                <a href="<?= base_url('reporter/submit_report.php') ?>" class="sidebar-link <?= is_nav_active('submit_report.php', $current_script) ?>">
                    <i class="bi bi-card-checklist text-secondary"></i>
                    <span>የቀረቡ ሪፖርቶች ዝርዝር</span>
                </a>
            <?php endif; ?>

            <!-- Section 5: Administration -->
            <?php if ($current_role === 'admin'): ?>
                <div class="sidebar-section-title">ሲስተም አስተዳደር</div>
                <a href="<?= base_url('admin/users.php') ?>" class="sidebar-link <?= is_nav_active('users.php', $current_script) ?>">
                    <i class="bi bi-people-fill text-danger"></i>
                    <span>የተጠቃሚዎች ማስተዳደሪያ</span>
                </a>
            <?php endif; ?>

            <!-- User Self Service -->
            <div class="sidebar-section-title">የግል መለያ</div>
            <a href="<?= base_url('change_password.php') ?>" class="sidebar-link <?= is_nav_active('change_password.php', $current_script) ?>">
                <i class="bi bi-shield-lock-fill text-warning"></i>
                <span>የይለፍ ቃል መቀየሪያ</span>
            </a>
            <a href="<?= base_url('logout.php') ?>" class="sidebar-link text-danger-emphasis">
                <i class="bi bi-box-arrow-right text-danger"></i>
                <span>ውጣ (Logout)</span>
            </a>
        </aside>

        <!-- MAIN PAGE CONTENT CONTAINER (Closed by footer.php) -->
        <main class="flex-grow-1 p-3 p-md-4 overflow-auto" style="min-height: calc(100vh - 68px);">

            <script>
                function toggleSidebar() {
                    const sb = document.getElementById('appSidebar');
                    if (sb) {
                        sb.classList.toggle('show');
                    }
                }
            </script>