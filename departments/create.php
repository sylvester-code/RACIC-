<?php
// departments/create.php - Create New Church Department
$pageTitle = 'Create Department';
require_once __DIR__ . '/../includes/header.php';
requireRole(['super_admin', 'head_pastor', 'church_admin']);

$db = getDB();
$user = currentUser();
$errors = [];

$users = $db->query("SELECT id, full_name, email FROM users WHERE status = 'active' ORDER BY full_name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $errors[] = "Invalid CSRF security token.";
    } else {
        $name = trim($_POST['name'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $description = trim($_POST['description'] ?? '');
        $leaderId = !empty($_POST['leader_id']) ? (int)$_POST['leader_id'] : null;
        $meetingSchedule = trim($_POST['meeting_schedule'] ?? '');
        $budget = (float)($_POST['budget_allocation'] ?? 0);
        $status = $_POST['status'] ?? 'active';

        if (empty($name)) $errors[] = "Department name is required.";
        if (empty($code)) $errors[] = "Department short code is required.";

        // Unique check
        $dup = $db->prepare("SELECT id FROM departments WHERE name = ? OR code = ?");
        $dup->execute([$name, $code]);
        if ($dup->fetch()) {
            $errors[] = "A department with this name or code already exists.";
        }

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                $stmt = $db->prepare("
                    INSERT INTO departments (
                        name, code, description, leader_id, meeting_schedule,
                        budget_allocation, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $name, $code, $description, $leaderId, $meetingSchedule,
                    $budget, $status
                ]);
                $deptId = (int)$db->lastInsertId();

                // If leader assigned, update user's department_id
                if ($leaderId) {
                    $uUpd = $db->prepare("UPDATE users SET department_id = ? WHERE id = ?");
                    $uUpd->execute([$deptId, $leaderId]);
                    createNotification($leaderId, "Department Leadership Appointment", "You have been assigned as the leader of the {$name} department.", "departments/view.php?id={$deptId}");
                }

                $db->commit();
                logAudit('CREATE_DEPARTMENT', 'Departments', $deptId, "Created department '{$name}' ({$code})");
                flash('success', "Department '{$name}' created successfully!");
                header("Location: " . BASE_URL . "departments/view.php?id={$deptId}");
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
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold text-dark mb-0"><i class="fas fa-plus-circle text-primary me-2"></i>Create Church Department</h4>
                <small class="text-muted">Establish a ministry, designate leadership, and set meeting schedules.</small>
            </div>
            <a href="<?= BASE_URL ?>departments/index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Back
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
                            <label class="form-label fw-bold small">Department Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Children's Ministry, Youth Ministry" value="<?= e($_POST['name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Short Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control text-uppercase" placeholder="e.g. CHM, YTM" maxlength="10" value="<?= e($_POST['code'] ?? '') ?>" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small">Department Mission & Purpose</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Outline the spiritual mission and operational objectives of this ministry..."><?= e($_POST['description'] ?? '') ?></textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Department Leader</label>
                            <select name="leader_id" class="form-select">
                                <option value="">Select Leader...</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= (isset($_POST['leader_id']) && $_POST['leader_id'] == $u['id']) ? 'selected' : '' ?>>
                                        <?= e($u['full_name']) ?> (<?= e($u['email']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Meeting Schedule / Rehearsals</label>
                            <input type="text" name="meeting_schedule" class="form-control" placeholder="e.g., Thursdays 6:00 PM & Sundays 7:30 AM" value="<?= e($_POST['meeting_schedule'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Annual Budget Allocation (<?= CURRENCY_SYMBOL ?>)</label>
                            <input type="number" step="0.01" name="budget_allocation" class="form-control" placeholder="0.00" value="<?= e($_POST['budget_allocation'] ?? '5000.00') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" selected>Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="border-top mt-4 pt-3 d-flex justify-content-end gap-2">
                        <a href="<?= BASE_URL ?>departments/index.php" class="btn btn-light border">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-check-circle me-1"></i>Create Department
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

