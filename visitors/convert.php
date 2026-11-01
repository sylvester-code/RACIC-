<?php
// visitors/convert.php - Convert Visitor to Full Church Member
$pageTitle = 'Convert Visitor to Member';
require_once __DIR__ . '/../includes/header.php';
requireRole(['super_admin', 'head_pastor', 'church_admin']);

$db = getDB();
$user = currentUser();
$visitorId = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];

$stmt = $db->prepare("SELECT * FROM visitors WHERE id = ?");
$stmt->execute([$visitorId]);
$visitor = $stmt->fetch();

if (!$visitor) {
    flash('danger', 'Visitor record not found.');
    header('Location: ' . BASE_URL . 'visitors/index.php');
    exit;
}

if ($visitor['follow_up_status'] === 'Converted' && $visitor['converted_to_member_id']) {
    flash('info', 'This visitor has already been converted into a church member.');
    header('Location: ' . BASE_URL . 'members/view.php?id=' . $visitor['converted_to_member_id']);
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
        $departmentId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $baptismStatus = $_POST['baptism_status'] ?? 'Not Baptized';
        $membershipDate = !empty($_POST['membership_date']) ? $_POST['membership_date'] : date('Y-m-d');
        $notes = trim($_POST['notes'] ?? '');

        if (empty($firstName) || empty($lastName)) $errors[] = "First name and last name are required.";
        if (empty($phone)) $errors[] = "Phone number is required.";

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                $memberCode = generateCode('RAC-M', 'members', 'member_code');

                // Insert into members
                $mStmt = $db->prepare("
                    INSERT INTO members (
                        member_code, first_name, last_name, gender, date_of_birth,
                        marital_status, phone, email, address, occupation,
                        baptism_status, membership_date, membership_status,
                        department_id, notes, created_by, created_at
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, 'Active',
                        ?, ?, ?, NOW()
                    )
                ");
                $mStmt->execute([
                    $memberCode, $firstName, $lastName, $gender, $dob,
                    $maritalStatus, $phone, $email ?: null, $address, $occupation,
                    $baptismStatus, $membershipDate,
                    $departmentId, "Converted from visitor {$visitor['visitor_code']}. " . $notes, $user['id']
                ]);
                $newMemberId = (int)$db->lastInsertId();

                // If department selected, add to department_members
                if ($departmentId) {
                    $dmStmt = $db->prepare("
                        INSERT INTO department_members (department_id, member_id, role_in_dept, joined_date, status)
                        VALUES (?, ?, 'Member', ?, 'active')
                    ");
                    $dmStmt->execute([$departmentId, $newMemberId, $membershipDate]);
                }

                // Update visitor record
                $vUpd = $db->prepare("
                    UPDATE visitors 
                    SET follow_up_status = 'Converted', converted_to_member_id = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $vUpd->execute([$newMemberId, $visitorId]);

                $db->commit();
                logAudit('CONVERT_VISITOR', 'Visitors', $visitorId, "Converted visitor {$visitor['visitor_code']} to church member {$memberCode}.");
                flash('success', "Praise the Lord! Visitor '{$firstName} {$lastName}' has been officially converted into a church member ({$memberCode})!");
                header("Location: " . BASE_URL . "members/view.php?id={$newMemberId}");
                exit;

            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = "Conversion error: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold text-dark mb-0"><i class="fas fa-user-check text-warning me-2"></i>Convert Visitor into Church Member</h4>
                <small class="text-muted">Transition a nurtured first-time guest into the official RACIC membership roll.</small>
            </div>
            <a href="<?= BASE_URL ?>visitors/view.php?id=<?= $visitor['id'] ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Cancel
            </a>
        </div>

        <div class="alert alert-success shadow-sm mb-4">
            <i class="fas fa-star text-warning me-2 fs-5"></i>
            Converting <strong><?= e($visitor['first_name'] . ' ' . $visitor['last_name']) ?></strong> (Visitor Code: <code><?= e($visitor['visitor_code']) ?></code>, Visited: <?= formatDate($visitor['visit_date']) ?>)
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
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" value="<?= e($_POST['first_name'] ?? $visitor['first_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" value="<?= e($_POST['last_name'] ?? $visitor['last_name']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Gender <span class="text-danger">*</span></label>
                            <select name="gender" class="form-select" required>
                                <option value="Male" <?= (($_POST['gender'] ?? $visitor['gender']) === 'Male') ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= (($_POST['gender'] ?? $visitor['gender']) === 'Female') ? 'selected' : '' ?>>Female</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control" value="<?= e($_POST['date_of_birth'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Marital Status</label>
                            <select name="marital_status" class="form-select">
                                <option value="Single">Single</option>
                                <option value="Married">Married</option>
                                <option value="Divorced">Divorced</option>
                                <option value="Widowed">Widowed</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Phone Number <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control" value="<?= e($_POST['phone'] ?? $visitor['phone']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Email Address</label>
                            <input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? $visitor['email']) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">Residential Address</label>
                            <input type="text" name="address" class="form-control" value="<?= e($_POST['address'] ?? $visitor['address']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Occupation</label>
                            <input type="text" name="occupation" class="form-control" placeholder="Profession / Career" value="<?= e($_POST['occupation'] ?? '') ?>">
                        </div>

                        <!-- Department & Spiritual Placement -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Assign to Department</label>
                            <select name="department_id" class="form-select">
                                <option value="">Select Department...</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['id'] ?>">
                                        <?= e($d['name']) ?> (<?= e($d['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Baptism Status</label>
                            <select name="baptism_status" class="form-select">
                                <option value="Baptized">Baptized</option>
                                <option value="Not Baptized" selected>Not Baptized</option>
                                <option value="In Training">In Training</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Official Membership Joining Date</label>
                            <input type="date" name="membership_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small">Spiritual Testimony / Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Details of their decision to make RACIC their home church..."><?= e($_POST['notes'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="border-top mt-4 pt-3 d-flex justify-content-end gap-2">
                        <a href="<?= BASE_URL ?>visitors/view.php?id=<?= $visitor['id'] ?>" class="btn btn-light border">Cancel</a>
                        <button type="submit" class="btn btn-success px-4 shadow-sm">
                            <i class="fas fa-check-circle me-1"></i>Confirm Membership Conversion
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

