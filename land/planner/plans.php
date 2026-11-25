<?php
// planner/plans.php
require_once '../includes/auth_check.php';
check_auth(['planner', 'admin']);

$loc_id = $_SESSION['location_id'] ?? 2;
$loc_name = $_SESSION['location_name'] ?? 'Subcity';
$msg = '';
$err = '';

// 1. Handle Plan Creation with Multiple Activities
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_plan'])) {
    $title = trim($_POST['title'] ?? '');
    $fiscal_year = trim($_POST['fiscal_year'] ?? '2019 E.C.');
    $period = $_POST['period'] ?? 'Q1';
    $desc = trim($_POST['description'] ?? '');
    $domain = trim($_POST['domain'] ?? '');

    $activities = $_POST['activities'] ?? [];
    $targets = $_POST['target_values'] ?? [];
    $units = $_POST['units'] ?? [];
    $budgets = $_POST['budgets'] ?? [];

    if (!empty($title) && !empty($domain) && !empty($activities)) {
        // Insert Parent Plan
        $stmt = $pdo->prepare("
            INSERT INTO plans (title, fiscal_year, period, location_id, created_by, description, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'submitted')
        ");
        $stmt->execute([$title, $fiscal_year, $period, $loc_id, $_SESSION['user_id'], $desc]);
        $plan_id = $pdo->lastInsertId();

        // Insert Multiple Activities into plan_items
        $stmt_kpi = $pdo->prepare("
            INSERT INTO plan_items (plan_id, kpi_name, target_value, unit_of_measure, planned_revenue) 
            VALUES (?, ?, ?, ?, ?)
        ");

        for ($i = 0; $i < count($activities); $i++) {
            $act_name = trim($activities[$i]);
            if (!empty($act_name)) {
                $kpi_name = $domain . ' - ' . $act_name;
                $tgt = (float)($targets[$i] ?? 0);
                $u = trim($units[$i] ?? 'Units');
                $bgt = (float)($budgets[$i] ?? 0);
                $stmt_kpi->execute([$plan_id, $kpi_name, $tgt, $u, $bgt]);
            }
        }

        $msg = "Plan with " . count($activities) . " activities submitted to Subcity Director!";
    } else {
        $err = "Please select a domain, provide a title, and add at least one activity.";
    }
}

// 2. Handle Plan Modification (Allowed if status != 'approved')
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_plan'])) {
    $plan_id = (int)$_POST['edit_plan_id'];
    $title = trim($_POST['title'] ?? '');
    $fiscal_year = trim($_POST['fiscal_year'] ?? '2019 E.C.');
    $period = $_POST['period'] ?? 'Q1';
    $desc = trim($_POST['description'] ?? '');
    $domain = trim($_POST['domain'] ?? '');

    $activities = $_POST['activities'] ?? [];
    $targets = $_POST['target_values'] ?? [];
    $units = $_POST['units'] ?? [];
    $budgets = $_POST['budgets'] ?? [];

    $stmt_check = $pdo->prepare("SELECT status FROM plans WHERE id = ? AND location_id = ?");
    $stmt_check->execute([$plan_id, $loc_id]);
    $existing = $stmt_check->fetch();

    if ($existing && $existing['status'] !== 'approved') {
        // Update Parent Plan
        $stmt_up = $pdo->prepare("
            UPDATE plans 
            SET title = ?, fiscal_year = ?, period = ?, description = ?, status = 'submitted' 
            WHERE id = ? AND location_id = ?
        ");
        $stmt_up->execute([$title, $fiscal_year, $period, $desc, $plan_id, $loc_id]);

        // Replace all plan_items
        $pdo->prepare("DELETE FROM plan_items WHERE plan_id = ?")->execute([$plan_id]);

        $stmt_kpi = $pdo->prepare("
            INSERT INTO plan_items (plan_id, kpi_name, target_value, unit_of_measure, planned_revenue) 
            VALUES (?, ?, ?, ?, ?)
        ");

        for ($i = 0; $i < count($activities); $i++) {
            $act_name = trim($activities[$i]);
            if (!empty($act_name)) {
                $kpi_name = $domain . ' - ' . $act_name;
                $tgt = (float)($targets[$i] ?? 0);
                $u = trim($units[$i] ?? 'Units');
                $bgt = (float)($budgets[$i] ?? 0);
                $stmt_kpi->execute([$plan_id, $kpi_name, $tgt, $u, $bgt]);
            }
        }

        $msg = "Plan updated and re-submitted to Subcity Director!";
    } else {
        $err = "Approved plans are locked and cannot be edited.";
    }
}

// Fetch all plans for this subcity
$stmt = $pdo->prepare("SELECT * FROM plans WHERE location_id = ? ORDER BY created_at DESC");
$stmt->execute([$loc_id]);
$plans = $stmt->fetchAll();

// Fetch all activities grouped by plan_id
$stmt_items = $pdo->prepare("
    SELECT pi.* 
    FROM plan_items pi 
    JOIN plans p ON pi.plan_id = p.id 
    WHERE p.location_id = ?
");
$stmt_items->execute([$loc_id]);
$all_items = $stmt_items->fetchAll();

$items_by_plan = [];
foreach ($all_items as $item) {
    $items_by_plan[$item['plan_id']][] = $item;
}

// Attach items to each plan
foreach ($plans as &$p) {
    $p['items'] = $items_by_plan[$p['id']] ?? [];
    $p['total_budget'] = array_sum(array_column($p['items'], 'planned_revenue'));
    $p['total_target'] = array_sum(array_column($p['items'], 'target_value'));
}
unset($p);

require_once '../includes/header.php';
?>

<style>
    .activity-card {
        border-left: 4px solid #0284c7;
        background-color: #f8fafc;
        border-radius: 8px;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">Subcity Strategic Planning</h3>
        <p class="text-secondary small mb-0">
            Define multi-activity plans, monthly targets, and budgets across the 5 official domains for <?= htmlspecialchars($loc_name) ?>.
        </p>
    </div>
    <button class="btn btn-primary btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#newPlanModal" onclick="initNewPlanForm()">
        <i class="bi bi-plus-lg me-1"></i> New Strategic Plan
    </button>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($err) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="card card-custom">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-dark">Subcity Plans Registry (<?= count($plans) ?> Plans)</h6>
        <span class="text-muted small">Plans can contain multiple official activities</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Plan Title & Details</th>
                        <th>Strategic Activities Planned</th>
                        <th>Period</th>
                        <th>Combined Targets & Revenue</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($plans)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No plans recorded yet. Click "+ New Strategic Plan" above.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($plans as $p): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?= htmlspecialchars($p['title']) ?></div>
                                <div class="text-muted small text-truncate" style="max-width: 280px;"><?= htmlspecialchars($p['description']) ?></div>
                                <?php if (!empty($p['director_feedback'])): ?>
                                    <div class="text-danger small mt-1">
                                        <i class="bi bi-chat-left-dots-fill me-1"></i><strong>Feedback:</strong> <?= htmlspecialchars($p['director_feedback']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex flex-column gap-1" style="max-width: 320px;">
                                    <?php if (!empty($p['items'])): ?>
                                        <?php foreach ($p['items'] as $item): ?>
                                            <div class="badge bg-light text-dark border text-start py-1 px-2 text-truncate" title="<?= htmlspecialchars($item['kpi_name']) ?>">
                                                <i class="bi bi-check2 text-primary me-1"></i>
                                                <?= htmlspecialchars($item['kpi_name']) ?>: <strong><?= number_format($item['target_value']) ?> <?= htmlspecialchars($item['unit_of_measure']) ?></strong>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">No specific activities</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?= htmlspecialchars($p['fiscal_year']) ?> (<?= htmlspecialchars($p['period']) ?>)
                                </span>
                            </td>
                            <td>
                                <div class="fw-bold text-primary small"><?= number_format($p['total_target']) ?> Total Items</div>
                                <div class="text-muted small"><?= number_format($p['total_budget']) ?> ETB</div>
                            </td>
                            <td>
                                <?php if ($p['status'] === 'approved'): ?>
                                    <span class="badge bg-success"><i class="bi bi-check2-all me-1"></i> Approved</span>
                                <?php elseif ($p['status'] === 'submitted'): ?>
                                    <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i> Under Review</span>
                                <?php elseif ($p['status'] === 'rolled_back'): ?>
                                    <span class="badge bg-danger"><i class="bi bi-arrow-return-left me-1"></i> Rolled Back</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Draft</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <?php if ($p['status'] !== 'approved'): ?>
                                    <button class="btn btn-sm btn-outline-primary"
                                        onclick="openEditModal(<?= htmlspecialchars(json_encode($p)) ?>)">
                                        <i class="bi bi-pencil-square me-1"></i> Edit Plan
                                    </button>
                                <?php else: ?>
                                    <span class="badge bg-light text-success border py-2 px-3">
                                        <i class="bi bi-lock-fill text-muted me-1"></i> Locked & Active
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ==================== MODAL: CREATE PLAN (MULTI-ACTIVITY) ==================== -->
<div class="modal fade" id="newPlanModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content rounded-4 border-0">
            <form method="POST">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Create Multi-Activity Strategic Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Domain, Title, and Fiscal Period Row -->
                    <div class="row g-3 mb-3 p-3 bg-light rounded border">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-primary">1. የሥራ ዘርፍ / Strategic Domain <span class="text-danger">*</span></label>
                            <select name="domain" id="newDomain" class="form-select" required onchange="handleDomainChange('new', this.value)">
                                <option value="">-- ምድብ ምረጥ --</option>
                                <option value="ይዞታ ማረጋገጥ" selected>1. ይዞታ ማረጋገጥ (Title Certification & Regularization)</option>
                                <option value="አገልግሎት">2. አገልግሎት (Public Service Delivery)</option>
                                <option value="ዲጂታላይዜሽን">3. ዲጂታላይዜሽን (Digitalization & Archiving)</option>
                                <option value="ኢ-ላንድ">4. ኢ-ላንድ (e-Land Cadastre System)</option>
                                <option value="ገቢ">5. ገቢ (Revenue & Lease Collection)</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold">Plan Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="newTitle" class="form-control" value="የይዞታ ማረጋገጥ የተቀናጀ እቅድ 2019 (Q2)" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-primary">Fiscal Period (Quarter) <span class="text-danger">*</span></label>
                            <select name="period" id="newPeriod" class="form-select" onchange="onPeriodChanged('new')">
                                <option value="Q1">Q1 (ሐምሌ - መስከረም)</option>
                                <option value="Q2" selected>Q2 (ጥቅምት - ታኅሣሥ)</option>
                                <option value="Q3">Q3 (ጥር - መጋቢት)</option>
                                <option value="Q4">Q4 (ሚያዝያ - ሰኔ)</option>
                                <option value="Annual">Annual (ዓመታዊ እቅድ)</option>
                            </select>
                            <input type="hidden" name="fiscal_year" id="newFiscalYear" value="2019 E.C.">
                        </div>
                    </div>

                    <!-- Multiple Activities Section -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold mb-0 text-dark">
                                <i class="bi bi-list-task text-primary me-2"></i>የተግባራት ዝርዝርና ኢላማዎች (Planned Activities under this Domain)
                            </h6>
                            <button type="button" class="btn btn-outline-primary btn-sm fw-bold" onclick="addActivityRow('new')">
                                <i class="bi bi-plus-circle me-1"></i> ተጨማሪ ተግባር ጨምር (Add Activity)
                            </button>
                        </div>

                        <!-- Dynamic Container for Activities -->
                        <div id="newActivitiesContainer" class="d-flex flex-column gap-3">
                            <!-- Populated dynamically via JS -->
                        </div>
                    </div>

                    <!-- Total Summary Bar -->
                    <div class="p-3 bg-light rounded border d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted small">ጠቅላላ የተመደበ በጀት (Combined Budget across all activities):</span>
                        <strong class="text-success fs-5" id="newTotalBudget">0.00 ETB</strong>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Plan Description & Strategic Goals</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Brief outline of operations and execution milestones..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_plan" class="btn btn-primary px-4 fw-semibold">
                        <i class="bi bi-send-fill me-1"></i> Submit Plan to Director
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== MODAL: EDIT PLAN (MULTI-ACTIVITY) ==================== -->
<div class="modal fade" id="editPlanModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content rounded-4 border-0">
            <form method="POST">
                <input type="hidden" name="update_plan" value="1">
                <input type="hidden" name="edit_plan_id" id="editPlanId">

                <div class="modal-header border-0 pb-0">
                    <div>
                        <h5 class="modal-title fw-bold">Edit Subcity Strategic Plan</h5>
                        <div class="text-muted small">Update and add/remove activities before director approval</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-4">
                    <!-- Feedback Alert -->
                    <div id="editFeedbackAlert" class="alert alert-danger py-2 px-3 small rounded-3 mb-3" style="display:none;">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong>Director Remarks:</strong> <span id="editFeedbackText"></span>
                    </div>

                    <div class="row g-3 mb-3 p-3 bg-light rounded border">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-primary">Strategic Domain <span class="text-danger">*</span></label>
                            <select name="domain" id="editDomain" class="form-select" required onchange="handleDomainChange('edit', this.value)">
                                <option value="ይዞታ ማረጋገጥ">1. ይዞታ ማረጋገጥ (Title Certification)</option>
                                <option value="አገልግሎት">2. አገልግሎት (Public Service Delivery)</option>
                                <option value="ዲጂታላይዜሽን">3. ዲጂታላይዜሽን (Digitalization)</option>
                                <option value="ኢ-ላንድ">4. ኢ-ላንድ (e-Land System)</option>
                                <option value="ገቢ">5. ገቢ (Revenue Collection)</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold">Plan Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="editTitle" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-primary">Fiscal Period (Quarter) <span class="text-danger">*</span></label>
                            <select name="period" id="editPeriod" class="form-select" onchange="onPeriodChanged('edit')">
                                <option value="Q1">Q1 (ሐምሌ - መስከረም)</option>
                                <option value="Q2">Q2 (ጥቅምት - ታኅሣሥ)</option>
                                <option value="Q3">Q3 (ጥር - መጋቢት)</option>
                                <option value="Q4">Q4 (ሚያዝያ - ሰኔ)</option>
                                <option value="Annual">Annual (ዓመታዊ እቅድ)</option>
                            </select>
                            <input type="hidden" name="fiscal_year" id="editFiscalYear" value="2019 E.C.">
                        </div>
                    </div>

                    <!-- Multiple Activities Section -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold mb-0 text-dark">
                                <i class="bi bi-list-task text-primary me-2"></i>Planned Activities
                            </h6>
                            <button type="button" class="btn btn-outline-primary btn-sm fw-bold" onclick="addActivityRow('edit')">
                                <i class="bi bi-plus-circle me-1"></i> ተጨማሪ ተግባር ጨምር (Add Activity)
                            </button>
                        </div>

                        <div id="editActivitiesContainer" class="d-flex flex-column gap-3">
                            <!-- Populated dynamically via JS -->
                        </div>
                    </div>

                    <div class="p-3 bg-light rounded border d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted small">ለመሰበሰብ የታቀደ ባጄት (Combined Budget):</span>
                        <strong class="text-success fs-5" id="editTotalBudget">0.00 ETB</strong>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Plan Description</label>
                        <textarea name="description" id="editDesc" class="form-control" rows="2"></textarea>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold">
                        <i class="bi bi-send-check me-1"></i> Save Changes & Resubmit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== JAVASCRIPT: DYNAMIC PERIOD MONTHS & ACTIVITIES ==================== -->
<script>
    // Official Bureau Activities Mapping
    const domainActivitiesMap = {
        'ይዞታ ማረጋገጥ': [
            '1. የቅድመ ዝግጅት ስራዎችን',
            '2. የግንዛቤ ፈጠራ ስራዎች',
            '3. የህዝብ ታዛቢና ቅሬታ ሰሚ ማስመረጥ',
            '4. ይረጋገጥልኝ ጥያቄ መቀበል',
            '5. የማህደራትን ኮፒ ከመብት ፈጣሪው ተቋም መቀበል',
            '6. የመስክ ወሰን ማካለል ስራ መስራት',
            '7. የመስክ ቅየሳ ስራ መስራት',
            '8. የይዞታዎችን ማህደር ማደራጀት',
            '10. የተረጋገጡ ማህደረሠት',
            '11. ብቁ የሆኑ የግል ይዞታዎች',
            '12. ብቁ የሆኑ የመንግስት ይዞታዎች',
            '13. ሌላ'
        ],
        'አገልግሎት': [
            'ስም ዝውውር',
            'የባንክ እዳ ምዝገባና ስረዛ',
            'የፍርድ ቤት እዳ እገዳ',
            'የካርታ ቅጅ መስጠት',
            'መረጃ ወቅታዊ ማድረግ',
            'ለፍትህ ተቋማት ምላሽ መስጠት',
            'ይካፈልልኝ',
            'ይካተትልኝ',
            'ግብር ተመን',
            'ወሰን ችካል',
            'አገልግሎት ለውጥ',
            'ምትክ ካርታ መስጠት',
            'ሌላ'
        ],
        'ዲጂታላይዜሽን': [
            '1. ጂኦሪፈረንስ የተደረጉ ይዞታዎች ብዛት',
            '2. ማይግሬት የተደረጉ ይዞታዎች ብዛት',
            '3. ስካን የተደረጉ ማህደረሠት ብዛት',
            '4. የተሳሰሩ ይዞታዎች ብዛት',
            '5. ተረጋግጠው የተመዘገቡ ይዞታዎች ብዛት'
        ],
        'ኢ-ላንድ': [
            'ኮንደሚኒየም',
            'ሪልስቴት',
            'አፓርታማ',
            'ይዞታ'
        ],
        'ገቢ': [
            '1. መደበኛ ገቢ',
            '2. ትረስት ፈንድ'
        ]
    };

    // Fiscal Period (Quarter) to 3-Months Mapping
    const quarterMonthsMap = {
        'Q1': ['ወር 1 (ሐምሌ / July)', 'ወር 2 (ነሐሴ / August)', 'ወር 3 (መስከረም / September)'],
        'Q2': ['ወር 1 (ጥቅምት / October)', 'ወር 2 (ኅዳር / November)', 'ወር 3 (ታኅሣሥ / December)'],
        'Q3': ['ወር 1 (ጥር / January)', 'ወር 2 (የካቲት / February)', 'ወር 3 (መጋቢት / March)'],
        'Q4': ['ወር 1 (ሚያዝያ / April)', 'ወር 2 (ግንቦት / May)', 'ወር 3 (ሰኔ / June)'],
        'Annual': ['1ኛ መንፈቅ (H1)', '3ኛ ሩብ (Q3)', '4ኛ ሩብ (Q4)']
    };

    // Default units per domain
    function getDefaultUnit(domain) {
        if (domain === 'ገቢ') return 'ብር (ETB)';
        if (domain === 'ይዞታ ማረጋገጥ') return 'ይዞታ / ካርታ';
        if (domain === 'ዲጂታላይዜሽን') return 'ዶክመንት / ፋይል';
        if (domain === 'አገልግሎት') return 'ተገልጋይ (Clients)';
        if (domain === 'ኢ-ላንድ') return 'ትራንዛክሽን';
        return 'Units';
    }

    // Event listener: Triggered when Fiscal Period changes
    function onPeriodChanged(mode) {
        const periodSelect = document.getElementById(mode + 'Period');
        const selectedQ = periodSelect ? periodSelect.value : 'Q2';
        const months = quarterMonthsMap[selectedQ] || quarterMonthsMap['Q2'];

        // Update month labels in every active activity row in the container
        const container = document.getElementById(mode + 'ActivitiesContainer');
        if (container) {
            const rows = container.querySelectorAll('.activity-card');
            rows.forEach(row => {
                const lbl1 = row.querySelector('.month-lbl-1');
                const lbl2 = row.querySelector('.month-lbl-2');
                const lbl3 = row.querySelector('.month-lbl-3');
                if (lbl1) lbl1.innerText = months[0];
                if (lbl2) lbl2.innerText = months[1];
                if (lbl3) lbl3.innerText = months[2];
            });
        }

        // Auto update title with the new quarter
        const domainSelect = document.getElementById(mode + 'Domain');
        const titleInput = document.getElementById(mode + 'Title');
        if (titleInput && domainSelect && domainSelect.value) {
            const fy = document.getElementById(mode + 'FiscalYear') ? document.getElementById(mode + 'FiscalYear').value : '2019 E.C.';
            titleInput.value = 'የ' + domainSelect.value + ' የተቀናጀ እቅድ ' + fy + ' (' + selectedQ + ')';
        }
    }

    // Add Activity Row with dynamic month labels based on the currently selected period
    function addActivityRow(mode, data = null) {
        const container = document.getElementById(mode + 'ActivitiesContainer');
        const domain = document.getElementById(mode + 'Domain').value || 'ይዞታ ማረጋገጥ';
        const activities = domainActivitiesMap[domain] || [];
        const defaultUnit = getDefaultUnit(domain);

        // Fetch active period month labels
        const periodSelect = document.getElementById(mode + 'Period');
        const currentQ = periodSelect ? periodSelect.value : 'Q2';
        const monthLabels = quarterMonthsMap[currentQ] || quarterMonthsMap['Q2'];

        const rowId = 'act_row_' + Math.random().toString(36).substr(2, 9);
        const row = document.createElement('div');
        row.className = 'activity-card card p-3 border shadow-sm';
        row.id = rowId;

        let optionsHtml = '<option value="">-- የተግባር ዝርዝር ይምረጡ --</option>';
        activities.forEach(act => {
            const isSel = (data && data.activity === act) ? 'selected' : '';
            optionsHtml += `<option value="${act}" ${isSel}>${act}</option>`;
        });

        const targetVal = data ? data.target : '';
        const unitVal = data ? data.unit : defaultUnit;
        const budgetVal = data ? data.budget : '';

        row.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="badge bg-primary px-2 py-1"><i class="bi bi-tag-fill me-1"></i>የተግባር መደብ</span>
            <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2" onclick="removeActivityRow('${rowId}', '${mode}')" title="ተግባር አስወግድ">
                <i class="bi bi-trash3-fill"></i> አስወግድ
            </button>
        </div>
        <div class="mb-2">
            <label class="form-label small fw-bold text-secondary">የተግባር ዓይነት / Activity <span class="text-danger">*</span></label>
            <select name="activities[]" class="form-select form-select-sm" required>
                ${optionsHtml}
            </select>
        </div>
        <div class="row g-2 mb-2 p-2 bg-white rounded border">
            <div class="col-3">
                <label class="small text-muted month-lbl-1" style="font-size:0.75rem;">${monthLabels[0]}</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm m1-input" placeholder="0" oninput="calcMonthlySum(this)">
            </div>
            <div class="col-3">
                <label class="small text-muted month-lbl-2" style="font-size:0.75rem;">${monthLabels[1]}</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm m2-input" placeholder="0" oninput="calcMonthlySum(this)">
            </div>
            <div class="col-3">
                <label class="small text-muted month-lbl-3" style="font-size:0.75rem;">${monthLabels[2]}</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm m3-input" placeholder="0" oninput="calcMonthlySum(this)">
            </div>
            <div class="col-3">
                <label class="small fw-bold text-primary" style="font-size:0.75rem;">ድምር ዒላማ *</label>
                <input type="number" step="0.01" min="0" name="target_values[]" class="form-control form-control-sm target-input fw-bold bg-light" value="${targetVal}" placeholder="0" required>
            </div>
        </div>
        <div class="row g-2">
            <div class="col-md-6">
                <label class="small fw-semibold text-secondary">መለኪያ (Unit)</label>
                <input type="text" name="units[]" class="form-control form-control-sm" value="${unitVal}" required>
            </div>
            <div class="col-md-6">
                <label class="small fw-semibold text-secondary">የተመደበ በጀት (ETB) <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0" name="budgets[]" class="form-control form-control-sm budget-input" value="${budgetVal}" placeholder="0.00" oninput="calcTotalBudget('${mode}')" required>
            </div>
        </div>
    `;

        container.appendChild(row);
        calcTotalBudget(mode);
    }

    // Remove Activity Row
    function removeActivityRow(rowId, mode) {
        const row = document.getElementById(rowId);
        if (row) {
            row.remove();
            calcTotalBudget(mode);
        }
    }

    // Sum 3 months for that specific row
    function calcMonthlySum(input) {
        const card = input.closest('.activity-card');
        const m1 = parseFloat(card.querySelector('.m1-input').value) || 0;
        const m2 = parseFloat(card.querySelector('.m2-input').value) || 0;
        const m3 = parseFloat(card.querySelector('.m3-input').value) || 0;
        const total = m1 + m2 + m3;
        if (total > 0) {
            card.querySelector('.target-input').value = total.toFixed(2);
        }
    }

    // Calculate combined budget across all rows
    function calcTotalBudget(mode) {
        const container = document.getElementById(mode + 'ActivitiesContainer');
        const budgetInputs = container.querySelectorAll('.budget-input');
        let total = 0;
        budgetInputs.forEach(inp => {
            total += parseFloat(inp.value) || 0;
        });
        document.getElementById(mode + 'TotalBudget').innerText = Number(total).toLocaleString() + ' ETB';
    }

    // When Domain dropdown changes: update title & re-populate activity rows
    function handleDomainChange(mode, domain) {
        const titleInput = document.getElementById(mode + 'Title');
        const periodSelect = document.getElementById(mode + 'Period');
        const qVal = periodSelect ? periodSelect.value : 'Q2';

        if (titleInput) {
            titleInput.value = 'የ' + domain + ' የተቀናጀ እቅድ 2019 (' + qVal + ')';
        }

        const container = document.getElementById(mode + 'ActivitiesContainer');
        const selects = container.querySelectorAll('select[name="activities[]"]');
        const unitInputs = container.querySelectorAll('input[name="units[]"]');
        const activities = domainActivitiesMap[domain] || [];
        const defaultUnit = getDefaultUnit(domain);

        selects.forEach(sel => {
            sel.innerHTML = '<option value="">-- የተግባር ዝርዝር ይምረጡ --</option>';
            activities.forEach(act => {
                const opt = document.createElement('option');
                opt.value = act;
                opt.textContent = act;
                sel.appendChild(opt);
            });
        });

        unitInputs.forEach(inp => {
            inp.value = defaultUnit;
        });
    }

    // Initialize New Plan Form
    function initNewPlanForm() {
        const container = document.getElementById('newActivitiesContainer');
        container.innerHTML = '';
        addActivityRow('new');
        onPeriodChanged('new');
    }

    // Open Edit Modal with all multiple activities pre-filled
    function openEditModal(plan) {
        document.getElementById('editPlanId').value = plan.id;
        document.getElementById('editTitle').value = plan.title;
        document.getElementById('editFiscalYear').value = plan.fiscal_year;
        document.getElementById('editPeriod').value = plan.period;
        document.getElementById('editDesc').value = plan.description || '';

        let domain = 'ይዞታ ማረጋገጥ';
        if (plan.items && plan.items.length > 0) {
            const firstKpi = plan.items[0].kpi_name || '';
            if (firstKpi.includes(' - ')) {
                domain = firstKpi.split(' - ')[0];
            }
        }
        document.getElementById('editDomain').value = domain;

        // Feedback alert
        const feedbackAlert = document.getElementById('editFeedbackAlert');
        const feedbackText = document.getElementById('editFeedbackText');
        if (plan.status === 'rolled_back' && plan.director_feedback) {
            feedbackText.innerText = plan.director_feedback;
            feedbackAlert.style.display = 'block';
        } else {
            feedbackAlert.style.display = 'none';
        }

        // Populate multiple activities
        const container = document.getElementById('editActivitiesContainer');
        container.innerHTML = '';

        if (plan.items && plan.items.length > 0) {
            plan.items.forEach(item => {
                let actName = item.kpi_name;
                if (actName.includes(' - ')) {
                    actName = actName.split(' - ').slice(1).join(' - ');
                }
                addActivityRow('edit', {
                    activity: actName,
                    target: item.target_value,
                    unit: item.unit_of_measure,
                    budget: item.planned_revenue
                });
            });
        } else {
            addActivityRow('edit');
        }

        // Update month labels according to plan's period
        onPeriodChanged('edit');

        new bootstrap.Modal(document.getElementById('editPlanModal')).show();
    }

    // Auto init on page load
    document.addEventListener('DOMContentLoaded', function() {
        initNewPlanForm();
    });
</script>

<?php require_once '../includes/footer.php'; ?>