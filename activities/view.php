<?php
// activities/view.php - Comprehensive Activity Monitoring & Report Details
$pageTitle = 'View Activity Details';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$user = currentUser();
$id = !empty($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    flash('danger', 'Invalid activity ID specified.');
    header('Location: ' . BASE_URL . 'activities/index.php');
    exit;
}

// Fetch activity
$stmt = $db->prepare("
    SELECT a.*, d.name as department_name, d.code as department_code, u.full_name as creator_name
    FROM activities a
    LEFT JOIN departments d ON d.id = a.department_id
    LEFT JOIN users u ON u.id = a.created_by
    WHERE a.id = ?
");
$stmt->execute([$id]);
$activity = $stmt->fetch();

if (!$activity) {
    flash('danger', 'Activity not found.');
    header('Location: ' . BASE_URL . 'activities/index.php');
    exit;
}

// Fetch assigned personnel
$assignStmt = $db->prepare("
    SELECT aa.*, u.full_name, u.email, u.phone, r.display_name as role_display
    FROM activity_assignments aa
    LEFT JOIN users u ON u.id = aa.user_id
    LEFT JOIN roles r ON r.id = u.role_id
    WHERE aa.activity_id = ?
");
$assignStmt->execute([$id]);
$assignments = $assignStmt->fetchAll();

// Fetch report if exists
$reportStmt = $db->prepare("
    SELECT ar.*, u.full_name as submitter_name, rev.full_name as reviewer_name
    FROM activity_reports ar
    LEFT JOIN users u ON u.id = ar.submitted_by_user_id
    LEFT JOIN users rev ON rev.id = ar.reviewed_by_user_id
    WHERE ar.activity_id = ?
");
$reportStmt->execute([$id]);
$report = $reportStmt->fetch();

// Handle pastoral review approval / revision
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_review'])) {
    requireRole(['super_admin', 'head_pastor']);
    if (!validateCSRF()) {
        flash('danger', 'Invalid CSRF token.');
    } else {
        $reviewStatus = $_POST['review_status'] ?? 'Approved';
        $reviewNotes = trim($_POST['review_notes'] ?? '');

        if ($report) {
            $upd = $db->prepare("
                UPDATE activity_reports 
                SET status = ?, review_notes = ?, reviewed_by_user_id = ?, reviewed_at = NOW() 
                WHERE id = ?
            ");
            $upd->execute([$reviewStatus, $reviewNotes, $user['id'], $report['id']]);

            // Notify submitter
            createNotification(
                $report['submitted_by_user_id'],
                "Activity Report {$reviewStatus}",
                "Your activity report for '{$activity['title']}' has been marked as {$reviewStatus}.",
                "activities/view.php?id={$id}#report"
            );

            logAudit('REVIEW_REPORT', 'Activities', $report['id'], "Report for {$activity['activity_code']} reviewed as {$reviewStatus}.");
            flash('success', "Report status successfully updated to '{$reviewStatus}'.");
            header("Location: " . BASE_URL . "activities/view.php?id={$id}#report");
            exit;
        }
    }
}
?>

<!-- Title & Navigation -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge bg-primary fs-6"><?= e($activity['activity_code']) ?></span>
            <h3 class="fw-bold text-dark mb-0"><?= e($activity['title']) ?></h3>
            <?= getStatusBadge($activity['status']) ?>
        </div>
        <p class="text-muted mb-0 small">
            Organized by <strong><?= e($activity['department_name']) ?></strong> &bull; Scheduled by <?= e($activity['creator_name']) ?>
        </p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <a href="<?= BASE_URL ?>activities/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Back
        </a>
        <a href="<?= BASE_URL ?>activities/edit.php?id=<?= $activity['id'] ?>" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-edit me-1"></i>Edit Activity
        </a>
        <?php if ($activity['status'] === 'Completed' || $report): ?>
            <a href="<?= BASE_URL ?>activities/report.php?id=<?= $activity['id'] ?>" class="btn btn-warning btn-sm text-dark">
                <i class="fas fa-file-alt me-1"></i><?= $report ? 'Update Report' : 'Submit Activity Report' ?>
            </a>
        <?php endif; ?>
        <button class="btn btn-outline-dark btn-sm btn-trigger-print">
            <i class="fas fa-print me-1"></i>Print Activity Brief
        </button>
    </div>
</div>

<div class="row g-4">
    <!-- Main Activity Details Column -->
    <div class="col-lg-8">
        <!-- Event Overview Card -->
        <div class="church-card mb-4">
            <div class="card-header">
                <span><i class="fas fa-calendar-day text-primary me-2"></i>Event Logistics & Summary</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Date of Activity</small>
                        <span class="fw-bold text-dark fs-6"><?= formatDate($activity['activity_date'], 'l, F j, Y') ?></span>
                    </div>
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Time Schedule</small>
                        <span class="fw-bold text-dark fs-6">
                            <?= date('h:i A', strtotime($activity['start_time'])) ?> &ndash; <?= date('h:i A', strtotime($activity['end_time'])) ?>
                        </span>
                    </div>
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Venue / Location</small>
                        <span class="fw-bold text-dark fs-6"><i class="fas fa-map-pin text-danger me-1"></i><?= e($activity['venue']) ?></span>
                    </div>
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Responsible Ministry</small>
                        <span class="badge bg-light text-dark border fs-6"><?= e($activity['department_name']) ?></span>
                    </div>
                    <div class="col-12">
                        <small class="text-muted d-block mb-1">Objectives & Description</small>
                        <div class="p-3 bg-light rounded border text-secondary">
                            <?= nl2br(e($activity['description'] ?: 'No detailed objective provided.')) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- POST-ACTIVITY REPORT SECTION -->
        <div class="church-card" id="report">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-file-contract text-success me-2"></i>Post-Activity Completion Report</span>
                <?php if ($report): ?>
                    <?= getStatusBadge($report['status']) ?>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!$report): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-clipboard-list text-muted fs-1 mb-3"></i>
                        <h5>No Activity Report Filed Yet</h5>
                        <p class="text-muted small">
                            After the conclusion of this activity, the responsible department leader must submit an evaluation report containing actual attendance, achievements, challenges, expenditure, and recommendations.
                        </p>
                        <?php if ($activity['status'] === 'Completed'): ?>
                            <a href="<?= BASE_URL ?>activities/report.php?id=<?= $activity['id'] ?>" class="btn btn-warning text-dark px-4 shadow-sm">
                                <i class="fas fa-plus me-1"></i>Submit Activity Report Now
                            </a>
                        <?php else: ?>
                            <div class="alert alert-info py-2 d-inline-block small">
                                <i class="fas fa-info-circle me-1"></i>This activity is currently <strong><?= e($activity['status']) ?></strong>. Once marked as Completed, reports can be filed.
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- Full Report View -->
                    <div class="row g-3 mb-4">
                        <div class="col-sm-6 col-md-3">
                            <div class="p-3 border rounded bg-light text-center">
                                <small class="text-muted d-block">Expected Attendance</small>
                                <span class="fs-4 fw-bold text-dark"><?= number_format($activity['expected_attendance']) ?></span>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="p-3 border rounded bg-light text-center">
                                <small class="text-muted d-block">Actual Attendance</small>
                                <span class="fs-4 fw-bold text-success"><?= number_format($report['actual_attendance']) ?></span>
                                <?php 
                                    $diff = $report['actual_attendance'] - $activity['expected_attendance'];
                                    $diffColor = $diff >= 0 ? 'text-success' : 'text-danger';
                                    $diffSign = $diff >= 0 ? '+' : '';
                                ?>
                                <small class="<?= $diffColor ?> d-block fw-semibold"><?= $diffSign . $diff ?> vs target</small>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="p-3 border rounded bg-light text-center">
                                <small class="text-muted d-block">Allocated Budget</small>
                                <span class="fs-5 fw-bold text-dark"><?= formatCurrency($activity['budget']) ?></span>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="p-3 border rounded bg-light text-center">
                                <small class="text-muted d-block">Total Expenditure</small>
                                <span class="fs-5 fw-bold text-primary"><?= formatCurrency($report['expenditure']) ?></span>
                                <?php 
                                    $finVar = $activity['budget'] - $report['expenditure'];
                                    $finColor = $finVar >= 0 ? 'text-success' : 'text-danger';
                                ?>
                                <small class="<?= $finColor ?> d-block fw-semibold">
                                    <?= $finVar >= 0 ? 'Surplus: ' : 'Deficit: ' ?><?= formatCurrency(abs($finVar)) ?>
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Achievements -->
                    <div class="mb-3">
                        <h6 class="fw-bold text-success mb-2"><i class="fas fa-trophy me-2"></i>Key Achievements & Highlights</h6>
                        <div class="p-3 bg-white border rounded">
                            <?= nl2br(e($report['achievements'])) ?>
                        </div>
                    </div>

                    <!-- Challenges -->
                    <?php if (!empty($report['challenges'])): ?>
                        <div class="mb-3">
                            <h6 class="fw-bold text-danger mb-2"><i class="fas fa-exclamation-triangle me-2"></i>Challenges & Obstacles Encountered</h6>
                            <div class="p-3 bg-white border rounded text-dark">
                                <?= nl2br(e($report['challenges'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Recommendations -->
                    <?php if (!empty($report['recommendations'])): ?>
                        <div class="mb-3">
                            <h6 class="fw-bold text-primary mb-2"><i class="fas fa-lightbulb me-2"></i>Recommendations for Future Programs</h6>
                            <div class="p-3 bg-white border rounded text-dark">
                                <?= nl2br(e($report['recommendations'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between align-items-center text-muted small border-top pt-3">
                        <div>
                            <i class="fas fa-user-edit me-1"></i>Submitted by: <strong><?= e($report['submitter_name']) ?></strong> on <?= formatDateTime($report['submitted_at']) ?>
                        </div>
                        <?php if ($report['reviewed_by_user_id']): ?>
                            <div>
                                <i class="fas fa-check-circle text-success me-1"></i>Reviewed by: <strong><?= e($report['reviewer_name']) ?></strong> on <?= formatDateTime($report['reviewed_at']) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Pastoral Review Decision Box (Super Admin & Head Pastor) -->
                    <?php if (hasRole(['super_admin', 'head_pastor'])): ?>
                        <div class="border-top mt-4 pt-3 bg-light p-3 rounded no-print">
                            <h6 class="fw-bold text-dark mb-2"><i class="fas fa-stamp text-primary me-2"></i>Pastoral Oversight & Decision</h6>
                            <?php if ($report['review_notes']): ?>
                                <div class="alert alert-secondary py-2 small mb-3">
                                    <strong>Previous Review Notes:</strong> <?= e($report['review_notes']) ?>
                                </div>
                            <?php endif; ?>
                            <form method="POST" action="">
                                <?= csrfField(); ?>
                                <input type="hidden" name="action_review" value="1">
                                <div class="row g-2 align-items-center">
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold mb-1">Decision</label>
                                        <select name="review_status" class="form-select form-select-sm">
                                            <option value="Approved" <?= $report['status'] === 'Approved' ? 'selected' : '' ?>>Approve Report</option>
                                            <option value="Needs Revision" <?= $report['status'] === 'Needs Revision' ? 'selected' : '' ?>>Request Revision</option>
                                            <option value="Submitted" <?= $report['status'] === 'Submitted' ? 'selected' : '' ?>>Keep Under Review</option>
                                        </select>
                                    </div>
                                    <div class="col-md-7">
                                        <label class="form-label small fw-bold mb-1">Pastoral Remarks & Commendation</label>
                                        <input type="text" name="review_notes" class="form-control form-control-sm" placeholder="e.g., Commendable turnout. Well executed." value="<?= e($report['review_notes'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-2 mt-auto">
                                        <button type="submit" class="btn btn-sm btn-primary w-100">
                                            <i class="fas fa-save me-1"></i>Save
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Sidebar Column: Assignments & Quick Info -->
    <div class="col-lg-4">
        <!-- Assigned Personnel -->
        <div class="church-card mb-4">
            <div class="card-header">
                <span><i class="fas fa-users-cog text-secondary me-2"></i>Assigned Leaders & Workers</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($assignments)): ?>
                    <div class="p-3 text-center text-muted small">
                        No specific workers assigned to this activity yet.
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($assignments as $asg): ?>
                            <div class="list-group-item p-3">
                                <div class="fw-bold text-dark"><?= e($asg['full_name']) ?></div>
                                <div class="small text-muted"><?= e($asg['role_display'] ?? 'Church Worker') ?> &bull; <?= e($asg['role_description']) ?></div>
                                <?php if ($asg['phone']): ?>
                                    <div class="small text-secondary mt-1"><i class="fas fa-phone me-1"></i><?= e($asg['phone']) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Status Card -->
        <div class="church-card">
            <div class="card-header">
                <span><i class="fas fa-tachometer-alt text-primary me-2"></i>Activity Health Tracker</span>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Attendance Target Met</span>
                        <strong>
                            <?php 
                                $pct = ($activity['expected_attendance'] > 0 && $report) ? round(($report['actual_attendance'] / $activity['expected_attendance']) * 100) : 0;
                                echo $pct . '%';
                            ?>
                        </strong>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-success" style="width: <?= min(100, $pct) ?>%"></div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Budget Consumption</span>
                        <strong>
                            <?php 
                                $bPct = ($activity['budget'] > 0 && $report) ? round(($report['expenditure'] / $activity['budget']) * 100) : 0;
                                echo $bPct . '%';
                            ?>
                        </strong>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar <?= $bPct > 100 ? 'bg-danger' : 'bg-primary' ?>" style="width: <?= min(100, $bPct) ?>%"></div>
                    </div>
                </div>

                <hr>
                <div class="small text-muted">
                    <div><strong>Created:</strong> <?= formatDateTime($activity['created_at']) ?></div>
                    <div><strong>Last Update:</strong> <?= formatDateTime($activity['updated_at']) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

