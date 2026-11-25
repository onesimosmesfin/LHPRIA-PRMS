<?php
// reporter/create_report.php
require_once '../includes/auth_check.php';
check_auth(['reporter', 'admin', 'subcity_director', 'hq_director']);

$loc_id = $_SESSION['location_id'] ?? 2;
$loc_name = $_SESSION['location_name'] ?? 'Subcity';
$msg = '';
$err = '';

// Fiscal Quarter & Month Mapping with Ethiopian Calendar Equivalents
$quarterMonths = [
    'Q1' => [
        'July' => 'July (ሐምሌ)',
        'August' => 'August (ነሐሴ)',
        'September' => 'September (መስከረም)'
    ],
    'Q2' => [
        'October' => 'October (ጥቅምት)',
        'November' => 'November (ኅዳር)',
        'December' => 'December (ታኅሣሥ)'
    ],
    'Q3' => [
        'January' => 'January (ጥር)',
        'February' => 'February (የካቲት)',
        'March' => 'March (መጋቢት)'
    ],
    'Q4' => [
        'April' => 'April (ሚያዝያ)',
        'May' => 'May (ግንቦት)',
        'June' => 'June (ሰኔ)'
    ],
    'Annual' => [
        'H1' => '1ኛ መንፈቅ (H1)',
        'Q3' => '3ኛ ሩብ (Q3)',
        'Q4' => '4ኛ ሩብ (Q4)'
    ]
];

