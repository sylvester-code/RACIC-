<?php
// visitors/create.php - Register First-Time Church Visitor
$pageTitle = 'Register New Visitor';
require_once __DIR__ . '/../includes/header.php';
requireRole(['super_admin', 'head_pastor', 'church_admin', 'dept_leader', 'church_worker']);

$db = getDB();
$user = currentUser();
$errors = [];

$members = $db->query("SELECT id, first_name, last_name, member_code FROM members WHERE membership_status = 'Active' ORDER BY first_name ASC")->fetchAll();
$workers = $db->query("SELECT id, full_name, email FROM users WHERE status = 'active' ORDER BY full_name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $errors[] = "Invalid CSRF security token.";
    } else {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $gender = $_POST['gender'] ?? '';
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $visitDate = !empty($_POST['visit_date']) ? $_POST['visit_date'] : date('Y-m-d');
        $inviterMemberId = !empty($_POST['invited_by_member_id']) ? (int)$_POST['invited_by_member_id'] : null;
        $inviterName = trim($_POST['invited_by_name'] ?? '');
        $prayerRequests = trim($_POST['prayer_requests'] ?? '');
        $followUpStatus = $_POST['follow_up_status'] ?? 'New';
        $assignedTo = !empty($_POST['assigned_to_user_id']) ? (int)$_POST['assigned_to_user_id'] : null;
        $notes = trim($_POST['notes'] ?? '');

        if (empty($firstName) || empty($lastName)) $errors[] = "First name and last name are required.";
        if (empty($gender)) $errors[] = "Gender is required.";
        if (empty($phone)) $errors[] = "Contact phone number is required.";

        if (empty($errors)) {
            try {
                $code = generateCode('RAC-V', 'visitors', 'visitor_code');

                $stmt = $db->prepare("
                    INSERT INTO visitors (
                        visitor_code, first_name, last_name, gender, phone, email,
                        address, visit_date, invited_by_member_id, invited_by_name,
                        prayer_requests, follow_up_status, assigned_to_user_id, notes, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $code, $firstName, $lastName, $gender, $phone, $email ?: null,
                    $address, $visitDate, $inviterMemberId, $inviterName,
                    $prayerRequests, $followUpStatus, $assignedTo, $notes
                ]);
                $visitorId = (int)$db->lastInsertId();

                if ($assignedTo) {
                    createNotification($assignedTo, "Visitor Assigned for Follow-up", "You have been assigned to follow up on guest {$firstName} {$lastName} ({$phone}).", "visitors/view.php?id={$visitorId}");
                }

                logAudit('CREATE_VISITOR', 'Visitors', $visitorId, "Registered visitor {$firstName} {$lastName} ({$code})");
                flash('success', "Visitor '{$firstName} {$lastName}' ({$code}) registered successfully!");
                header("Location: " . BASE_URL . "visitors/view.php?id={$visitorId}");
                exit;

            } catch (Exception $e) {
                $errors[] = "Registration error: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold text-dark mb-0"><i class="fas fa-user-plus text-primary me-2"></i>Register Church Visitor</h4>
                <small class="text-muted">Record visitor details, prayer needs, and assign follow-up care team.</small>
            </div>
            <a href="<?= BASE_URL ?>visitors/index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Back to Visitors
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
                            <label class="form-label fw-bold small">Phone Number <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control" placeholder="+233 20 000 0000" value="<?= e($_POST['phone'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="guest@example.com" value="<?= e($_POST['email'] ?? '') ?>">
                        </div>

                        <div class="col-md-8">
                            <label class="form-label fw-bold small">Residential Address / Area</label>
                            <input type="text" name="address" class="form-control" placeholder="e.g., East Legon, Spintex Road, Accra" value="<?= e($_POST['address'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">First Visit Date <span class="text-danger">*</span></label>
                            <input type="date" name="visit_date" class="form-control" value="<?= e($_POST['visit_date'] ?? date('Y-m-d')) ?>" required>
                        </div>

                        <!-- Invitation Details -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Invited by Member (Select if known)</label>
                            <select name="invited_by_member_id" class="form-select">
                                <option value="">None / External Source...</option>
                                <?php foreach ($members as $m): ?>
                                    <option value="<?= $m['id'] ?>" <?= (isset($_POST['invited_by_member_id']) && $_POST['invited_by_member_id'] == $m['id']) ? 'selected' : '' ?>>
                                        <?= e($m['first_name'] . ' ' . $m['last_name']) ?> (<?= e($m['member_code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Or Invited by / Channel</label>
                            <input type="text" name="invited_by_name" class="form-control" placeholder="e.g. Social Media, Evangelism Outreach, Radio" value="<?= e($_POST['invited_by_name'] ?? '') ?>">
                        </div>

                        <!-- Prayer Requests -->
                        <div class="col-12">
                            <label class="form-label fw-bold small">Prayer Requests / Spiritual Needs</label>
                            <textarea name="prayer_requests" class="form-control" rows="3" placeholder="Enter specific prayer requests shared by the visitor..."><?= e($_POST['prayer_requests'] ?? '') ?></textarea>
                        </div>

                        <!-- Follow up assignment -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Assign Follow-up Worker</label>
                            <select name="assigned_to_user_id" class="form-select">
                                <option value="">Assign Care Worker...</option>
                                <?php foreach ($workers as $w): ?>
                                    <option value="<?= $w['id'] ?>" <?= (isset($_POST['assigned_to_user_id']) && $_POST['assigned_to_user_id'] == $w['id']) ? 'selected' : '' ?>>
                                        <?= e($w['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Initial Follow-up Status</label>
                            <select name="follow_up_status" class="form-select">
                                <option value="New" selected>New</option>
                                <option value="Contacted">Contacted</option>
                                <option value="Visited">Visited</option>
                                <option value="Prayed With">Prayed With</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small">General Reception Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Impression, reception feedback, interested in ministries..."><?= e($_POST['notes'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="border-top mt-4 pt-3 d-flex justify-content-end gap-2">
                        <a href="<?= BASE_URL ?>visitors/index.php" class="btn btn-light border">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-check-circle me-1"></i>Save Visitor Record
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

