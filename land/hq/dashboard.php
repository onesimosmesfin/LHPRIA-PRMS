<?php
// hq/dashboard.php
require_once '../includes/auth_check.php';

check_auth(['hq_director', 'admin']);

$msg = '';
$err = '';

// Handle HQ Feedback / Remarks Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_hq_remarks'])) {
    $report_id  = (int)($_POST['report_id'] ?? 0);
    $hq_remarks = trim($_POST['hq_remarks'] ?? '');

    if ($report_id > 0) {
        $stmt_hq = $pdo->prepare("UPDATE reports SET hq_remarks = ?, updated_at = NOW() WHERE id = ?");
        $stmt_hq->execute([$hq_remarks, $report_id]);
        $msg = "የዋና መስሪያ ቤት አስተያየት/ግብረ-መልስ በተሳካ ሁኔታ ተመዝግቧል! (HQ feedback saved successfully!)";
    }
}

// 1. Fetch All Subcities (11 Subcities)
$stmt_loc = $pdo->query("SELECT id, name, code FROM locations WHERE type = 'subcity' ORDER BY name ASC");
$subcities = $stmt_loc->fetchAll(PDO::FETCH_ASSOC);

// 2. Fetch All Subcity Plans with Plan Items & User Attribution
$sql_plans = "
    SELECT p.*, l.name AS subcity_name, l.code AS subcity_code, u.username AS planner_name
    FROM plans p
    JOIN locations l ON p.location_id = l.id
    LEFT JOIN users u ON p.created_by = u.id
    WHERE l.type = 'subcity'
    ORDER BY p.created_at DESC
";
$stmt_pl = $pdo->query($sql_plans);
$all_plans = $stmt_pl->fetchAll(PDO::FETCH_ASSOC);