// Handle Sample Plan Creation for Testing
if (isset($_POST['create_sample_plan'])) {
    $stmt = $pdo->prepare("
        INSERT INTO plans
        (title, fiscal_year, period, location_id, created_by, description, status)
        VALUES (?, '2019 E.C.', 'Q2', ?, ?, 'የይዞታ ማረጋገጥና አገልግሎት እቅድ', 'approved')
    ");
    $stmt->execute([
        'የይዞታ ማረጋገጥ የተቀናጀ እቅድ 2019 (Q2)',
        $loc_id,
        $_SESSION['user_id']
    ]);
    $p_id = $pdo->lastInsertId();

    $stmt_k = $pdo->prepare("
        INSERT INTO plan_items
        (plan_id, kpi_name, target_value, unit_of_measure, planned_revenue)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt_k->execute([$p_id, 'ይዞታ ማረጋገጥ - 1. የቅድመ ዝግጅት ስራዎችን', 200, 'ይዞታ / ካርታ', 50000]);
    $stmt_k->execute([$p_id, 'ይዞታ ማረጋገጥ - 6. የመስክ ወሰን ማካለል ስራ መስራት', 500, 'ይዞታ / ካርታ', 150000]);

    $msg = "Sample Approved Plan with multiple activities created successfully!";
}

// Handle Report Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_report'])) {
    $plan_id = (int)($_POST['plan_id'] ?? 0);
    $report_title = trim($_POST['report_title'] ?? '');
    $quarter = $_POST['quarter'] ?? '';
    $month = $_POST['month'] ?? '';
    $week = $_POST['week'] ?? '';
    $achieved_arr = $_POST['achieved'] ?? [];
    $budget_arr = $_POST['earned_revenue'] ?? [];
    $perf = trim($_POST['general_performance'] ?? '');
    $challenges = trim($_POST['challenges_faced'] ?? '');

    // Validations
    if (!isset($quarterMonths[$quarter])) {
        $err = "Please select a valid quarter.";
    } elseif (!array_key_exists($month, $quarterMonths[$quarter])) {
        $err = "Please select a valid month for the selected quarter.";
    } elseif (!in_array($week, ['Week 1', 'Week 2', 'Week 3', 'Week 4'], true)) {
        $err = "Please select a valid week.";
    } elseif ($plan_id <= 0 || empty($report_title)) {
        $err = "Please select an approved plan and enter a report title.";
    } elseif (empty($achieved_arr)) {
        $err = "Please report progress on at least one activity.";
    } else {
        // Formatted Period: e.g. Q2 - November - Week 2
        $period = $quarter . ' - ' . $month . ' - ' . $week;

        // Verify that plan exists and is approved
        $stmt_check = $pdo->prepare("SELECT id FROM plans WHERE id = ? AND status = 'approved'");
        $stmt_check->execute([$plan_id]);

        if (!$stmt_check->fetch()) {
            $err = "The selected plan is not approved or does not exist.";
        } else {
            // Insert Master Report
            $stmt = $pdo->prepare("
                INSERT INTO reports
                (plan_id, location_id, reported_by, report_title, period,
                 general_performance, challenges_faced, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'submitted_to_subcity')
            ");
            $stmt->execute([
                $plan_id,
                $loc_id,
                $_SESSION['user_id'],
                $report_title,
                $period,
                $perf,
                $challenges
            ]);
            $report_id = $pdo->lastInsertId();

            // Insert each activity's performance into report_items
            $stmt_item = $pdo->prepare("
                INSERT INTO report_items
                (report_id, plan_item_id, achieved_value, earned_revenue)
                VALUES (?, ?, ?, ?)
            ");

            foreach ($achieved_arr as $plan_item_id => $achieved_val) {
                $ach = (float)$achieved_val;
                $bgt = (float)($budget_arr[$plan_item_id] ?? 0);
                $stmt_item->execute([$report_id, (int)$plan_item_id, $ach, $bgt]);
            }

            header("Location: submit_report.php?msg=" . urlencode("Report submitted successfully to Subcity Director!"));
            exit;
        }
    }
}

// Fetch Approved Plans for this subcity
$stmt = $pdo->prepare("
    SELECT p.* 
    FROM plans p 
    WHERE p.location_id = ? AND p.status = 'approved' 
    ORDER BY p.created_at DESC
");
$stmt->execute([$loc_id]);
$approved_plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fallback to all approved plans if admin or testing
if (empty($approved_plans)) {
    $approved_plans = $pdo->query("
        SELECT p.* 
        FROM plans p 
        WHERE p.status = 'approved' 
        ORDER BY p.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch all plan_items grouped by plan_id
$plan_ids = array_column($approved_plans, 'id');
$items_by_plan = [];

if (!empty($plan_ids)) {
    $placeholders = implode(',', array_fill(0, count($plan_ids), '?'));
    $stmt_items = $pdo->prepare("SELECT * FROM plan_items WHERE plan_id IN ($placeholders)");
    $stmt_items->execute($plan_ids);
    $all_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    foreach ($all_items as $item) {
        $items_by_plan[$item['plan_id']][] = $item;
    }
}

foreach ($approved_plans as &$p) {
    $p['items'] = $items_by_plan[$p['id']] ?? [];
}
unset($p);

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">Create Progress Report</h3>
        <p class="text-secondary small mb-0">
            Report weekly/monthly achievements and budget utilization against approved plans for <?= htmlspecialchars($loc_name) ?>.
        </p>
    </div>
    <a href="submit_report.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> View All Reports
    </a>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= htmlspecialchars($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($err): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= htmlspecialchars($err) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (empty($approved_plans)): ?>
    <div class="alert alert-warning py-3 rounded-3 mb-4">
        <strong><i class="bi bi-info-circle-fill me-2"></i>No Approved Plans Found for <?= htmlspecialchars($loc_name) ?>!</strong>
        <div class="small mt-1 text-muted">
            Reports must be tied to an approved plan. You can automatically create a test approved plan using the button below:
        </div>
        <form method="POST" class="mt-3">
            <button type="submit" name="create_sample_plan" class="btn btn-warning btn-sm fw-bold">
                <i class="bi bi-lightning-charge-fill me-1"></i> Auto-Create Sample Plan
            </button>
        </form>
    </div>
<?php endif; ?>

<div class="card card-custom p-4" style="max-width: 950px; margin: 0 auto;">
    <form method="POST" id="createReportForm">

        <!-- Step 1: Select Approved Plan -->
        <div class="mb-3">
            <label class="form-label fw-bold text-primary">
                1. Select Approved Subcity Plan <span class="text-danger">*</span>
            </label>
            <select name="plan_id" id="planSelect" class="form-select form-select-lg fs-6" required onchange="handlePlanSelection()" <?= empty($approved_plans) ? 'disabled' : '' ?>>
                <option value="">-- Choose an Approved Plan --</option>
                <?php foreach ($approved_plans as $p): ?>
                    <option value="<?= (int)$p['id'] ?>">
                        <?= htmlspecialchars($p['title']) ?> (<?= htmlspecialchars($p['fiscal_year']) ?> - <?= htmlspecialchars($p['period']) ?>) [<?= count($p['items']) ?> Activities]
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Step 2: Reporting Period Selection (Quarter, Month, Week) -->
        <div class="card border-primary border-opacity-25 bg-light p-3 mb-4">
            <h6 class="fw-bold text-primary mb-3">
                <i class="bi bi-calendar3 me-2"></i>2. Reporting Period
            </h6>
            <div class="row g-3">
                <!-- Quarter (Auto-locks to Plan's period) -->
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Quarter <span class="text-danger">*</span></label>
                    <select name="quarter" id="quarter" class="form-select" required onchange="handleQuarterChange()" <?= empty($approved_plans) ? 'disabled' : '' ?>>
                        <option value="">-- Select Quarter --</option>
                        <option value="Q1">Q1 (July - September / ሐምሌ - መስከረም)</option>
                        <option value="Q2" selected>Q2 (October - December / ጥቅምት - ታኅሣሥ)</option>
                        <option value="Q3">Q3 (January - March / ጥር - መጋቢት)</option>
                        <option value="Q4">Q4 (April - June / ሚያዝያ - ሰኔ)</option>
                        <option value="Annual">Annual (ዓመታዊ)</option>
                    </select>
                </div>

                <!-- Month (Populates dynamically based on Quarter) -->
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Month <span class="text-danger">*</span></label>
                    <select name="month" id="month" class="form-select" required onchange="autoSuggestTitle()" <?= empty($approved_plans) ? 'disabled' : '' ?>>
                        <option value="">-- Select Month --</option>
                    </select>
                </div>

                <!-- Week -->
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Week <span class="text-danger">*</span></label>
                    <select name="week" id="week" class="form-select" required onchange="autoSuggestTitle()" <?= empty($approved_plans) ? 'disabled' : '' ?>>
                        <option value="">-- Select Week --</option>
                        <option value="Week 1">Week 1 (ሳምንት 1)</option>
                        <option value="Week 2" selected>Week 2 (ሳምንት 2)</option>
                        <option value="Week 3">Week 3 (ሳምንት 3)</option>
                        <option value="Week 4">Week 4 (ሳምንት 4)</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Step 3: Report Title -->
        <div class="mb-4">
            <label class="form-label fw-semibold">
                Report Title <span class="text-danger">*</span>
            </label>
            <input type="text" name="report_title" id="reportTitle" class="form-control"
                placeholder="e.g. Land Regularization - Q2 November Week 2 Report"
                required <?= empty($approved_plans) ? 'disabled' : '' ?>>
        </div>

        <!-- Step 4: Multi-Activity Reporting Table (Populated dynamically) -->
        <div class="card p-3 mb-4 border-top border-primary border-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-ui-checks-grid text-primary me-2"></i>3. Record Performance Against Planned Activities
                </h6>
                <span class="badge bg-light text-secondary border" id="actCountBadge">0 Activities</span>
            </div>
            <p class="text-muted small mb-3">Enter the actual revenue earned and achievment for planned activity::</p>

            <div id="activitiesReportingContainer" class="d-flex flex-column gap-3">
                <div class="text-center py-4 text-muted border rounded bg-light" id="emptyPrompt">
                    <i class="bi bi-arrow-up-circle fs-3 text-secondary d-block mb-2"></i>
                    Please select an approved plan above to load its planned activities.
                </div>
            </div>

            <!-- Summary Totals Bar -->
            <div class="p-3 bg-light rounded border d-flex justify-content-between align-items-center mt-3" id="totalsSummaryBar" style="display:none !important;">
                <div>
                    <span class="text-muted small">Total Achieved Quantity: </span>
                    <strong class="text-primary fs-6" id="totalAchievedQty">0</strong>
                </div>
                <div>
                    <span class="text-muted small">የተሰበሰበ ገቢ (Actual Collected Revenue) </span>
                    <strong class="text-success fs-6" id="totalSpentBudget">0.00 ETB</strong>
                </div>
            </div>
        </div>

        <!-- Step 5: Challenges & Blockers -->
        <div class="mb-3">
            <label class="form-label fw-semibold">
                Challenges & Constraints Encountered
            </label>
            <textarea name="challenges_faced" class="form-control" rows="2"
                placeholder="Describe any boundary disputes, system downtime, file shortages, or bottlenecks..."
                <?= empty($approved_plans) ? 'disabled' : '' ?>></textarea>
        </div>

        <!-- Step 6: Executive Summary -->
        <div class="mb-4">
            <label class="form-label fw-semibold">
                Executive Performance Summary
            </label>
            <textarea name="general_performance" class="form-control" rows="2"
                placeholder="Brief summary of key achievements and milestones accomplished during this period..."
                <?= empty($approved_plans) ? 'disabled' : '' ?>></textarea>
        </div>

        <!-- Submit Buttons -->
        <div class="d-flex justify-content-end gap-2">
            <a href="submit_report.php" class="btn btn-light px-4">Cancel</a>
            <button type="submit" name="save_report" class="btn btn-primary px-4 fw-semibold"
                <?= empty($approved_plans) ? 'disabled' : '' ?>>
                <i class="bi bi-send-fill me-1"></i> Submit Report to Director
            </button>
        </div>

    </form>
</div>

<!-- ==================== JAVASCRIPT: SYNCHRONIZATION WITH PLANS ==================== -->
<script>
    // JSON payload of approved plans with all their items from plans.php
    const approvedPlansData = <?= json_encode($approved_plans, JSON_UNESCAPED_UNICODE) ?>;

    const quarterMonthsData = {
        Q1: [{
                val: 'July',
                label: 'July (ሐምሌ)'
            },
            {
                val: 'August',
                label: 'August (ነሐሴ)'
            },
            {
                val: 'September',
                label: 'September (መስከረም)'
            }
        ],
        Q2: [{
                val: 'October',
                label: 'October (ጥቅምት)'
            },
            {
                val: 'November',
                label: 'November (ኅዳር)'
            },
            {
                val: 'December',
                label: 'December (ታኅሣሥ)'
            }
        ],
        Q3: [{
                val: 'January',
                label: 'January (ጥር)'
            },
            {
                val: 'February',
                label: 'February (የካቲት)'
            },
            {
                val: 'March',
                label: 'March (መጋቢት)'
            }
        ],
        Q4: [{
                val: 'April',
                label: 'April (ሚያዝያ)'
            },
            {
                val: 'May',
                label: 'May (ግንቦት)'
            },
            {
                val: 'June',
                label: 'June (ሰኔ)'
            }
        ],
        Annual: [{
                val: 'H1',
                label: '1ኛ መንፈቅ (H1)'
            },
            {
                val: 'Q3',
                label: '3ኛ ሩብ (Q3)'
            },
            {
                val: 'Q4',
                label: '4ኛ ሩብ (Q4)'
            }
        ]
    };

    // When Quarter Changes, update the Month options
    function handleQuarterChange() {
        const qSelect = document.getElementById('quarter');
        const mSelect = document.getElementById('month');
        const selectedQ = qSelect.value;

        mSelect.innerHTML = '<option value="">-- Select Month --</option>';

        if (quarterMonthsData[selectedQ]) {
            quarterMonthsData[selectedQ].forEach((item, idx) => {
                const opt = document.createElement('option');
                opt.value = item.val;
                opt.textContent = item.label;
                if (idx === 1) opt.selected = true; // Default to middle month (e.g. November)
                mSelect.appendChild(opt);
            });
        }
        autoSuggestTitle();
    }

    // When a Plan is Selected: Sync Quarter, load activities, render rows
    function handlePlanSelection() {
        const planSelect = document.getElementById('planSelect');
        const planId = parseInt(planSelect.value);
        const container = document.getElementById('activitiesReportingContainer');
        const emptyPrompt = document.getElementById('emptyPrompt');
        const totalsBar = document.getElementById('totalsSummaryBar');
        const actBadge = document.getElementById('actCountBadge');

        const selectedPlan = approvedPlansData.find(p => p.id == planId);

        if (selectedPlan) {
            // 1. Sync Quarter with the plan's period (e.g. Q1, Q2, Q3, Q4)
            const qSelect = document.getElementById('quarter');
            if (selectedPlan.period && quarterMonthsData[selectedPlan.period]) {
                qSelect.value = selectedPlan.period;
            }
            handleQuarterChange();

            // 2. Render all activities planned in this plan
            container.innerHTML = '';
            const items = selectedPlan.items || [];
            actBadge.innerText = items.length + ' Activities Planned';

            if (items.length > 0) {
                totalsBar.style.setProperty('display', 'flex', 'important');

                items.forEach((item, index) => {
                    const card = document.createElement('div');
                    card.className = 'card p-3 border shadow-sm bg-white';
                    card.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-light text-primary border py-1 px-2 fw-bold">
                            <i class="bi bi-tag-fill me-1"></i>ተግባር #${index + 1}: ${item.kpi_name}
                        </span>
                        <div class="small">
                            Target Baseline: <strong class="text-primary">${Number(item.target_value).toLocaleString()} ${item.unit_of_measure}</strong> 
                            | Budget: <strong class="text-success">${Number(item.planned_revenue).toLocaleString()} ETB</strong>
                        </div>
                    </div>
                    <div class="row g-2 align-items-center">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-secondary mb-1">
                                Achieved Quantity (${item.unit_of_measure}) <span class="text-danger">*</span>
                            </label>
                            <input type="number" step="0.01" min="0" name="achieved[${item.id}]" class="form-control form-control-sm achieved-input" 
                                   data-target="${item.target_value}" placeholder="e.g. 150" required oninput="calcActivityProgress(this)">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-secondary mb-1">
                                Budget Utilized (ETB) <span class="text-danger">*</span>
                            </label>
                            <input type="number" step="0.01" min="0" name="earned_revenue[${item.id}]" class="form-control form-control-sm budget-input" 
                                   placeholder="e.g. 45000" required oninput="calcTotals()">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-secondary mb-1">Progress vs Target</label>
                            <div class="d-flex align-items-center mt-1">
                                <div class="progress flex-grow-1 me-2" style="height: 10px;">
                                    <div class="progress-bar bg-primary progress-indicator" style="width: 0%"></div>
                                </div>
                                <span class="fw-bold small progress-label text-primary">0%</span>
                            </div>
                        </div>
                    </div>
                `;
                    container.appendChild(card);
                });
            } else {
                container.innerHTML = `
                <div class="alert alert-info py-3 text-center mb-0">
                    No specific sub-activities found under this plan. General progress will be recorded.
                </div>
            `;
                totalsBar.style.setProperty('display', 'none', 'important');
            }
        } else {
            container.innerHTML = `
            <div class="text-center py-4 text-muted border rounded bg-light" id="emptyPrompt">
                <i class="bi bi-arrow-up-circle fs-3 text-secondary d-block mb-2"></i>
                Please select an approved plan above to load its planned activities.
            </div>
        `;
            totalsBar.style.setProperty('display', 'none', 'important');
            actBadge.innerText = '0 Activities';
        }
        autoSuggestTitle();
    }

    // Calculate individual activity progress percentage
    function calcActivityProgress(input) {
        const card = input.closest('.card');
        const target = parseFloat(input.getAttribute('data-target')) || 1;
        const achieved = parseFloat(input.value) || 0;
        const percent = Math.min(Math.round((achieved / target) * 100), 100);

        const bar = card.querySelector('.progress-indicator');
        const lbl = card.querySelector('.progress-label');

        bar.style.width = percent + '%';
        lbl.innerText = percent + '%';

        if (percent >= 90) bar.className = 'progress-bar bg-success progress-indicator';
        else if (percent >= 50) bar.className = 'progress-bar bg-primary progress-indicator';
        else bar.className = 'progress-bar bg-warning progress-indicator';

        calcTotals();
    }

    // Calculate overall summary totals
    function calcTotals() {
        const achievedInputs = document.querySelectorAll('.achieved-input');
        const budgetInputs = document.querySelectorAll('.budget-input');

        let totalAch = 0;
        let totalSpent = 0;

        achievedInputs.forEach(inp => totalAch += parseFloat(inp.value) || 0);
        budgetInputs.forEach(inp => totalSpent += parseFloat(inp.value) || 0);

        document.getElementById('totalAchievedQty').innerText = totalAch.toLocaleString();
        document.getElementById('totalSpentBudget').innerText = Number(totalSpent).toLocaleString() + ' ETB';
    }

    // Auto suggest report title
    function autoSuggestTitle() {
        const planSelect = document.getElementById('planSelect');
        const planId = planSelect.value;
        const quarter = document.getElementById('quarter').value;
        const month = document.getElementById('month').value;
        const week = document.getElementById('week').value;
        const titleInput = document.getElementById('reportTitle');

        const selectedPlan = approvedPlansData.find(p => p.id == planId);

        if (selectedPlan) {
            let suffix = '';
            if (quarter) suffix += ' ' + quarter;
            if (month) suffix += ' ' + month;
            if (week) suffix += ' ' + week;

            titleInput.value = selectedPlan.title + ' -' + suffix + ' Report';
        }
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        handleQuarterChange();
        if (approvedPlansData.length > 0) {
            document.getElementById('planSelect').selectedIndex = 1;
            handlePlanSelection();
        }
    });
</script>

<?php require_once '../includes/footer.php'; ?>