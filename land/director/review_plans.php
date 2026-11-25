<?php
// director/review_plans.php
require_once '../includes/auth_check.php';
check_auth(['subcity_director', 'admin']);

$director_loc_id = $_SESSION['location_id'] ?? 2;
$msg = '';
$err = '';

// 1. Handle Plan Approval or Rollback
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plan_action'])) {
    $plan_id = (int)$_POST['plan_id'];
    $action = $_POST['plan_action']; // 'approve' or 'rollback'
    $feedback = trim($_POST['feedback'] ?? '');
    $new_status = ($action === 'approve') ? 'approved' : 'rolled_back';

    $stmt = $pdo->prepare("
        UPDATE plans 
        SET status = ?, director_feedback = ?, reviewed_by = ?, reviewed_at = NOW() 
        WHERE id = ? AND location_id = ?
    ");
    $stmt->execute([$new_status, $feedback, $_SESSION['user_id'], $plan_id, $director_loc_id]);
    $msg = ($action === 'approve') ? "Plan has been approved successfully!" : "Plan has been rolled back to Planner with remarks.";
}

// 2. Handle Report Forwarding to HQ or Rollback to Reporter
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_action'])) {
    $report_id = (int)$_POST['report_id'];
    $action = $_POST['report_action']; // 'forward_hq' or 'rollback_reporter'
    $notes = trim($_POST['director_notes'] ?? '');

    if ($action === 'forward_hq') {
        $stmt = $pdo->prepare("
            UPDATE reports 
            SET status = 'forwarded_to_hq', subcity_director_notes = ?, forwarded_to_hq_at = NOW() 
            WHERE id = ? AND location_id = ?
        ");
        $stmt->execute([$notes, $report_id, $director_loc_id]);
        $msg = "Report verified and forwarded to Headquarter (HQ)!";
    } else {
        $stmt = $pdo->prepare("
            UPDATE reports 
            SET status = 'rolled_back_to_reporter', subcity_director_notes = ? 
            WHERE id = ? AND location_id = ?
        ");
        $stmt->execute([$notes, $report_id, $director_loc_id]);
        $msg = "Report rolled back to Reporter for adjustments.";
    }
}

// Fetch all Plans for this subcity with KPI items
$stmt_plans = $pdo->prepare("
    SELECT p.*, u.fullname AS planner_name,
           pi.kpi_name, pi.target_value, pi.unit_of_measure, pi.planned_revenue
    FROM plans p 
    JOIN users u ON p.created_by = u.id 
    LEFT JOIN plan_items pi ON p.id = pi.plan_id
    WHERE p.location_id = ? 
    ORDER BY p.created_at DESC
");
$stmt_plans->execute([$director_loc_id]);
$plans = $stmt_plans->fetchAll();

// Fetch all Incoming Reports for this subcity with item metrics
$stmt_reports = $pdo->prepare("
    SELECT r.*, p.title AS plan_title, u.fullname AS reporter_name,
           ri.achieved_value, ri.earned_revenue, 
           pi.kpi_name, pi.target_value, pi.unit_of_measure, pi.planned_revenue
    FROM reports r 
    JOIN plans p ON r.plan_id = p.id 
    JOIN users u ON r.reported_by = u.id 
    LEFT JOIN report_items ri ON r.id = ri.report_id
    LEFT JOIN plan_items pi ON ri.plan_item_id = pi.id
    WHERE r.location_id = ? 
    ORDER BY r.created_at DESC
");
$stmt_reports->execute([$director_loc_id]);
$reports = $stmt_reports->fetchAll();

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">Subcity Director Verification Center</h3>
        <p class="text-secondary small mb-0">Open and review incoming plans and reports. Approve, adjust, or forward to Headquarter.</p>
    </div>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<!-- Nav Tabs -->
<ul class="nav nav-pills mb-4 bg-white p-2 rounded-3 border" id="reviewTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active fw-semibold" id="plans-tab" data-bs-toggle="pill" data-bs-target="#plans-pane" type="button" role="tab">
            <i class="bi bi-file-earmark-text me-2"></i>Incoming Plans
            <span class="badge bg-primary ms-1"><?= count($plans) ?></span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="reports-tab" data-bs-toggle="pill" data-bs-target="#reports-pane" type="button" role="tab">
            <i class="bi bi-journal-check me-2"></i>Incoming Reports
            <span class="badge bg-warning text-dark ms-1"><?= count($reports) ?></span>
        </button>
    </li>
</ul>

<div class="tab-content" id="reviewTabsContent">
    <!-- ==================== TAB 1: PLANS ==================== -->
    <div class="tab-pane fade show active" id="plans-pane" role="tabpanel">
        <div class="card card-custom">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark">Subcity Plans Submitted by Planners</h6>
                <span class="text-muted small">Click <strong>View Details</strong> to inspect and approve</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Plan Title</th>
                                <th>Fiscal Period</th>
                                <th>Submitted By</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Inspection</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($plans)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No plans submitted for review yet.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($plans as $p): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold"><?= htmlspecialchars($p['title']) ?></div>
                                        <div class="text-muted small text-truncate" style="max-width: 320px;"><?= htmlspecialchars($p['description']) ?></div>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($p['fiscal_year']) ?> (<?= htmlspecialchars($p['period']) ?>)</span></td>
                                    <td><?= htmlspecialchars($p['planner_name']) ?></td>
                                    <td>
                                        <?php if ($p['status'] === 'approved'): ?>
                                            <span class="badge bg-success"><i class="bi bi-check2-all"></i> Approved</span>
                                        <?php elseif ($p['status'] === 'submitted'): ?>
                                            <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split"></i> Awaiting Review</span>
                                        <?php elseif ($p['status'] === 'rolled_back'): ?>
                                            <span class="badge bg-danger"><i class="bi bi-arrow-return-left"></i> Rolled Back</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Draft</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <button class="btn btn-sm btn-outline-primary"
                                            onclick="openPlanModal(<?= htmlspecialchars(json_encode($p)) ?>)">
                                            <i class="bi bi-eye me-1"></i> View Details
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== TAB 2: REPORTS ==================== -->
    <div class="tab-pane fade" id="reports-pane" role="tabpanel">
        <div class="card card-custom">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark">Incoming Reports from Subcity Reporters</h6>
                <span class="text-muted small">Click <strong>View Report</strong> to inspect metrics and forward to HQ</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Report Title & Linked Plan</th>
                                <th>Period</th>
                                <th>Reporter</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($reports)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No reports submitted by reporters yet.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($reports as $r): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold"><?= htmlspecialchars($r['report_title']) ?></div>
                                        <div class="text-muted small"><strong>Plan:</strong> <?= htmlspecialchars($r['plan_title']) ?></div>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($r['period']) ?></span></td>
                                    <td><?= htmlspecialchars($r['reporter_name']) ?></td>
                                    <td>
                                        <?php if ($r['status'] === 'forwarded_to_hq'): ?>
                                            <span class="badge bg-primary"><i class="bi bi-send-check"></i> Forwarded to HQ</span>
                                        <?php elseif ($r['status'] === 'rolled_back_to_reporter'): ?>
                                            <span class="badge bg-danger"><i class="bi bi-arrow-return-left"></i> Rolled Back</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark"><i class="bi bi-clock"></i> Pending Review</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <button class="btn btn-sm btn-primary"
                                            onclick="openReportModal(<?= htmlspecialchars(json_encode($r)) ?>)">
                                            <i class="bi bi-journal-text me-1"></i> Open & Inspect
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== MODAL: VIEW & APPROVE PLAN ==================== -->
<div class="modal fade" id="viewPlanModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom pb-3">
                <div>
                    <h5 class="modal-title fw-bold text-dark" id="vPlanTitle">Plan Details</h5>
                    <span class="badge bg-primary mt-1" id="vPlanPeriod"></span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-4">
                    <h6 class="fw-bold text-secondary text-uppercase small">Plan Description & Objectives</h6>
                    <p class="text-dark bg-light p-3 rounded border" id="vPlanDesc"></p>
                </div>

                <div class="mb-4">
                    <h6 class="fw-bold text-secondary text-uppercase small">Key Performance Indicator (KPI) & Budget Allocation</h6>
                    <div class="table-responsive border rounded">
                        <table class="table mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Indicator Name</th>
                                    <th>Target Quantity</th>
                                    <th>Unit</th>
                                    <th>Allocated Budget (ETB)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="fw-bold" id="vPlanKpi"></td>
                                    <td class="text-primary fw-bold" id="vPlanTarget"></td>
                                    <td id="vPlanUnit"></td>
                                    <td class="text-success fw-bold" id="vPlanBudget"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <form method="POST" id="planDecisionForm">
                    <input type="hidden" name="plan_id" id="vPlanId">
                    <input type="hidden" name="plan_action" id="vPlanAction">

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Director Feedback / Revision Remarks</label>
                        <textarea name="feedback" class="form-control" rows="2" placeholder="Optional notes for approval, or specify required changes if rolling back..."></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-outline-danger" onclick="submitPlanDecision('rollback')">
                            <i class="bi bi-arrow-return-left me-1"></i> Roll Back to Planner
                        </button>
                        <button type="button" class="btn btn-success" onclick="submitPlanDecision('approve')">
                            <i class="bi bi-check2-circle me-1"></i> Approve Plan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ==================== MODAL: VIEW & FORWARD REPORT ==================== -->
<div class="modal fade" id="viewReportModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom pb-3">
                <div>
                    <h5 class="modal-title fw-bold text-dark" id="vRepTitle">Report Details</h5>
                    <span class="text-muted small">Linked to Plan: <strong id="vRepPlan"></strong></span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded border">
                            <div class="text-secondary small fw-bold text-uppercase">Target vs Achieved Quantity</div>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <div>Target: <strong id="vRepTgt"></strong></div>
                                <div class="fs-5 fw-bold text-primary" id="vRepAch"></div>
                            </div>
                            <div class="progress mt-2" style="height: 8px;">
                                <div class="progress-bar bg-primary" id="vRepProg" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded border">
                            <div class="text-secondary small fw-bold text-uppercase">Budget Utilization</div>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <div>Allocated: <strong id="vRepBgtAlloc"></strong> ETB</div>
                                <div class="fs-5 fw-bold text-success" id="vRepBgtUsed"></div>
                            </div>
                            <div class="small text-muted mt-1" id="vRepBgtPercent"></div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <h6 class="fw-bold text-secondary text-uppercase small">Performance Summary</h6>
                    <div class="p-3 bg-light rounded border text-dark" id="vRepSummary"></div>
                </div>

                <div class="mb-4">
                    <h6 class="fw-bold text-secondary text-uppercase small text-danger">Challenges & Constraints Encountered</h6>
                    <div class="p-3 bg-light rounded border text-dark" id="vRepChallenges"></div>
                </div>

                <form method="POST" id="reportDecisionForm">
                    <input type="hidden" name="report_id" id="vRepId">
                    <input type="hidden" name="report_action" id="vRepAction">

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Director Verification Notes for HQ</label>
                        <textarea name="director_notes" class="form-control" rows="2" placeholder="Add verified remarks or endorsement notes for Headquarter..."></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-outline-danger" onclick="submitReportDecision('rollback_reporter')">
                            <i class="bi bi-arrow-return-left me-1"></i> Roll Back to Reporter
                        </button>
                        <button type="button" class="btn btn-primary" onclick="submitReportDecision('forward_hq')">
                            <i class="bi bi-send-check-fill me-1"></i> Verify & Forward to HQ
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Open Plan Inspection Modal
    function openPlanModal(plan) {
        document.getElementById('vPlanId').value = plan.id;
        document.getElementById('vPlanTitle').innerText = plan.title;
        document.getElementById('vPlanPeriod').innerText = plan.fiscal_year + ' (' + plan.period + ')';
        document.getElementById('vPlanDesc').innerText = plan.description || 'No detailed narrative provided.';
        document.getElementById('vPlanKpi').innerText = plan.kpi_name || 'General Targets';
        document.getElementById('vPlanTarget').innerText = Number(plan.target_value || 0).toLocaleString();
        document.getElementById('vPlanUnit').innerText = plan.unit_of_measure || 'Units';
        document.getElementById('vPlanBudget').innerText = Number(plan.planned_revenue || 0).toLocaleString() + ' ETB';

        new bootstrap.Modal(document.getElementById('viewPlanModal')).show();
    }

    function submitPlanDecision(action) {
        document.getElementById('vPlanAction').value = action;
        const confirmMsg = (action === 'approve') ?
            'Approve this subcity plan and open it for performance reporting?' :
            'Roll back this plan to the planner for revisions?';
        if (confirm(confirmMsg)) {
            document.getElementById('planDecisionForm').submit();
        }
    }

    // Open Report Inspection Modal
    function openReportModal(report) {
        document.getElementById('vRepId').value = report.id;
        document.getElementById('vRepTitle').innerText = report.report_title + ' (' + report.period + ')';
        document.getElementById('vRepPlan').innerText = report.plan_title;

        const target = Number(report.target_value || 1);
        const achieved = Number(report.achieved_value || 0);
        const percent = Math.min(Math.round((achieved / target) * 100), 100);

        document.getElementById('vRepTgt').innerText = target.toLocaleString() + ' ' + (report.unit_of_measure || '');
        document.getElementById('vRepAch').innerText = achieved.toLocaleString() + ' ' + (report.unit_of_measure || '') + ' (' + percent + '%)';
        document.getElementById('vRepProg').style.width = percent + '%';

        const bgtAlloc = Number(report.planned_revenue || 0);
        const bgtUsed = Number(report.earned_revenue || 0);
        document.getElementById('vRepBgtAlloc').innerText = bgtAlloc.toLocaleString();
        document.getElementById('vRepBgtUsed').innerText = bgtUsed.toLocaleString() + ' ETB';
        document.getElementById('vRepBgtPercent').innerText = bgtAlloc > 0 ?
            'Utilized ' + Math.round((bgtUsed / bgtAlloc) * 100) + '% of allocated budget' :
            'Self-funded / No initial budget';

        document.getElementById('vRepSummary').innerText = report.general_performance || 'No summary provided.';
        document.getElementById('vRepChallenges').innerText = report.challenges_faced || 'No challenges reported.';

        new bootstrap.Modal(document.getElementById('viewReportModal')).show();
    }

    function submitReportDecision(action) {
        document.getElementById('vRepAction').value = action;
        const confirmMsg = (action === 'forward_hq') ?
            'Verify this report and forward it directly to Headquarter (HQ)?' :
            'Roll back this report to the reporter for corrections?';
        if (confirm(confirmMsg)) {
            document.getElementById('reportDecisionForm').submit();
        }
    }
</script>

<?php require_once '../includes/footer.php'; ?>