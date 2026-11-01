<?php
// departments/edit.php - Edit Department Details
$pageTitle = 'Edit Department';
require_once __DIR__ . '/../includes/header.php';
requireRole(['super_admin', 'head_pastor', 'church_admin']);

$db = getDB();
$user = currentUser();
$id = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];

$stmt = $db->prepare("SELECT * FROM departments WHERE id = ?");
$stmt->execute([$id]);
$department = $stmt->fetch();

if (!$department) {
    flash('danger', 'Department not found.');
    header('Location: ' . BASE_URL . 'departments/index.php');
    exit;
}

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

        if (empty($errors)) {
            try {
                $upd = $db->prepare("
                    UPDATE departments 
                    SET name = ?, code = ?, description = ?, leader_id = ?,
                        meeting_schedule = ?, budget_allocation = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $upd->execute([
                    $name, $code, $description, $leaderId,
                    $meetingSchedule, $budget, $status, $id
                ]);

                if ($leaderId) {
                    $uUpd = $db->prepare("UPDATE users SET department_id = ? WHERE id = ?");
                    $uUpd->execute([$id, $leaderId]);
                }

                logAudit('UPDATE_DEPARTMENT', 'Departments', $id, "Updated department {$name} ({$code})");
                flash('success', "Department '{$name}' updated successfully!");
                header("Location: " . BASE_URL . "departments/view.php?id={$id}");
                exit;

            } catch (Exception $e) {
                $errors[] = "Error saving department: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold text-dark mb-0"><i class="fas fa-edit text-primary me-2"></i>Edit Department: <?= e($department['name']) ?></h4>
                <small class="text-muted">Update ministry details, assigned leadership, or budget.</small>
            </div>
            <a href="<?= BASE_URL ?>departments/view.php?id=<?= $department['id'] ?>" class="btn btn-outline-secondary btn-sm">
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
                            <label class="form-label fw-bold small">Department Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="<?= e($_POST['name'] ?? $department['name']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Short Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control text-uppercase" value="<?= e($_POST['code'] ?? $department['code']) ?>" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small">Mission & Description</label>
                            <textarea name="description" class="form-control" rows="3"><?= e($_POST['description'] ?? $department['description']) ?></textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Department Leader</label>
                            <select name="leader_id" class="form-select">
                                <option value="">Select Leader...</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= (($_POST['leader_id'] ?? $department['leader_id']) == $u['id']) ? 'selected' : '' ?>>
                                        <?= e($u['full_name']) ?> (<?= e($u['email']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Meeting Schedule</label>
                            <input type="text" name="meeting_schedule" class="form-control" value="<?= e($_POST['meeting_schedule'] ?? $department['meeting_schedule']) ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Annual Budget (<?= CURRENCY_SYMBOL ?>)</label>
                            <input type="number" step="0.01" name="budget_allocation" class="form-control" value="<?= e($_POST['budget_allocation'] ?? $department['budget_allocation']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" <?= (($_POST['status'] ?? $department['status']) === 'active') ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= (($_POST['status'] ?? $department['status']) === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="border-top mt-4 pt-3 d-flex justify-content-end gap-2">
                        <a href="<?= BASE_URL ?>departments/view.php?id=<?= $department['id'] ?>" class="btn btn-light border">Cancel</a>
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

