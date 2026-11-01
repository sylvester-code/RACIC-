<?php
// members/edit.php - Edit Member Details
$pageTitle = 'Edit Member Profile';
require_once __DIR__ . '/../includes/header.php';
requireRole(['super_admin', 'head_pastor', 'church_admin', 'dept_leader']);

$db = getDB();
$user = currentUser();
$id = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];

$stmt = $db->prepare("SELECT * FROM members WHERE id = ?");
$stmt->execute([$id]);
$member = $stmt->fetch();

if (!$member) {
    flash('danger', 'Member record not found.');
    header('Location: ' . BASE_URL . 'members/index.php');
    exit;
}

$departments = $db->query("SELECT id, name, code FROM departments WHERE status = 'active' ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $errors[] = "Invalid CSRF security token.";
    } else {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $gender = $_POST['gender'] ?? '';
        $dob = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
        $maritalStatus = $_POST['marital_status'] ?? 'Single';
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $occupation = trim($_POST['occupation'] ?? '');
        $baptismStatus = $_POST['baptism_status'] ?? 'Not Baptized';
        $baptismDate = !empty($_POST['baptism_date']) ? $_POST['baptism_date'] : null;
        $membershipDate = !empty($_POST['membership_date']) ? $_POST['membership_date'] : date('Y-m-d');
        $membershipStatus = $_POST['membership_status'] ?? 'Active';
        $departmentId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $emergencyName = trim($_POST['emergency_contact_name'] ?? '');
        $emergencyPhone = trim($_POST['emergency_contact_phone'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (empty($firstName) || empty($lastName)) $errors[] = "First name and last name are required.";
        if (empty($phone)) $errors[] = "Contact phone number is required.";

        // Handle Photo upload
        $photoPath = $member['photo'];
        if (!empty($_FILES['photo']['name'])) {
            try {
                $photoPath = uploadFile('photo', 'members', ['jpg', 'jpeg', 'png', 'webp']);
            } catch (Exception $e) {
                $errors[] = "Photo upload error: " . $e->getMessage();
            }
        }

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                $upd = $db->prepare("
                    UPDATE members SET
                        first_name = ?, last_name = ?, gender = ?, date_of_birth = ?,
                        marital_status = ?, phone = ?, email = ?, address = ?, occupation = ?,
                        baptism_status = ?, baptism_date = ?, membership_date = ?, membership_status = ?,
                        department_id = ?, photo = ?, emergency_contact_name = ?, emergency_contact_phone = ?,
                        notes = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $upd->execute([
                    $firstName, $lastName, $gender, $dob,
                    $maritalStatus, $phone, $email ?: null, $address, $occupation,
                    $baptismStatus, $baptismDate, $membershipDate, $membershipStatus,
                    $departmentId, $photoPath, $emergencyName, $emergencyPhone,
                    $notes, $id
                ]);

                // Sync department membership
                if ($departmentId) {
                    $dmStmt = $db->prepare("
                        INSERT INTO department_members (department_id, member_id, role_in_dept, joined_date, status)
                        VALUES (?, ?, 'Member', ?, 'active')
                        ON DUPLICATE KEY UPDATE status = 'active'
                    ");
                    $dmStmt->execute([$departmentId, $id, $membershipDate]);
                }

                $db->commit();
                logAudit('UPDATE_MEMBER', 'Members', $id, "Updated member {$firstName} {$lastName} ({$member['member_code']})");
                flash('success', "Member '{$firstName} {$lastName}' updated successfully!");
                header("Location: " . BASE_URL . "members/view.php?id={$id}");
                exit;

            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = "Error saving member: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold text-dark mb-0"><i class="fas fa-user-edit text-primary me-2"></i>Edit Member: <?= e($member['member_code']) ?></h4>
                <small class="text-muted">Update contact details, spiritual milestones, or membership status.</small>
            </div>
            <a href="<?= BASE_URL ?>members/view.php?id=<?= $member['id'] ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Back to Profile
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
                <form method="POST" action="" enctype="multipart/form-data">
                    <?= csrfField(); ?>

                    <h5 class="fw-bold text-primary mb-3"><i class="fas fa-id-card me-2"></i>Personal Details</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" value="<?= e($_POST['first_name'] ?? $member['first_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" value="<?= e($_POST['last_name'] ?? $member['last_name']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Gender <span class="text-danger">*</span></label>
                            <select name="gender" class="form-select" required>
                                <option value="Male" <?= (($_POST['gender'] ?? $member['gender']) === 'Male') ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= (($_POST['gender'] ?? $member['gender']) === 'Female') ? 'selected' : '' ?>>Female</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control" value="<?= e($_POST['date_of_birth'] ?? $member['date_of_birth']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Marital Status</label>
                            <select name="marital_status" class="form-select">
                                <option value="Single" <?= (($_POST['marital_status'] ?? $member['marital_status']) === 'Single') ? 'selected' : '' ?>>Single</option>
                                <option value="Married" <?= (($_POST['marital_status'] ?? $member['marital_status']) === 'Married') ? 'selected' : '' ?>>Married</option>
                                <option value="Divorced" <?= (($_POST['marital_status'] ?? $member['marital_status']) === 'Divorced') ? 'selected' : '' ?>>Divorced</option>
                                <option value="Widowed" <?= (($_POST['marital_status'] ?? $member['marital_status']) === 'Widowed') ? 'selected' : '' ?>>Widowed</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Occupation</label>
                            <input type="text" name="occupation" class="form-control" value="<?= e($_POST['occupation'] ?? $member['occupation']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Change Photograph</label>
                            <input type="file" name="photo" class="form-control" accept="image/jpeg,image/png,image/webp">
                            <?php if ($member['photo']): ?>
                                <small class="text-success d-block mt-1"><i class="fas fa-check me-1"></i>Current photo exists</small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <h5 class="fw-bold text-primary mb-3"><i class="fas fa-phone-alt me-2"></i>Contact Information</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Phone <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control" value="<?= e($_POST['phone'] ?? $member['phone']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Email Address</label>
                            <input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? $member['email']) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">Residential Address</label>
                            <textarea name="address" class="form-control" rows="2"><?= e($_POST['address'] ?? $member['address']) ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Emergency Contact Name</label>
                            <input type="text" name="emergency_contact_name" class="form-control" value="<?= e($_POST['emergency_contact_name'] ?? $member['emergency_contact_name']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Emergency Contact Phone</label>
                            <input type="text" name="emergency_contact_phone" class="form-control" value="<?= e($_POST['emergency_contact_phone'] ?? $member['emergency_contact_phone']) ?>">
                        </div>
                    </div>

                    <h5 class="fw-bold text-primary mb-3"><i class="fas fa-church me-2"></i>Church & Ministry Information</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Department</label>
                            <select name="department_id" class="form-select">
                                <option value="">Select Department...</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['id'] ?>" <?= (($_POST['department_id'] ?? $member['department_id']) == $d['id']) ? 'selected' : '' ?>>
                                        <?= e($d['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Baptism Status</label>
                            <select name="baptism_status" class="form-select">
                                <option value="Baptized" <?= (($_POST['baptism_status'] ?? $member['baptism_status']) === 'Baptized') ? 'selected' : '' ?>>Baptized</option>
                                <option value="Not Baptized" <?= (($_POST['baptism_status'] ?? $member['baptism_status']) === 'Not Baptized') ? 'selected' : '' ?>>Not Baptized</option>
                                <option value="In Training" <?= (($_POST['baptism_status'] ?? $member['baptism_status']) === 'In Training') ? 'selected' : '' ?>>In Training</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Baptism Date</label>
                            <input type="date" name="baptism_date" class="form-control" value="<?= e($_POST['baptism_date'] ?? $member['baptism_date']) ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Membership Date</label>
                            <input type="date" name="membership_date" class="form-control" value="<?= e($_POST['membership_date'] ?? $member['membership_date']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Membership Status</label>
                            <select name="membership_status" class="form-select" required>
                                <option value="Active" <?= (($_POST['membership_status'] ?? $member['membership_status']) === 'Active') ? 'selected' : '' ?>>Active</option>
                                <option value="Inactive" <?= (($_POST['membership_status'] ?? $member['membership_status']) === 'Inactive') ? 'selected' : '' ?>>Inactive</option>
                                <option value="Transferred" <?= (($_POST['membership_status'] ?? $member['membership_status']) === 'Transferred') ? 'selected' : '' ?>>Transferred</option>
                                <option value="Deceased" <?= (($_POST['membership_status'] ?? $member['membership_status']) === 'Deceased') ? 'selected' : '' ?>>Deceased</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small">Pastoral Notes</label>
                            <textarea name="notes" class="form-control" rows="2"><?= e($_POST['notes'] ?? $member['notes']) ?></textarea>
                        </div>
                    </div>

                    <div class="border-top pt-3 d-flex justify-content-end gap-2">
                        <a href="<?= BASE_URL ?>members/view.php?id=<?= $member['id'] ?>" class="btn btn-light border">Cancel</a>
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

