<?php
// members/create.php - Member Registration
$pageTitle = 'Register New Member';
require_once __DIR__ . '/../includes/header.php';
requireRole(['super_admin', 'head_pastor', 'church_admin', 'dept_leader']);

$db = getDB();
$user = currentUser();
$errors = [];

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
        if (empty($gender)) $errors[] = "Gender is required.";
        if (empty($phone)) $errors[] = "Contact phone number is required.";

        // Handle Photo upload
        $photoPath = null;
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

                $memberCode = generateCode('RAC-M', 'members', 'member_code');

                $stmt = $db->prepare("
                    INSERT INTO members (
                        member_code, first_name, last_name, gender, date_of_birth,
                        marital_status, phone, email, address, occupation,
                        baptism_status, baptism_date, membership_date, membership_status,
                        department_id, photo, emergency_contact_name, emergency_contact_phone,
                        notes, created_by, created_at
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, NOW()
                    )
                ");
                $stmt->execute([
                    $memberCode, $firstName, $lastName, $gender, $dob,
                    $maritalStatus, $phone, $email ?: null, $address, $occupation,
                    $baptismStatus, $baptismDate, $membershipDate, $membershipStatus,
                    $departmentId, $photoPath, $emergencyName, $emergencyPhone,
                    $notes, $user['id']
                ]);
                $memberId = (int)$db->lastInsertId();

                // If department selected, link in department_members
                if ($departmentId) {
                    $dmStmt = $db->prepare("
                        INSERT INTO department_members (department_id, member_id, role_in_dept, joined_date, status)
                        VALUES (?, ?, 'Member', ?, 'active')
                        ON DUPLICATE KEY UPDATE status = 'active'
                    ");
                    $dmStmt->execute([$departmentId, $memberId, $membershipDate]);
                }

                $db->commit();
                logAudit('CREATE_MEMBER', 'Members', $memberId, "Registered member {$firstName} {$lastName} ({$memberCode})");
                flash('success', "Member '{$firstName} {$lastName}' ({$memberCode}) registered successfully!");
                header("Location: " . BASE_URL . "members/view.php?id={$memberId}");
                exit;

            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = "Registration failed: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold text-dark mb-0"><i class="fas fa-user-plus text-primary me-2"></i>Member Registration</h4>
                <small class="text-muted">Enter personal background, spiritual milestones, and departmental placement.</small>
            </div>
            <a href="<?= BASE_URL ?>members/index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Back to Directory
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

                    <!-- SECTION 1: Personal Information -->
                    <h5 class="fw-bold text-primary mb-3"><i class="fas fa-id-card me-2"></i>Personal Information</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" value="<?= e($_POST['first_name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" value="<?= e($_POST['last_name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Gender <span class="text-danger">*</span></label>
                            <select name="gender" class="form-select" required>
                                <option value="">Select Gender...</option>
                                <option value="Male" <?= (($_POST['gender'] ?? '') === 'Male') ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= (($_POST['gender'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control" value="<?= e($_POST['date_of_birth'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Marital Status</label>
                            <select name="marital_status" class="form-select">
                                <option value="Single" <?= (($_POST['marital_status'] ?? 'Single') === 'Single') ? 'selected' : '' ?>>Single</option>
                                <option value="Married" <?= (($_POST['marital_status'] ?? '') === 'Married') ? 'selected' : '' ?>>Married</option>
                                <option value="Divorced" <?= (($_POST['marital_status'] ?? '') === 'Divorced') ? 'selected' : '' ?>>Divorced</option>
                                <option value="Widowed" <?= (($_POST['marital_status'] ?? '') === 'Widowed') ? 'selected' : '' ?>>Widowed</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Occupation / Profession</label>
                            <input type="text" name="occupation" class="form-control" placeholder="e.g. Teacher, Engineer, Trader" value="<?= e($_POST['occupation'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Member Photograph</label>
                            <input type="file" name="photo" class="form-control" accept="image/jpeg,image/png,image/webp">
                            <small class="text-muted">JPG, PNG, or WEBP (Max 10MB)</small>
                        </div>
                    </div>

                    <!-- SECTION 2: Contact Information -->
                    <h5 class="fw-bold text-primary mb-3"><i class="fas fa-phone-alt me-2"></i>Contact Information</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Primary Phone <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control" placeholder="+233 24 000 0000" value="<?= e($_POST['phone'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="member@example.com" value="<?= e($_POST['email'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">Residential Address / City</label>
                            <textarea name="address" class="form-control" rows="2" placeholder="House number, Street name, Suburb, City"><?= e($_POST['address'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Emergency Contact Name</label>
                            <input type="text" name="emergency_contact_name" class="form-control" value="<?= e($_POST['emergency_contact_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Emergency Contact Phone</label>
                            <input type="text" name="emergency_contact_phone" class="form-control" value="<?= e($_POST['emergency_contact_phone'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- SECTION 3: Spiritual & Church Life -->
                    <h5 class="fw-bold text-primary mb-3"><i class="fas fa-bible me-2"></i>Spiritual Milestones & Department</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Department Allocation</label>
                            <select name="department_id" class="form-select">
                                <option value="">Select Department (Optional)...</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['id'] ?>" <?= (isset($_POST['department_id']) && $_POST['department_id'] == $d['id']) ? 'selected' : '' ?>>
                                        <?= e($d['name']) ?> (<?= e($d['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Baptism Status</label>
                            <select name="baptism_status" class="form-select">
                                <option value="Baptized" <?= (($_POST['baptism_status'] ?? '') === 'Baptized') ? 'selected' : '' ?>>Baptized</option>
                                <option value="Not Baptized" <?= (($_POST['baptism_status'] ?? 'Not Baptized') === 'Not Baptized') ? 'selected' : '' ?>>Not Baptized</option>
                                <option value="In Training" <?= (($_POST['baptism_status'] ?? '') === 'In Training') ? 'selected' : '' ?>>In Training</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Baptism Date</label>
                            <input type="date" name="baptism_date" class="form-control" value="<?= e($_POST['baptism_date'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Membership Date <span class="text-danger">*</span></label>
                            <input type="date" name="membership_date" class="form-control" value="<?= e($_POST['membership_date'] ?? date('Y-m-d')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Membership Status <span class="text-danger">*</span></label>
                            <select name="membership_status" class="form-select" required>
                                <option value="Active" <?= (($_POST['membership_status'] ?? 'Active') === 'Active') ? 'selected' : '' ?>>Active</option>
                                <option value="Inactive" <?= (($_POST['membership_status'] ?? '') === 'Inactive') ? 'selected' : '' ?>>Inactive</option>
                                <option value="Transferred" <?= (($_POST['membership_status'] ?? '') === 'Transferred') ? 'selected' : '' ?>>Transferred</option>
                                <option value="Deceased" <?= (($_POST['membership_status'] ?? '') === 'Deceased') ? 'selected' : '' ?>>Deceased</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small">Pastoral Notes & Spiritual History</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Spiritual gifts, counseling history, notes..."><?= e($_POST['notes'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="border-top pt-3 d-flex justify-content-end gap-2">
                        <a href="<?= BASE_URL ?>members/index.php" class="btn btn-light border">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4 shadow-sm">
                            <i class="fas fa-check-circle me-1"></i>Register Member
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

