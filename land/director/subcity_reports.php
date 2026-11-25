<?php
// director/subcity_reports.php
require_once '../includes/auth_check.php';
check_auth(['subcity_director']);

$director_loc_id = $_SESSION['location_id'];
$msg = '';

// Handle Forward to HQ
if (isset($_POST['forward_report_id'])) {
    $report_id = (int)$_POST['forward_report_id'];
    $stmt = $pdo->prepare("
        UPDATE reports 
        SET status = 'forwarded_to_hq', 
            forwarded_to_hq_at = NOW(), 
            subcity_director_notes = :notes 
        WHERE id = :id AND location_id = :loc_id
    ");
    $stmt->execute([
        'notes' => trim($_POST['notes'] ?? ''),
        'id' => $report_id,
        'loc_id' => $director_loc_id
    ]);
    $msg = "Report successfully reviewed and forwarded to Headquarter (HQ)!";
}

// Fetch Reports in this subcity
$stmt = $pdo->prepare("
    SELECT r.*, p.title AS plan_title, u.fullname AS reporter_name 
    FROM reports r 
    JOIN plans p ON r.plan_id = p.id 
    JOIN users u ON r.reported_by = u.id 
    WHERE r.location_id = :loc_id 
    ORDER BY r.created_at DESC
");
$stmt->execute(['loc_id' => $director_loc_id]);
$reports = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">Subcity Performance Reports</h3>
        <p class="text-secondary small mb-0">Review reports from subcity reporters and forward verified achievements to HQ.</p>
    </div>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="card card-custom">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Report & Linked Plan</th>
                        <th>Period</th>
                        <th>Reporter</th>
                        <th>Status</th>
                        <th class="text-end pe-4">HQ Submission</th>
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
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark"><i class="bi bi-clock"></i> Pending Subcity Review</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <?php if ($r['status'] !== 'forwarded_to_hq'): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="forward_report_id" value="<?= $r['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('Forward this verified report to Headquarter?')">
                                            <i class="bi bi-send-fill me-1"></i> Send to HQ
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge bg-light text-success border"><i class="bi bi-check-circle"></i> Sent to HQ</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>