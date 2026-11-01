<?php
// activities/create.php - Schedule a New Church Activity
$pageTitle = 'Schedule Activity';
require_once __DIR__ . '/../includes/header.php';
requireRole(['super_admin', 'head_pastor', 'church_admin', 'dept_leader']);

$db = getDB();
$user = currentUser();
$errors = [];

// Fetch departments
$departments = $db->query("SELECT id, name, code FROM departments WHERE status = 'active' ORDER BY name ASC")->fetchAll();

// Fetch church members/users for assignment
$users = $db->query("SELECT id, full_name, email FROM users WHERE status = 'active' ORDER BY full_name ASC")->fetchAll();
$members = $db->query("SELECT id, member_code, first_name, last_name FROM members WHERE membership_status = 'Active' ORDER BY first_name ASC")->fetchAll();

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
        $assignedUsers = $_POST['assigned_users'] ?? [];

        if (empty($title)) $errors[] = "Activity title is required.";
        if (empty($departmentId)) $errors[] = "Please select a responsible department.";
        if (empty($activityDate)) $errors[] = "Activity date is required.";
        if (empty($startTime) || empty($endTime)) $errors[] = "Starting and ending times are required.";
        if (empty($venue)) $errors[] = "Venue is required.";

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                $activityCode = generateCode('RAC-ACT', 'activities', 'activity_code');

                $stmt = $db->prepare("
                    INSERT INTO activities (
                        activity_code, title, description, department_id, activity_date,
                        start_time, end_time, venue, expected_attendance, budget, status, created_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $activityCode, $title, $description, $departmentId, $activityDate,
                    $startTime, $endTime, $venue, $expectedAttendance, $budget, $status, $user['id']
                ]);
                $activityId = (int)$db->lastInsertId();

                // Assign users/leaders
                if (!empty($assignedUsers) && is_array($assignedUsers)) {
                    $assignStmt = $db->prepare("INSERT INTO activity_assignments (activity_id, user_id, role_description) VALUES (?, ?, 'Assigned Leader/Worker')");
                    foreach ($assignedUsers as $uid) {
                        $uid = (int)$uid;
                        if ($uid > 0) {
                            $assignStmt->execute([$activityId, $uid]);
                            createNotification($uid, "New Activity Assignment", "You have been assigned to: {$title} on " . formatDate($activityDate), "activities/view.php?id={$activityId}");
                        }
                    }
                }

                // Add to church calendar events
                $eventStmt = $db->prepare("
                    INSERT INTO events (title, event_type, department_id, start_datetime, end_datetime, venue, description, color, created_by, created_at)
                    VALUES (?, 'Departmental Activity', ?, ?, ?, ?, ?, '#0f2c59', ?, NOW())
                ");
                $startDt = $activityDate . ' ' . $startTime;
                $endDt = $activityDate . ' ' . $endTime;
                $eventStmt->execute([$title, $departmentId, $startDt, $endDt, $venue, $description, $user['id']]);

                $db->commit();
                logAudit('CREATE_ACTIVITY', 'Activities', $activityId, "Created activity '{$title}' ({$activityCode})");
                flash('success', "Activity '{$title}' ({$activityCode}) successfully scheduled!");
                header("Location: " . BASE_URL . "activities/view.php?id={$activityId}");
                exit;

            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = "Failed to schedule activity: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold text-dark mb-0"><i class="fas fa-calendar-plus text-primary me-2"></i>Schedule Church Activity</h4>
                <small class="text-muted">Enter activity details, assign leadership, and set goals.</small>
            </div>
            <a href="<?= BASE_URL ?>activities/index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Back to Activities
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
            <div class="card-header">
                <span><i class="fas fa-info-circle text-primary me-2"></i>Activity Information</span>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="">
                    <?= csrfField(); ?>

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold small">Activity Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" placeholder="e.g., Youth Mega Worship Night & Evangelism Rally" value="<?= e($_POST['title'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Responsible Department <span class="text-danger">*</span></label>
                            <select name="department_id" class="form-select" required>
                                <option value="">Select Department...</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['id'] ?>" <?= (isset($_POST['department_id']) && $_POST['department_id'] == $d['id']) ? 'selected' : '' ?>>
                                        <?= e($d['name']) ?> (<?= e($d['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small">Description / Objective</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Outline the purpose, target outcome, and agenda of this program..."><?= e($_POST['description'] ?? '') ?></textarea>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Activity Date <span class="text-danger">*</span></label>
                            <input type="date" name="activity_date" class="form-control" value="<?= e($_POST['activity_date'] ?? date('Y-m-d')) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Start Time <span class="text-danger">*</span></label>
                            <input type="time" name="start_time" class="form-control" value="<?= e($_POST['start_time'] ?? '18:00') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">End Time <span class="text-danger">*</span></label>
                            <input type="time" name="end_time" class="form-control" value="<?= e($_POST['end_time'] ?? '20:30') ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Venue / Location <span class="text-danger">*</span></label>
                            <input type="text" name="venue" class="form-control" placeholder="e.g., RACIC Main Sanctuary, Forecourt, or Community Park" value="<?= e($_POST['venue'] ?? 'RACIC Main Sanctuary') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Expected Attendance</label>
                            <input type="number" name="expected_attendance" class="form-control" min="0" placeholder="e.g., 200" value="<?= e($_POST['expected_attendance'] ?? '150') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Budget (<?= CURRENCY_SYMBOL ?>)</label>
                            <input type="number" step="0.01" name="budget" class="form-control" min="0" placeholder="0.00" value="<?= e($_POST['budget'] ?? '1000.00') ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Initial Status</label>
                            <select name="status" class="form-select">
                                <option value="Upcoming" selected>Upcoming</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Completed">Completed</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Assigned Leadership / Workers</label>
                            <select name="assigned_users[]" class="form-select" multiple size="4">
                                <?php foreach ($users as $u): ?>
                                    <option value="<?= $u['id'] ?>">
                                        <?= e($u['full_name']) ?> (<?= e($u['email']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Hold Ctrl/Cmd to select multiple leaders or workers.</small>
                        </div>
                    </div>

                    <div class="border-top mt-4 pt-3 d-flex justify-content-end gap-2">
                        <a href="<?= BASE_URL ?>activities/index.php" class="btn btn-light border">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-check-circle me-1"></i>Schedule Activity
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

