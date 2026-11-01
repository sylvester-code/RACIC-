<?php
// members/view.php - Comprehensive Church Member Profile
$pageTitle = 'Member Profile';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$user = currentUser();
$id = !empty($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $db->prepare("
    SELECT m.*, d.name as department_name, d.code as department_code, u.full_name as recorder_name
    FROM members m
    LEFT JOIN departments d ON d.id = m.department_id
    LEFT JOIN users u ON u.id = m.created_by
    WHERE m.id = ?
");
$stmt->execute([$id]);
$member = $stmt->fetch();

if (!$member) {
    flash('danger', 'Member not found.');
    header('Location: ' . BASE_URL . 'members/index.php');
    exit;
}

// Attendance History (Last 8 sessions)
$attStmt = $db->prepare("
    SELECT ad.*, a.attendance_date, s.service_name, act.title as activity_title
    FROM attendance_details ad
    INNER JOIN attendance a ON a.id = ad.attendance_id
    LEFT JOIN services s ON s.id = a.service_id
    LEFT JOIN activities act ON act.id = a.activity_id
    WHERE ad.member_id = ?
    ORDER BY a.attendance_date DESC
    LIMIT 8
");
$attStmt->execute([$id]);
$attendances = $attStmt->fetchAll();

// Financial Giving History (Tithes / Donations recorded for this member)
$finStmt = $db->prepare("
    SELECT * FROM income WHERE member_id = ? ORDER BY transaction_date DESC LIMIT 8
");
$finStmt->execute([$id]);
$givings = $finStmt->fetchAll();

$totalGiving = (float)$db->prepare("SELECT COALESCE(SUM(amount), 0) FROM income WHERE member_id = ?");
$totalGiving->execute([$id]);
$sumGiving = (float)$totalGiving->fetchColumn();

// Calculate age if DOB exists
$age = null;
if (!empty($member['date_of_birth'])) {
    $dobDate = new DateTime($member['date_of_birth']);
    $now = new DateTime();
    $age = $dobDate->diff($now)->y;
}
?>

<!-- Header Toolbar -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div class="d-flex align-items-center gap-3">
        <?php if (!empty($member['photo'])): ?>
            <img src="<?= UPLOAD_URL . e($member['photo']) ?>" alt="Photo" class="rounded-circle shadow-sm" style="width: 64px; height: 64px; object-fit: cover;">
        <?php else: ?>
            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold fs-4 shadow-sm" style="width: 64px; height: 64px;">
                <?= strtoupper(substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1)) ?>
            </div>
        <?php endif; ?>
        <div>
            <div class="d-flex align-items-center gap-2">
                <h3 class="fw-bold text-dark mb-0"><?= e($member['first_name'] . ' ' . $member['last_name']) ?></h3>
                <?= getStatusBadge($member['membership_status']) ?>
            </div>
            <div class="text-muted small">
                <span class="badge bg-light text-primary border font-monospace"><?= e($member['member_code']) ?></span> &bull; 
                Joined <?= formatDate($member['membership_date']) ?>
            </div>
        </div>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <a href="<?= BASE_URL ?>members/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Directory
        </a>
        <a href="<?= BASE_URL ?>members/edit.php?id=<?= $member['id'] ?>" class="btn btn-primary btn-sm">
            <i class="fas fa-edit me-1"></i>Edit Member
        </a>
        <button class="btn btn-outline-dark btn-sm btn-trigger-print">
            <i class="fas fa-print me-1"></i>Print Profile
        </button>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Personal, Spiritual & Contact Details -->
    <div class="col-lg-5">
        <!-- Profile Overview Card -->
        <div class="church-card mb-4">
            <div class="card-header">
                <span><i class="fas fa-user-circle text-primary me-2"></i>Personal Profile</span>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Gender:</span>
                        <strong class="text-dark"><?= e($member['gender']) ?></strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Marital Status:</span>
                        <strong class="text-dark"><?= e($member['marital_status']) ?></strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Date of Birth:</span>
                        <strong class="text-dark">
                            <?= formatDate($member['date_of_birth']) ?> <?= $age ? "({$age} yrs)" : '' ?>
                        </strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Occupation:</span>
                        <strong class="text-dark"><?= e($member['occupation'] ?: '—') ?></strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Primary Phone:</span>
                        <a href="tel:<?= e($member['phone']) ?>" class="fw-bold text-decoration-none"><?= e($member['phone']) ?></a>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Email:</span>
                        <strong class="text-dark"><?= e($member['email'] ?: '—') ?></strong>
                    </li>
                    <li class="list-group-item px-0">
                        <span class="text-muted d-block mb-1">Residential Address:</span>
                        <div class="small text-secondary"><?= nl2br(e($member['address'] ?: '—')) ?></div>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Emergency Contact Card -->
        <div class="church-card mb-4">
            <div class="card-header">
                <span><i class="fas fa-phone-square-alt text-danger me-2"></i>Emergency Contact</span>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <small class="text-muted d-block">Contact Person</small>
                    <span class="fw-bold text-dark"><?= e($member['emergency_contact_name'] ?: 'Not provided') ?></span>
                </div>
                <div>
                    <small class="text-muted d-block">Contact Phone</small>
                    <span class="fw-bold text-dark"><?= e($member['emergency_contact_phone'] ?: '—') ?></span>
                </div>
            </div>
        </div>

        <!-- Spiritual & Department Card -->
        <div class="church-card">
            <div class="card-header">
                <span><i class="fas fa-cross text-warning me-2"></i>Spiritual Record</span>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Department:</span>
                        <span class="badge bg-light text-dark border"><?= e($member['department_name'] ?? 'Not Assigned') ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Baptism Status:</span>
                        <?= getStatusBadge($member['baptism_status']) ?>
                    </li>
                    <?php if ($member['baptism_date']): ?>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Baptism Date:</span>
                            <strong><?= formatDate($member['baptism_date']) ?></strong>
                        </li>
                    <?php endif; ?>
                    <li class="list-group-item px-0">
                        <span class="text-muted d-block mb-1">Pastoral Notes:</span>
                        <small class="text-secondary"><?= nl2br(e($member['notes'] ?: 'No recorded pastoral notes.')) ?></small>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Right Column: Attendance & Giving History -->
    <div class="col-lg-7">
        <!-- Attendance History Card -->
        <div class="church-card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-calendar-check text-primary me-2"></i>Recent Attendance History</span>
                <span class="badge bg-light text-muted border"><?= count($attendances) ?> entries</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Service / Program</th>
                                <th>Check-in Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($attendances)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No attendance roll-calls recorded yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($attendances as $att): ?>
                                    <tr>
                                        <td class="small fw-semibold"><?= formatDate($att['attendance_date']) ?></td>
                                        <td><?= e($att['service_name'] ?? $att['activity_title'] ?? 'Church Gathering') ?></td>
                                        <td class="small text-muted"><?= $att['check_in_time'] ? date('h:i A', strtotime($att['check_in_time'])) : '—' ?></td>
                                        <td><?= getStatusBadge($att['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Financial Tithe & Giving History (Pastoral / Finance visibility) -->
        <?php if (hasRole(['super_admin', 'head_pastor', 'finance_officer'])): ?>
            <div class="church-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-hand-holding-heart text-success me-2"></i>Stewardship & Giving Records</span>
                    <span class="badge bg-success">Total: <?= formatCurrency($sumGiving) ?></span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Txn Code</th>
                                    <th>Category</th>
                                    <th>Date</th>
                                    <th>Method</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($givings)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">No individual financial records attributed yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($givings as $g): ?>
                                        <tr>
                                            <td class="small font-monospace text-primary"><?= e($g['transaction_code']) ?></td>
                                            <td><span class="badge bg-light text-dark border"><?= e($g['category']) ?></span></td>
                                            <td class="small"><?= formatDate($g['transaction_date']) ?></td>
                                            <td class="small text-muted"><?= e($g['payment_method']) ?></td>
                                            <td class="text-end fw-bold text-success"><?= formatCurrency($g['amount']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

