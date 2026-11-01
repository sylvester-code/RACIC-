<?php
// activities/report.php - Submit or Edit Post-Activity Evaluation Report
$pageTitle = 'Submit Activity Report';
require_once __DIR__ . '/../includes/header.php';
requireRole(['super_admin', 'head_pastor', 'church_admin', 'dept_leader', 'church_worker']);

$db = getDB();
$user = currentUser();
$activityId = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];

// Fetch activity
$stmt = $db->prepare("
    SELECT a.*, d.name as department_name 
    FROM activities a 
    LEFT JOIN departments d ON d.id = a.department_id 
    WHERE a.id = ?
");
$stmt->execute([$activityId]);
$activity = $stmt->fetch();

if (!$activity) {
    flash('danger', 'Activity not found.');
    header('Location: ' . BASE_URL . 'activities/index.php');
    exit;
}

// Fetch existing report
$repStmt = $db->prepare("SELECT * FROM activity_reports WHERE activity_id = ?");
$repStmt->execute([$activityId]);
$report = $repStmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $errors[] = "Invalid CSRF security token.";
    } else {
        $actualAttendance = (int)($_POST['actual_attendance'] ?? 0);
        $expenditure = (float)($_POST['expenditure'] ?? 0);
        $achievements = trim($_POST['achievements'] ?? '');
        $challenges = trim($_POST['challenges'] ?? '');
        $recommendations = trim($_POST['recommendations'] ?? '');
        $status = $_POST['status'] ?? 'Submitted';

        if ($actualAttendance < 0) $errors[] = "Actual attendance cannot be negative.";
        if (empty($achievements)) $errors[] = "Key achievements & highlights are required.";

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                if ($report) {
                    // Update
                    $upd = $db->prepare("
                        UPDATE activity_reports 
                        SET actual_attendance = ?, expenditure = ?, achievements = ?,
                            challenges = ?, recommendations = ?, status = ?, submitted_at = NOW()
                        WHERE id = ?
                    ");
                    $upd->execute([
                        $actualAttendance, $expenditure, $achievements,
                        $challenges, $recommendations, $status, $report['id']
                    ]);
                    $reportId = $report['id'];
                } else {
                    // Insert
                    $ins = $db->prepare("
                        INSERT INTO activity_reports (
                            activity_id, actual_attendance, expenditure, achievements,
                            challenges, recommendations, status, submitted_by_user_id, submitted_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $ins->execute([
                        $activityId, $actualAttendance, $expenditure, $achievements,
                        $challenges, $recommendations, $status, $user['id']
                    ]);
                    $reportId = (int)$db->lastInsertId();
                }

                // Automatically ensure activity status is 'Completed'
                $actUpd = $db->prepare("UPDATE activities SET status = 'Completed' WHERE id = ?");
                $actUpd->execute([$activityId]);

                // Also notify Head Pastor / Super Admin
                $leadUsers = $db->query("SELECT id FROM users WHERE role_id IN (1, 2)")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($leadUsers as $leadId) {
                    createNotification(
                        $leadId,
                        "Activity Report Filed: {$activity['activity_code']}",
                        "{$user['full_name']} filed a report for '{$activity['title']}' with {$actualAttendance} in attendance.",
                        "activities/view.php?id={$activityId}#report"
                    );
                }

                $db->commit();
                logAudit('SUBMIT_REPORT', 'Activities', $reportId, "Submitted activity report for {$activity['activity_code']}.");
                flash('success', "Post-activity report for '{$activity['title']}' has been submitted successfully!");
                header("Location: " . BASE_URL . "activities/view.php?id={$activityId}#report");
                exit;

            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold text-dark mb-0"><i class="fas fa-file-contract text-warning me-2"></i>Post-Activity Report</h4>
                <small class="text-muted">Document actual attendance, financial expenditures, ministry outcomes, and recommendations.</small>
            </div>
            <a href="<?= BASE_URL ?>activities/view.php?id=<?= $activity['id'] ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Cancel & Return
            </a>
        </div>

        <!-- Activity Overview Banner -->
        <div class="alert alert-primary shadow-sm mb-4">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center">
                <div>
                    <span class="badge bg-primary me-2"><?= e($activity['activity_code']) ?></span>
                    <strong class="fs-6"><?= e($activity['title']) ?></strong>
                    <div class="small mt-1 text-primary-emphasis">
                        <i class="fas fa-sitemap me-1"></i><?= e($activity['department_name']) ?> &bull; 
                        <i class="far fa-calendar-alt me-1 ms-2"></i><?= formatDate($activity['activity_date']) ?> &bull; 
                        <i class="fas fa-map-marker-alt me-1 ms-2"></i><?= e($activity['venue']) ?>
                    </div>
                </div>
                <div class="mt-2 mt-sm-0 text-sm-end">
                    <span class="badge bg-light text-dark border">Target: <?= (int)$activity['expected_attendance'] ?> people</span>
                    <span class="badge bg-light text-dark border">Budget: <?= formatCurrency($activity['budget']) ?></span>
                </div>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger shadow-sm">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="church-card">
            <div class="card-header">
                <span><i class="fas fa-pen text-primary me-2"></i>Report Submission Form</span>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="">
                    <?= csrfField(); ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Actual Attendance (Headcount) <span class="text-danger">*</span></label>
                            <input type="number" name="actual_attendance" class="form-control form-control-lg" min="0" placeholder="e.g., 280" value="<?= e($_POST['actual_attendance'] ?? ($report['actual_attendance'] ?? '')) ?>" required>
                            <small class="text-muted">Target was: <strong><?= (int)$activity['expected_attendance'] ?></strong></small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Actual Expenditure (<?= CURRENCY_SYMBOL ?>) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="expenditure" class="form-control form-control-lg" min="0" placeholder="0.00" value="<?= e($_POST['expenditure'] ?? ($report['expenditure'] ?? '')) ?>" required>
                            <small class="text-muted">Allocated budget was: <strong><?= formatCurrency($activity['budget']) ?></strong></small>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small">Key Achievements & Highlights <span class="text-danger">*</span></label>
                            <textarea name="achievements" class="form-control" rows="4" placeholder="Detail souls saved, attendance milestones, spiritual breakthrough, guest speaker ministry, etc..." required><?= e($_POST['achievements'] ?? ($report['achievements'] ?? '')) ?></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small">Challenges & Bottlenecks</label>
                            <textarea name="challenges" class="form-control" rows="3" placeholder="Detail any logistics, equipment, power, weather, timing or financial challenges..."><?= e($_POST['challenges'] ?? ($report['challenges'] ?? '')) ?></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small">Recommendations for Future Activities</label>
                            <textarea name="recommendations" class="form-control" rows="3" placeholder="Provide constructive advice for subsequent events (e.g., sound improvements, early announcements)..."><?= e($_POST['recommendations'] ?? ($report['recommendations'] ?? '')) ?></textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Report Status</label>
                            <select name="status" class="form-select">
                                <option value="Submitted" <?= (($_POST['status'] ?? ($report['status'] ?? 'Submitted')) === 'Submitted') ? 'selected' : '' ?>>Submit for Pastoral Approval</option>
                                <option value="Draft" <?= (($_POST['status'] ?? ($report['status'] ?? '')) === 'Draft') ? 'selected' : '' ?>>Save as Draft</option>
                            </select>
                        </div>
                    </div>

                    <div class="border-top mt-4 pt-3 d-flex justify-content-end gap-2">
                        <a href="<?= BASE_URL ?>activities/view.php?id=<?= $activity['id'] ?>" class="btn btn-light border">Cancel</a>
                        <button type="submit" class="btn btn-success px-4 shadow-sm">
                            <i class="fas fa-check-circle me-1"></i>Submit Evaluation Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