$pl_items_map = [];
if (!empty($all_plans)) {
    $pl_ids = array_column($all_plans, 'id');
    $placeholders = str_repeat('?,', count($pl_ids) - 1) . '?';
    $stmt_pi = $pdo->prepare("
        SELECT id, plan_id, kpi_name, target_value, unit_of_measure, planned_revenue
        FROM plan_items
        WHERE plan_id IN ($placeholders)
        ORDER BY id ASC
    ");
    $stmt_pi->execute($pl_ids);
    $pi_rows = $stmt_pi->fetchAll(PDO::FETCH_ASSOC);
    foreach ($pi_rows as $row) {
        $pl_items_map[$row['plan_id']][] = $row;
    }
}

$plans_dict = [];
foreach ($all_plans as &$pl) {
    $pl['items'] = $pl_items_map[$pl['id']] ?? [];
    $plans_dict[$pl['id']] = $pl;
}
unset($pl);

// 3. Fetch All Subcity Reports with Linked Plan & Report Items
$sql_reports = "
    SELECT r.*, p.title AS plan_title, p.fiscal_year, p.period AS plan_period,
           l.name AS subcity_name, l.code AS subcity_code, u.username AS reporter_name
    FROM reports r
    JOIN plans p ON r.plan_id = p.id
    JOIN locations l ON r.location_id = l.id
    LEFT JOIN users u ON r.reported_by = u.id
    WHERE l.type = 'subcity'
    ORDER BY r.created_at DESC
";
$stmt_rep = $pdo->query($sql_reports);
$all_reports = $stmt_rep->fetchAll(PDO::FETCH_ASSOC);

$rep_items_map = [];
if (!empty($all_reports)) {
    $rep_ids = array_column($all_reports, 'id');
    $placeholders = str_repeat('?,', count($rep_ids) - 1) . '?';
    $stmt_ri = $pdo->prepare("
        SELECT ri.*, pi.kpi_name, pi.target_value, pi.unit_of_measure, pi.planned_revenue
        FROM report_items ri
        JOIN plan_items pi ON ri.plan_item_id = pi.id
        WHERE ri.report_id IN ($placeholders)
        ORDER BY ri.id ASC
    ");
    $stmt_ri->execute($rep_ids);
    $ri_rows = $stmt_ri->fetchAll(PDO::FETCH_ASSOC);
    foreach ($ri_rows as $row) {
        $rep_items_map[$row['report_id']][] = $row;
    }
}

$reports_dict = [];
foreach ($all_reports as &$rep) {
    $rep['items'] = $rep_items_map[$rep['id']] ?? [];

    // Calculate aggregate score & revenue collection for this report
    $tot_pct = 0;
    $count = 0;
    $tot_revenue_earned = 0;
    $tot_revenue_planned = 0;

    foreach ($rep['items'] as $item) {
        $t = (float)$item['target_value'];
        $a = (float)$item['achieved_value'];
        if ($t > 0) {
            $tot_pct += ($a / $t) * 100;
        } elseif ($a > 0) {
            $tot_pct += 100;
        }
        $count++;

        $tot_revenue_earned += (float)$item['earned_revenue'];
        $tot_revenue_planned += (float)$item['planned_revenue'];
    }

    $rep['avg_progress'] = $count > 0 ? round($tot_pct / $count, 1) : 0;
    $rep['total_revenue_earned'] = $tot_revenue_earned;
    $rep['total_revenue_planned'] = $tot_revenue_planned;

    $reports_dict[$rep['id']] = $rep;
}
unset($rep);

// 4. Subcity Performance Leaderboard (#1 to #11 ranking)
$scorecard = [];
foreach ($subcities as $sc) {
    $sc_id = $sc['id'];
    $sc_plans = array_filter($all_plans, fn($p) => $p['location_id'] == $sc_id && $p['status'] == 'approved');
    $sc_reports = array_filter($all_reports, fn($r) => $r['location_id'] == $sc_id);

    $sc_total_score = 0;
    $sc_rep_count = count($sc_reports);
    $sc_revenue_earned = 0;
    $sc_revenue_planned = 0;

    foreach ($sc_reports as $r) {
        $sc_total_score += $r['avg_progress'];
        $sc_revenue_earned += $r['total_revenue_earned'];
        $sc_revenue_planned += $r['total_revenue_planned'];
    }

    $avg_score = $sc_rep_count > 0 ? round($sc_total_score / $sc_rep_count, 1) : 0;
    $collection_rate = $sc_revenue_planned > 0 ? round(($sc_revenue_earned / $sc_revenue_planned) * 100, 1) : 0;

    $scorecard[] = [
        'id'                => $sc['id'],
        'name'              => $sc['name'],
        'code'              => $sc['code'],
        'approved_plans'    => count($sc_plans),
        'reports_count'     => $sc_rep_count,
        'avg_score'         => $avg_score,
        'revenue_earned'    => $sc_revenue_earned,
        'revenue_planned'   => $sc_revenue_planned,
        'collection_rate'   => $collection_rate
    ];
}

// Sort leaderboard descending by average performance score
usort($scorecard, fn($a, $b) => $b['avg_score'] <=> $a['avg_score']);

// High-Level Summary Statistics
$total_approved_plans = count(array_filter($all_plans, fn($p) => $p['status'] == 'approved'));
$total_submitted_reports = count($all_reports);
$city_total_revenue_planned = array_sum(array_column($scorecard, 'revenue_planned'));
$city_total_revenue_earned = array_sum(array_column($scorecard, 'revenue_earned'));
$citywide_collection_rate = $city_total_revenue_planned > 0 ? round(($city_total_revenue_earned / $city_total_revenue_planned) * 100, 1) : 0;
$citywide_avg_progress = count($all_reports) > 0 ? round(array_sum(array_column($all_reports, 'avg_progress')) / count($all_reports), 1) : 0;

include '../includes/header.php';
?>

<style>
    @media print {
        body * {
            visibility: hidden;
        }

        #printableReportArea,
        #printableReportArea * {
            visibility: visible;
        }

        #printableReportArea {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }

        .no-print {
            display: none !important;
        }
    }

    .rank-badge-1 {
        background: linear-gradient(135deg, #ffd700, #ffae00);
        color: #000;
        font-weight: bold;
    }

    .rank-badge-2 {
        background: linear-gradient(135deg, #e0e0e0, #bdbdbd);
        color: #000;
        font-weight: bold;
    }

    .rank-badge-3 {
        background: linear-gradient(135deg, #cd7f32, #a0522d);
        color: #fff;
        font-weight: bold;
    }
</style>

<div class="container-fluid py-4">
    <!-- Header Banner -->
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="h3 fw-bold text-dark mb-1">
                <i class="bi bi-speedometer2 text-primary me-2"></i>የዋና መ/ቤት አጠቃላይ መቆጣጠሪያ (HQ Executive Command Dashboard)
            </h2>
            <p class="text-muted small mb-0">የ11ዱም ክፍለ ከተሞች የይዞታ ማረጋገጥ፣ አገልግሎት፣ ዲጂታላይዜሽን፣ ኢ-ላንድ እና የገቢ አሰባሰብ እቅድና አፈፃፀም ዝርዝር መረጃ።</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary" onclick="window.location.reload();">
                <i class="bi bi-arrow-clockwise me-1"></i>አድስ (Refresh)
            </button>
            <button class="btn btn-success fw-bold" onclick="exportScorecardCSV()">
                <i class="bi bi-file-earmark-excel-fill me-1"></i>የውጤት ሰንጠረዥ በ-CSV አውርድ
            </button>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (!empty($msg)): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Top Summary KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm border-0 border-start border-primary border-4 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small fw-bold text-uppercase">የጸደቁ እቅዶች</span>
                        <div class="badge bg-primary-subtle text-primary p-2 rounded-circle"><i class="bi bi-folder-check fs-6"></i></div>
                    </div>
                    <h3 class="fw-bold text-dark mb-1"><?= number_format($total_approved_plans) ?></h3>
                    <div class="text-muted small">በ11 ክፍለ ከተሞች የተፈቀዱ</div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm border-0 border-start border-success border-4 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small fw-bold text-uppercase">የቀረቡ ሪፖርቶች</span>
                        <div class="badge bg-success-subtle text-success p-2 rounded-circle"><i class="bi bi-file-earmark-bar-graph fs-6"></i></div>
                    </div>
                    <h3 class="fw-bold text-dark mb-1"><?= number_format($total_submitted_reports) ?></h3>
                    <div class="text-muted small">ሳምንታዊ/ወርሃዊ ግምገማዎች</div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm border-0 border-start border-info border-4 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small fw-bold text-uppercase">አጠቃላይ የስራ አፈፃፀም</span>
                        <div class="badge bg-info-subtle text-info p-2 rounded-circle"><i class="bi bi-pie-chart-fill fs-6"></i></div>
                    </div>
                    <h3 class="fw-bold text-dark mb-1"><?= $citywide_avg_progress ?>%</h3>
                    <div class="progress mt-2" style="height: 6px;">
                        <div class="progress-bar bg-info" style="width: <?= min($citywide_avg_progress, 100) ?>%;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm border-0 border-start border-success border-4 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small fw-bold text-uppercase">የተሰበሰበ/የተገኘ ገቢ (Earned)</span>
                        <div class="badge bg-success-subtle text-success p-2 rounded-circle"><i class="bi bi-cash-stack fs-6"></i></div>
                    </div>
                    <h3 class="fw-bold text-success mb-1"><?= number_format($city_total_revenue_earned, 2) ?> <small class="fs-6 text-muted">ETB</small></h3>
                    <div class="text-muted small">
                        ከታቀደው ገቢ: <strong><?= number_format($city_total_revenue_planned, 2) ?> ETB</strong> (<?= $citywide_collection_rate ?>%)
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-bar-chart-line-fill text-primary me-2"></i>የ11ዱ ክፍለ ከተሞች የአፈፃፀም ንጽጽር (% Average Progress)</h6>
                    <span class="badge bg-light text-secondary border">የደረጃ ሰንጠረዥ</span>
                </div>
                <div class="card-body">
                    <canvas id="subcityBarChart" style="min-height: 280px;"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-pie-chart text-success me-2"></i>የከተማ አጠቃላይ የገቢ አሰባሰብ (Revenue Target vs Actual)</h6>
                </div>
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <div style="width: 220px; height: 220px;">
                        <canvas id="revenueDoughnutChart"></canvas>
                    </div>
                    <div class="mt-3 text-center small text-muted">
                        የተሰበሰበ ገቢ: <strong class="text-success"><?= number_format($city_total_revenue_earned) ?> ETB</strong> |
                        ቀሪ የሚሰበሰብ: <strong class="text-secondary"><?= number_format(max(0, $city_total_revenue_planned - $city_total_revenue_earned)) ?> ETB</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Subcity Performance Scorecard (Leaderboard #1 to #11) -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold text-dark mb-0"><i class="bi bi-trophy-fill text-warning me-2"></i>የክፍለ ከተሞች የደረጃ ሰንጠረዥ (Subcity Performance Scorecard)</h5>
                <span class="text-muted small">በአማካይ አፈፃፀም ውጤት እና በገቢ አሰባሰብ ምጣኔ መሰረት የተደረጀ</span>
            </div>
            <span class="badge bg-dark px-3 py-2">11 ክፍለ ከተሞች</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="scorecardTable">
                    <thead class="table-light">
                        <tr class="small text-muted">
                            <th style="width: 80px;" class="text-center">ደረጃ (Rank)</th>
                            <th>ክፍለ ከተማ (Subcity)</th>
                            <th class="text-center">የጸደቁ እቅዶች</th>
                            <th class="text-center">የቀረቡ ሪፖርቶች</th>
                            <th style="width: 230px;">አማካይ አፈፃፀም (% Score)</th>
                            <th class="text-end">የተሰበሰበ ገቢ (Earned - ETB)</th>
                            <th class="text-center">የገቢ አሰባሰብ ምጣኔ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scorecard as $idx => $sc):
                            $rank = $idx + 1;
                            $rank_class = $rank === 1 ? 'rank-badge-1' : ($rank === 2 ? 'rank-badge-2' : ($rank === 3 ? 'rank-badge-3' : 'bg-light text-dark border'));
                            $medal = $rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : ($rank === 3 ? '🥉' : '#' . $rank));
                        ?>
                            <tr>
                                <td class="text-center">
                                    <span class="badge px-2 py-1 rounded-pill <?= $rank_class ?>"><?= $medal ?></span>
                                </td>
                                <td>
                                    <span class="fw-bold text-dark"><?= htmlspecialchars($sc['name']) ?></span>
                                    <span class="badge bg-light text-muted border ms-1"><?= htmlspecialchars($sc['code']) ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary-subtle text-primary border border-primary px-2"><?= $sc['approved_plans'] ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary-subtle text-secondary border px-2"><?= $sc['reports_count'] ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 10px;">
                                            <div class="progress-bar <?= $sc['avg_score'] >= 80 ? 'bg-success' : ($sc['avg_score'] >= 50 ? 'bg-warning' : 'bg-danger') ?>"
                                                style="width: <?= min($sc['avg_score'], 100) ?>%;"></div>
                                        </div>
                                        <span class="fw-bold small" style="min-width: 45px;"><?= $sc['avg_score'] ?>%</span>
                                    </div>
                                </td>
                                <td class="text-end fw-bold text-success small">
                                    <?= number_format($sc['revenue_earned'], 2) ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= $sc['collection_rate'] >= 100 ? 'bg-success' : ($sc['collection_rate'] >= 75 ? 'bg-primary-subtle text-primary border border-primary' : 'bg-warning-subtle text-dark border border-warning') ?> px-2 py-1">
                                        <?= $sc['collection_rate'] ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- DUAL MASTER INSPECTION TABS (REPORTS & PLANS) -->
    <div class="card shadow-sm border-0 mb-5">
        <div class="card-header bg-white py-3 border-bottom">
            <ul class="nav nav-pills card-header-pills" id="hqInspectionTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-bold" id="reports-tab" data-bs-toggle="pill" data-bs-target="#reports-pane" type="button" role="tab">
                        <i class="bi bi-file-earmark-text-fill me-2"></i>1. የክፍለ ከተሞች ሪፖርቶች (All Reports - <?= count($all_reports) ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold" id="plans-tab" data-bs-toggle="pill" data-bs-target="#plans-pane" type="button" role="tab">
                        <i class="bi bi-journal-check me-2"></i>2. የክፍለ ከተሞች እቅዶች (All Plans - <?= count($all_plans) ?>)
                    </button>
                </li>
            </ul>
        </div>

        <div class="card-body p-0">
            <div class="tab-content" id="hqInspectionTabsContent">

                <!-- TAB 1: REPORTS INSPECTION -->
                <div class="tab-pane fade show active" id="reports-pane" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr class="small text-muted">
                                    <th>#</th>
                                    <th>ክፍለ ከተማ (Subcity)</th>
                                    <th>የሪፖርቱ ርዕስ (Report Title)</th>
                                    <th>የሪፖርት ወቅት (Period)</th>
                                    <th>ሪፖርተር</th>
                                    <th class="text-center">የአፈፃፀም ውጤት (% Score)</th>
                                    <th class="text-center">ሁኔታ (Status)</th>
                                    <th class="text-end">እርምጃ (Action)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($all_reports)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-5">ምንም የቀረበ ሪፖርት አልተገኘም።</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($all_reports as $r): ?>
                                        <tr>
                                            <td class="text-muted fw-bold">#<?= $r['id'] ?></td>
                                            <td>
                                                <span class="badge bg-light text-dark border fw-bold"><?= htmlspecialchars($r['subcity_name']) ?></span>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($r['report_title']) ?></div>
                                                <div class="small text-muted">እቅድ: <?= htmlspecialchars($r['plan_title']) ?></div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($r['period']) ?></span>
                                            </td>
                                            <td class="small text-dark">
                                                <i class="bi bi-person me-1"></i><?= htmlspecialchars($r['reporter_name'] ?? 'N/A') ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge <?= $r['avg_progress'] >= 80 ? 'bg-success' : ($r['avg_progress'] >= 50 ? 'bg-warning text-dark' : 'bg-danger') ?> px-2 py-1">
                                                    <?= $r['avg_progress'] ?>%
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <?php if (!empty($r['hq_remarks'])): ?>
                                                    <span class="badge bg-info text-dark"><i class="bi bi-chat-check-fill me-1"></i>HQ ታይቷል</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary-subtle text-primary border border-primary">አዲስ ሪፖርት</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-primary fw-bold" onclick="openReportModal(<?= $r['id'] ?>)">
                                                    <i class="bi bi-eye-fill me-1"></i>በዝርዝር መርምር
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 2: PLANS INSPECTION -->
                <div class="tab-pane fade" id="plans-pane" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr class="small text-muted">
                                    <th>#</th>
                                    <th>ክፍለ ከተማ (Subcity)</th>
                                    <th>የእቅዱ ርዕስ (Plan Title)</th>
                                    <th>የበጀት ዓመት / ሩብ</th>
                                    <th>ፕላነር</th>
                                    <th class="text-center">የተካተቱ ተግባራት</th>
                                    <th class="text-center">ሁኔታ (Status)</th>
                                    <th class="text-end">እርምጃ (Action)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($all_plans)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-5">ምንም እቅድ አልተገኘም።</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($all_plans as $p): ?>
                                        <tr>
                                            <td class="text-muted fw-bold">#<?= $p['id'] ?></td>
                                            <td>
                                                <span class="badge bg-light text-dark border fw-bold"><?= htmlspecialchars($p['subcity_name']) ?></span>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($p['title']) ?></div>
                                                <div class="small text-muted text-truncate" style="max-width: 320px;"><?= htmlspecialchars($p['description'] ?? '') ?></div>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary-subtle text-primary"><?= htmlspecialchars($p['fiscal_year']) ?> (<?= htmlspecialchars($p['period']) ?>)</span>
                                            </td>
                                            <td class="small text-dark">
                                                <i class="bi bi-person me-1"></i><?= htmlspecialchars($p['planner_name'] ?? 'N/A') ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-secondary px-2"><?= count($p['items']) ?> ተግባራት</span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge <?= $p['status'] === 'approved' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                                    <?= $p['status'] === 'approved' ? 'የጸደቀ (Approved)' : htmlspecialchars($p['status']) ?>
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-outline-primary fw-bold" onclick="openPlanModal(<?= $p['id'] ?>)">
                                                    <i class="bi bi-journal-text me-1"></i>እቅዱን መርምር
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- 1. DETAILED REPORT INSPECTION MODAL       -->
<!-- ========================================== -->
<div class="modal fade" id="reportInspectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content shadow border-0" id="printableReportArea">
            <div class="modal-header bg-primary text-white py-3 no-print">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-check fs-4"></i>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="repModalSubcity">የሪፖርት ዝርዝር ምርመራ</h5>
                        <small class="text-white-50" id="repModalPeriod">ወቅት</small>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-light fw-bold" onclick="window.print()">
                        <i class="bi bi-printer-fill me-1"></i>አትም (Print / PDF)
                    </button>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>

            <div class="modal-body p-4">
                <!-- Executive Header Banner for Print / Display -->
                <div class="p-3 bg-light rounded border mb-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <span class="badge bg-primary px-2 py-1 mb-1" id="repModalSubcityBadge">አራዳ ክፍለ ከተማ</span>
                            <h4 class="fw-bold text-dark mb-1" id="repModalTitle">የሪፖርት ርዕስ</h4>
                            <div class="small text-muted">
                                <strong>እቅድ:</strong> <span id="repModalPlanTitle">--</span> |
                                <strong>ሪፖርተር:</strong> <span id="repModalReporter">--</span> |
                                <strong>የቀረበበት ቀን:</strong> <span id="repModalDate">--</span>
                            </div>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                            <div class="p-2 bg-white rounded border d-inline-block text-center shadow-sm" style="min-width: 140px;">
                                <div class="small text-muted fw-bold">አማካይ አፈፃፀም</div>
                                <div class="display-6 fw-bold text-primary mb-0" id="repModalScoreBadge">0%</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- KPI Summary Row for Revenue -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="p-3 bg-success-subtle border border-success rounded">
                            <div class="small text-success fw-bold">የተሰበሰበ/የተገኘ ገቢ (Earned Revenue)</div>
                            <h5 class="fw-bold text-success mb-0" id="repModalRevenueEarned">0.00 ETB</h5>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-primary-subtle border border-primary rounded">
                            <div class="small text-primary fw-bold">የታቀደ ጠቅላላ ገቢ (Planned Revenue)</div>
                            <h5 class="fw-bold text-dark mb-0" id="repModalRevenuePlanned">0.00 ETB</h5>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-info-subtle border border-info rounded">
                            <div class="small text-info fw-bold text-dark">የገቢ አሰባሰብ ምጣኔ (Collection Rate)</div>
                            <h5 class="fw-bold text-dark mb-0" id="repModalRevenueRate">0%</h5>
                        </div>
                    </div>
                </div>

                <!-- Plan vs Actual Multi-Activity Comparison Table -->
                <h6 class="fw-bold text-dark mb-2">
                    <i class="bi bi-table text-primary me-2"></i>የታቀደ vs የተከናወነ ተግባራት ንጽጽር (Plan vs. Actual Activity Breakdown)
                </h6>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered align-middle">
                        <thead class="table-secondary small text-dark">
                            <tr>
                                <th style="width: 32%;">የተግባር ስም (Planned Activity)</th>
                                <th class="text-center" style="width: 14%;">የታቀደ ግብ (Target)</th>
                                <th class="text-center" style="width: 14%;">የተከናወነ (Achieved)</th>
                                <th style="width: 20%;">የአፈፃፀም ምጣኔ (% Execution)</th>
                                <th class="text-end" style="width: 20%;">የተሰበሰበ ገቢ (Earned Revenue)</th>
                            </tr>
                        </thead>
                        <tbody id="repModalTableBody">
                            <!-- Populated dynamically via JS -->
                        </tbody>
                    </table>
                </div>

                <!-- Qualitative Section: Performance & Challenges -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="card h-100 border">
                            <div class="card-header bg-light py-2 fw-bold small text-dark">
                                <i class="bi bi-hand-thumbs-up-fill text-success me-1"></i>አጠቃላይ የስራ አፈፃፀም ግምገማ (General Performance)
                            </div>
                            <div class="card-body p-3 small text-secondary" id="repModalPerformance">
                                ምንም አልተገለጸም
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card h-100 border">
                            <div class="card-header bg-light py-2 fw-bold small text-dark">
                                <i class="bi bi-exclamation-octagon-fill text-danger me-1"></i>ያጋጠሙ ችግሮችና የተወሰዱ እርምጃዎች (Challenges & Mitigation)
                            </div>
                            <div class="card-body p-3 small text-secondary" id="repModalChallenges">
                                ምንም አልተገለጸም
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Subcity Director Remarks (if any) -->
                <div class="card border border-info mb-4" id="repModalSubcityRemarksBox">
                    <div class="card-header bg-info-subtle py-2 fw-bold small text-dark">
                        <i class="bi bi-chat-quote-fill text-info me-1"></i>የክፍለ ከተማ ዳይሬክተር የጸደቀ አስተያየት (Subcity Director Approval Remarks)
                    </div>
                    <div class="card-body p-3 small text-dark" id="repModalSubcityRemarks">
                        --
                    </div>
                </div>

                <!-- HQ Director Action & Feedback Section (Excluded in Print) -->
                <div class="card border-primary shadow-sm no-print">
                    <div class="card-header bg-primary-subtle py-2 fw-bold small text-primary">
                        <i class="bi bi-pencil-square me-1"></i>የዋና መ/ቤት ዳይሬክተር ግብረ-መልስ (HQ Director Official Feedback)
                    </div>
                    <div class="card-body p-3">
                        <form method="POST" action="dashboard.php">
                            <input type="hidden" name="report_id" id="repModalInputId" value="">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">ለክፍለ ከተማው የሚሰጥ ኦፊሴላዊ አስተያየት ወይም መመሪያ:</label>
                                <textarea name="hq_remarks" id="repModalHqRemarksText" class="form-control" rows="3" placeholder="ለዚህ ሪፖርት ማስተካከያ፣ ማበረታቻ ወይም መመሪያ እዚህ ይጻፉ..."></textarea>
                            </div>
                            <div class="text-end">
                                <button type="submit" name="save_hq_remarks" class="btn btn-primary fw-bold px-4">
                                    <i class="bi bi-check2-circle me-1"></i>አስተያየቱን መዝግብ (Save HQ Remarks)
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- 2. DETAILED PLAN INSPECTION MODAL         -->
<!-- ========================================== -->
<div class="modal fade" id="planInspectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-dark text-white py-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-journal-text fs-4 text-warning"></i>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="planModalTitle">የእቅድ ዝርዝር መረጃ</h5>
                        <small class="text-white-50" id="planModalSubcity">ክፍለ ከተማ</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4">
                <div class="p-3 bg-light rounded border mb-3">
                    <div class="row">
                        <div class="col-sm-6 mb-2">
                            <span class="small text-muted d-block">የበጀት ዓመት & ሩብ:</span>
                            <strong class="text-dark" id="planModalPeriod">2019 E.C. - Q2</strong>
                        </div>
                        <div class="col-sm-6 mb-2">
                            <span class="small text-muted d-block">ፕላነር (አዘጋጅ):</span>
                            <strong class="text-dark" id="planModalPlanner">--</strong>
                        </div>
                        <div class="col-12 mt-2">
                            <span class="small text-muted d-block">የእቅዱ ማብራሪያ:</span>
                            <p class="small text-secondary mb-0" id="planModalDesc">--</p>
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold text-dark mb-2">
                    <i class="bi bi-list-check text-primary me-2"></i>የታቀዱ ተግባራት ዝርዝር (Activities Planned)
                </h6>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-secondary small text-dark">
                            <tr>
                                <th>የተግባር ስም</th>
                                <th class="text-center">መለኪያ</th>
                                <th class="text-center">የታቀደ ግብ</th>
                                <th class="text-end">የታቀደ ገቢ (Planned Revenue - ETB)</th>
                            </tr>
                        </thead>
                        <tbody id="planModalTableBody">
                            <!-- Populated dynamically via JS -->
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td colspan="3" class="text-end">ጠቅላላ የታቀደ ገቢ:</td>
                                <td class="text-end text-success" id="planModalTotalRevenue">0.00 ETB</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="card border-warning mt-3 d-none" id="planModalFeedbackBox">
                    <div class="card-header bg-warning-subtle py-2 small fw-bold text-dark">
                        <i class="bi bi-chat-left-dots-fill text-warning me-1"></i>የዳይሬክተር አስተያየት (Director Feedback)
                    </div>
                    <div class="card-body p-3 small text-dark" id="planModalFeedback">
                        --
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript Data & Chart Logic -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    const reportsData = <?= json_encode($reports_dict, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;
    const plansData = <?= json_encode($plans_dict, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;
    const scorecardData = <?= json_encode($scorecard, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;

    // Open & Populate Detailed Report Modal
    function openReportModal(reportId) {
        const r = reportsData[reportId];
        if (!r) return;

        document.getElementById('repModalInputId').value = r.id;
        document.getElementById('repModalSubcity').textContent = r.subcity_name + ' - ሪፖርት #' + r.id;
        document.getElementById('repModalSubcityBadge').textContent = r.subcity_name;
        document.getElementById('repModalPeriod').textContent = r.period;
        document.getElementById('repModalTitle').textContent = r.report_title;
        document.getElementById('repModalPlanTitle').textContent = r.plan_title;
        document.getElementById('repModalReporter').textContent = r.reporter_name || 'N/A';
        document.getElementById('repModalDate').textContent = r.created_at || 'N/A';
        document.getElementById('repModalScoreBadge').textContent = r.avg_progress + '%';

        document.getElementById('repModalRevenueEarned').textContent = Number(r.total_revenue_earned).toLocaleString(undefined, {
            minimumFractionDigits: 2
        }) + ' ETB';
        document.getElementById('repModalRevenuePlanned').textContent = Number(r.total_revenue_planned).toLocaleString(undefined, {
            minimumFractionDigits: 2
        }) + ' ETB';

        const colRate = r.total_revenue_planned > 0 ? Math.round((r.total_revenue_earned / r.total_revenue_planned) * 100) : 0;
        document.getElementById('repModalRevenueRate').textContent = colRate + '%';

        // Populate Activities Comparison Table
        const tbody = document.getElementById('repModalTableBody');
        tbody.innerHTML = '';

        if (r.items && r.items.length > 0) {
            r.items.forEach(item => {
                const t = parseFloat(item.target_value) || 0;
                const a = parseFloat(item.achieved_value) || 0;
                const pct = t > 0 ? Math.round((a / t) * 100) : (a > 0 ? 100 : 0);
                const cappedPct = Math.min(Math.max(pct, 0), 100);

                const badgeClass = pct >= 80 ? 'bg-success' : (pct >= 50 ? 'bg-warning text-dark' : 'bg-danger');

                const tr = document.createElement('tr');
                tr.innerHTML = `
                <td>
                    <div class="fw-bold text-dark small">${escapeHtml(item.kpi_name)}</div>
                    <span class="badge bg-light text-secondary border small">መለኪያ: ${escapeHtml(item.unit_of_measure)}</span>
                </td>
                <td class="text-center fw-bold text-primary">
                    ${t.toLocaleString()} <span class="small text-muted">${escapeHtml(item.unit_of_measure)}</span>
                </td>
                <td class="text-center fw-bold text-dark">
                    ${a.toLocaleString()} <span class="small text-muted">${escapeHtml(item.unit_of_measure)}</span>
                </td>
                <td>
                    <div class="d-flex align-items-center gap-1">
                        <div class="progress flex-grow-1" style="height: 8px;">
                            <div class="progress-bar ${badgeClass}" style="width: ${cappedPct}%;"></div>
                        </div>
                        <span class="badge ${badgeClass} small">${pct}%</span>
                    </div>
                </td>
                <td class="text-end">
                    <span class="fw-bold text-success">${Number(item.earned_revenue).toLocaleString(undefined, {minimumFractionDigits: 2})} ETB</span>
                    <div class="small text-muted">እቅድ: ${Number(item.planned_revenue).toLocaleString(undefined, {minimumFractionDigits: 2})} ETB</div>
                </td>
            `;
                tbody.appendChild(tr);
            });
        } else {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-3">ምንም የተግባራት ዝርዝር አልተገኘም።</td></tr>`;
        }

        document.getElementById('repModalPerformance').textContent = r.general_performance || 'ምንም አልተገለጸም';
        document.getElementById('repModalChallenges').textContent = r.challenges_faced || 'ምንም አልተገለጸም';

        // Subcity remarks
        const scBox = document.getElementById('repModalSubcityRemarksBox');
        if (r.subcity_remarks) {
            scBox.classList.remove('d-none');
            document.getElementById('repModalSubcityRemarks').textContent = r.subcity_remarks;
        } else {
            scBox.classList.add('d-none');
        }

        // HQ remarks input
        document.getElementById('repModalHqRemarksText').value = r.hq_remarks || '';

        const modal = new bootstrap.Modal(document.getElementById('reportInspectModal'));
        modal.show();
    }

    // Open & Populate Detailed Plan Modal
    function openPlanModal(planId) {
        const p = plansData[planId];
        if (!p) return;

        document.getElementById('planModalTitle').textContent = p.title;
        document.getElementById('planModalSubcity').textContent = p.subcity_name + ' (# ' + p.id + ')';
        document.getElementById('planModalPeriod').textContent = (p.fiscal_year || '2019') + ' - ' + (p.period || 'Q1');
        document.getElementById('planModalPlanner').textContent = p.planner_name || 'N/A';
        document.getElementById('planModalDesc').textContent = p.description || 'ምንም ማብራሪያ አልተሰጠም።';

        const tbody = document.getElementById('planModalTableBody');
        tbody.innerHTML = '';
        let totalRevenue = 0;

        if (p.items && p.items.length > 0) {
            p.items.forEach(item => {
                const revenueTarget = parseFloat(item.planned_revenue) || 0;
                totalRevenue += revenueTarget;

                const tr = document.createElement('tr');
                tr.innerHTML = `
                <td class="fw-bold text-dark small">${escapeHtml(item.kpi_name)}</td>
                <td class="text-center small">${escapeHtml(item.unit_of_measure)}</td>
                <td class="text-center fw-bold text-primary">${Number(item.target_value).toLocaleString()}</td>
                <td class="text-end fw-bold text-success">${revenueTarget.toLocaleString(undefined, {minimumFractionDigits: 2})} ETB</td>
            `;
                tbody.appendChild(tr);
            });
        } else {
            tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted py-3">ምንም የታቀዱ ተግባራት አልተገኙም።</td></tr>`;
        }

        document.getElementById('planModalTotalRevenue').textContent = totalRevenue.toLocaleString(undefined, {
            minimumFractionDigits: 2
        }) + ' ETB';

        const fbBox = document.getElementById('planModalFeedbackBox');
        if (p.director_feedback) {
            fbBox.classList.remove('d-none');
            document.getElementById('planModalFeedback').textContent = p.director_feedback;
        } else {
            fbBox.classList.add('d-none');
        }

        const modal = new bootstrap.Modal(document.getElementById('planInspectModal'));
        modal.show();
    }

    // Render Charts
    document.addEventListener('DOMContentLoaded', () => {
        // 1. Subcity Performance Bar Chart
        const ctxBar = document.getElementById('subcityBarChart');
        if (ctxBar) {
            const labels = scorecardData.map(s => s.name);
            const scores = scorecardData.map(s => s.avg_score);
            const backgroundColors = scores.map(s => s >= 80 ? '#198754' : (s >= 50 ? '#ffc107' : '#dc3545'));

            new Chart(ctxBar, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'አማካይ አፈፃፀም (%)',
                        data: scores,
                        backgroundColor: backgroundColors,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: v => v + '%'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }

        // 2. Revenue Doughnut Chart (Target vs Actual Earned)
        const ctxDoughnut = document.getElementById('revenueDoughnutChart');
        if (ctxDoughnut) {
            const earned = <?= (float)$city_total_revenue_earned ?>;
            const planned = <?= (float)$city_total_revenue_planned ?>;
            const remaining = Math.max(0, planned - earned);

            new Chart(ctxDoughnut, {
                type: 'doughnut',
                data: {
                    labels: ['የተሰበሰበ ገቢ (Earned)', 'ቀሪ የሚሰበሰብ (Remaining Target)'],
                    datasets: [{
                        data: [earned, remaining],
                        backgroundColor: ['#198754', '#6c757d'],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
    });

    // Export Scorecard to CSV
    function exportScorecardCSV() {
        let csv = "Rank,Subcity,Approved Plans,Reports Count,Avg Score (%),Earned Revenue (ETB),Planned Revenue (ETB),Collection Rate (%)\n";
        scorecardData.forEach((s, idx) => {
            csv += `${idx + 1},"${s.name}",${s.approved_plans},${s.reports_count},${s.avg_score},${s.revenue_earned},${s.revenue_planned},${s.collection_rate}%\n`;
        });

        const blob = new Blob([csv], {
            type: 'text/csv;charset=utf-8;'
        });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = `Subcity_Revenue_Scorecard_${new Date().toISOString().slice(0,10)}.csv`;
        link.click();
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
</script>

<?php include '../includes/footer.php'; ?>