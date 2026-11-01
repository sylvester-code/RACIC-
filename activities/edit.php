<?php
// activities/edit.php - Edit an Existing Church Activity
$pageTitle = 'Edit Activity';
require_once __DIR__ . '/../includes/header.php';
requireRole(['super_admin', 'head_pastor', 'church_admin', 'dept_leader']);

$db = getDB();
$user = currentUser();
$id = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];

$stmt = $db->prepare("SELECT * FROM activities WHERE id = ?");
$stmt->execute([$id]);
$activity = $stmt->fetch();

if (!$activity) {
    flash('danger', 'Activity not found.');
    header('Location: ' . BASE_URL . 'activities/index.php');
    exit;
}

$departments = $db->query("SELECT id, name, code FROM departments WHERE status = 'active' ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $errors[] = "Invalid CSRF security token.";
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $departmentId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $activityDate = trim($_POST['activity_date'] ?? '');
        $startTime = trim($_POST['start_time'] ?? '');
        $endTime = trim($_POST['end_time'] ?? '');
        $venue = trim($_POST['venue'] ?? '');
        $expectedAttendance = (int)($_POST['expected_attendance'] ?? 0);
        $budget = (float)($_POST['budget'] ?? 0);
        $status = $_POST['status'] ?? 'Upcoming';

        if (empty($title)) $errors[] = "Activity title is required.";
        if (empty($departmentId)) $errors[] = "Please select a responsible department.";
        if (empty($activityDate)) $errors[] = "Activity date is required.";

        if (empty($errors)) {
            try {
                $upd = $db->prepare("
                    UPDATE activities 
                    SET title = ?, description = ?, department_id = ?, activity_date = ?,
                        start_time = ?, end_time = ?, venue = ?, expected_attendance = ?,
                        budget = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $upd->execute([
                    $title, $description, $departmentId, $activityDate,
                    $startTime, $endTime, $venue, $expectedAttendance,
                    $budget, $status, $id
                ]);

                logAudit('UPDATE_ACTIVITY', 'Activities', $id, "Updated activity details for {$activity['activity_code']}.");
                flash('success', "Activity '{$title}' successfully updated!");
                header("Location: " . BASE_URL . "activities/view.php?id={$id}");
                exit;
            } catch (Exception $e) {
                $errors[] = "Error updating activity: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold text-dark mb-0"><i class="fas fa-edit text-primary me-2"></i>Edit Activity: <?= e($activity['activity_code']) ?></h4>
                <small class="text-muted">Update schedules, venue, budget, or event status.</small>
            </div>
            <a href="<?= BASE_URL ?>activities/view.php?id=<?= $activity['id'] ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Cancel
            </a>
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
            <div class="card-body p-4">
                <form method="POST" action="">
                    <?= csrfField(); ?>

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold small">Activity Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" value="<?= e($_POST['title'] ?? $activity['title']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Responsible Department <span class="text-danger">*</span></label>
                            <select name="department_id" class="form-select" required>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['id'] ?>" <?= (($_POST['department_id'] ?? $activity['department_id']) == $d['id']) ? 'selected' : '' ?>>
                                        <?= e($d['name']) ?> (<?= e($d['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small">Description / Objective</label>
                            <textarea name="description" class="form-control" rows="3"><?= e($_POST['description'] ?? $activity['description']) ?></textarea>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Activity Date <span class="text-danger">*</span></label>
                            <input type="date" name="activity_date" class="form-control" value="<?= e($_POST['activity_date'] ?? $activity['activity_date']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Start Time <span class="text-danger">*</span></label>
                            <input type="time" name="start_time" class="form-control" value="<?= e($_POST['start_time'] ?? $activity['start_time']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">End Time <span class="text-danger">*</span></label>
                            <input type="time" name="end_time" class="form-control" value="<?= e($_POST['end_time'] ?? $activity['end_time']) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Venue <span class="text-danger">*</span></label>
                            <input type="text" name="venue" class="form-control" value="<?= e($_POST['venue'] ?? $activity['venue']) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Expected Attendance</label>
                            <input type="number" name="expected_attendance" class="form-control" value="<?= e($_POST['expected_attendance'] ?? $activity['expected_attendance']) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Budget (<?= CURRENCY_SYMBOL ?>)</label>
                            <input type="number" step="0.01" name="budget" class="form-control" value="<?= e($_POST['budget'] ?? $activity['budget']) ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Activity Status</label>
                            <select name="status" class="form-select">
                                <option value="Upcoming" <?= (($_POST['status'] ?? $activity['status']) === 'Upcoming') ? 'selected' : '' ?>>Upcoming</option>
                                <option value="In Progress" <?= (($_POST['status'] ?? $activity['status']) === 'In Progress') ? 'selected' : '' ?>>In Progress</option>
                                <option value="Completed" <?= (($_POST['status'] ?? $activity['status']) === 'Completed') ? 'selected' : '' ?>>Completed</option>
                                <option value="Postponed" <?= (($_POST['status'] ?? $activity['status']) === 'Postponed') ? 'selected' : '' ?>>Postponed</option>
                                <option value="Cancelled" <?= (($_POST['status'] ?? $activity['status']) === 'Cancelled') ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                    </div>

                    <div class="border-top mt-4 pt-3 d-flex justify-content-end gap-2">
                        <a href="<?= BASE_URL ?>activities/view.php?id=<?= $activity['id'] ?>" class="btn btn-light border">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-1"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

