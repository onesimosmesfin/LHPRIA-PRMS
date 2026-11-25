<?php
// reporter/submit_report.php
require_once '../includes/auth_check.php';
check_auth(['reporter']);

$loc_id = $_SESSION['location_id'];
$msg = '';
$err = '';

// Handle Report Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    $plan_id = (int)($_POST['plan_id'] ?? 0);
    $report_title = trim($_POST['report_title'] ?? '');
    $period = $_POST['period'] ?? 'Q1';
    $achieved = (float)($_POST['achieved_value'] ?? 0);
    $earned_revenue = (float)($_POST['earned_revenue'] ?? 0);
    $perf = trim($_POST['general_performance'] ?? '');
    $challenges = trim($_POST['challenges_faced'] ?? '');

    if ($plan_id > 0 && !empty($report_title)) {
        // 1. Insert Master Report
        $stmt = $pdo->prepare("
            INSERT INTO reports (plan_id, location_id, reported_by, report_title, period, general_performance, challenges_faced, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'submitted_to_subcity')
        ");
        $stmt->execute([$plan_id, $loc_id, $_SESSION['user_id'], $report_title, $period, $perf, $challenges]);
        $report_id = $pdo->lastInsertId();

        // 2. Insert KPI metrics if plan_item exists
        $stmt_kpi = $pdo->prepare("SELECT id FROM plan_items WHERE plan_id = ? LIMIT 1");
        $stmt_kpi->execute([$plan_id]);
        $kpi_item = $stmt_kpi->fetch();

        if ($kpi_item) {
            $stmt_item = $pdo->prepare("
                INSERT INTO report_items (report_id, plan_item_id, achieved_value, earned_revenue) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt_item->execute([$report_id, $kpi_item['id'], $achieved, $earned_revenue]);
        }

        $msg = "Performance report submitted directly to your Subcity Director for review!";
    } else {
        $err = "Please select a valid approved plan and enter a report title.";
    }
}

// Fetch approved plans for this subcity
$stmt = $pdo->prepare("SELECT * FROM plans WHERE location_id = ? AND status = 'approved' ORDER BY created_at DESC");
$stmt->execute([$loc_id]);
$approved_plans = $stmt->fetchAll();

// Fetch reports submitted by this reporter
$stmt_r = $pdo->prepare("
    SELECT r.*, p.title AS plan_title 
    FROM reports r 
    JOIN plans p ON r.plan_id = p.id 
    WHERE r.reported_by = ? 
    ORDER BY r.created_at DESC
");
$stmt_r->execute([$_SESSION['user_id']]);
$my_reports = $stmt_r->fetchAll();

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">Submit Progress Report</h3>
        <p class="text-secondary small mb-0">Record actual progress and budget utilization against approved subcity plans.</p>
    </div>
    <a href="create_report.php" class="btn btn-primary btn-sm px-3 shadow-sm">
        <i class="bi bi-file-earmark-arrow-up me-1"></i> New Report
    </a>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($err) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<?php if (empty($approved_plans)): ?>
    <div class="alert alert-warning py-3 rounded-3 d-flex align-items-center mb-4">
        <i class="bi bi-info-circle-fill fs-4 me-3 text-warning"></i>
        <div>
            <strong>No Approved Plans Available for this Subcity yet!</strong><br>
            <span class="small text-muted">A Subcity Planner must create a plan, and the Subcity Director must approve it before reports can be submitted.</span>
        </div>
    </div>
<?php endif; ?>

<div class="card card-custom">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-journal-text me-2 text-primary"></i>My Submitted Reports</h6>
        <span class="badge bg-light text-secondary border"><?= count($my_reports) ?> Reports</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Report Title</th>
                        <th>Linked Plan</th>
                        <th>Period</th>
                        <th>Submission Date</th>
                        <th class="text-end pe-4">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($my_reports)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">No reports submitted yet. Click "+ New Report" to begin.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($my_reports as $r): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold"><?= htmlspecialchars($r['report_title']) ?></div>
                                <div class="text-muted small text-truncate" style="max-width: 320px;"><?= htmlspecialchars($r['general_performance']) ?></div>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($r['plan_title']) ?></span></td>
                            <td><?= htmlspecialchars($r['period']) ?></td>
                            <td class="text-muted small"><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                            <td class="text-end pe-4">
                                <?php if ($r['status'] === 'forwarded_to_hq'): ?>
                                    <span class="badge bg-primary"><i class="bi bi-send-check me-1"></i> Forwarded to HQ</span>
                                <?php elseif ($r['status'] === 'rolled_back_to_reporter'): ?>
                                    <span class="badge bg-danger"><i class="bi bi-arrow-counterclockwise me-1"></i> Rolled Back</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i> Under Subcity Review</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: New Report -->
<div class="modal fade" id="newReportModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <form method="POST">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Submit Subcity Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Approved Plan</label>
                        <select name="plan_id" class="form-select" required>
                            <option value="">-- Choose an Approved Plan --</option>
                            <?php foreach ($approved_plans as $p): ?>
                                <option value="<?= $p['id'] ?>">
                                    <?= htmlspecialchars($p['title']) ?> (<?= htmlspecialchars($p['fiscal_year']) ?> - <?= htmlspecialchars($p['period']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-8">
                            <label class="form-label small fw-semibold">Report Title</label>
                            <input type="text" name="report_title" class="form-control" placeholder="e.g. Q1 Progress Update" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-semibold">Period</label>
                            <select name="period" class="form-select">
                                <option value="Annual">Annual</option>
                                <option value="Annual">Semi-Annual</option>
                                <option value="Q1">Q1</option>
                                <option value="Q2">Q2</option>
                                <option value="Q3">Q3</option>
                                <option value="Q4">Q4</option>
                                <option value="Monthly">Monthly</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Achieved Quantity</label>
                            <input type="number" step="0.01" name="achieved_value" class="form-control" placeholder="e.g. 1420" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Budget Utilized (ETB)</label>
                            <input type="number" step="0.01" name="earned_revenue" class="form-control" placeholder="e.g. 480000" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Challenges & Blockers Encountered</label>
                        <textarea name="challenges_faced" class="form-control" rows="2" placeholder="Describe any bottlenecks or resource shortages..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Executive Performance Summary</label>
                        <textarea name="general_performance" class="form-control" rows="2" placeholder="Brief summary of milestones reached..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="submit_report" class="btn btn-primary px-4">Submit Report</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>