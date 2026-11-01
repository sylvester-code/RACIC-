<?php
// departments/view.php - Dedicated Department Management Portal
$pageTitle = 'Department Management Portal';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$user = currentUser();
$id = !empty($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $db->prepare("
    SELECT d.*, u.full_name as leader_name, u.email as leader_email, u.phone as leader_phone
    FROM departments d
    LEFT JOIN users u ON u.id = d.leader_id
    WHERE d.id = ?
");
$stmt->execute([$id]);
$department = $stmt->fetch();

if (!$department) {
    flash('danger', 'Department not found.');
    header('Location: ' . BASE_URL . 'departments/index.php');
    exit;
}

// 1. Department Members
$membersStmt = $db->prepare("
    SELECT m.*, dm.role_in_dept, dm.joined_date as dept_joined_date, dm.id as dm_id
    FROM members m
    LEFT JOIN department_members dm ON dm.member_id = m.id AND dm.department_id = ?
    WHERE m.department_id = ? OR dm.department_id = ?
    ORDER BY m.first_name ASC
");
$membersStmt->execute([$id, $id, $id]);
$deptMembers = $membersStmt->fetchAll();

// 2. Department Activities
$actStmt = $db->prepare("
    SELECT a.*, ar.id as report_id, ar.status as report_status, ar.actual_attendance, ar.expenditure
    FROM activities a
    LEFT JOIN activity_reports ar ON ar.activity_id = a.id
    WHERE a.department_id = ?
    ORDER BY a.activity_date DESC
");
$actStmt->execute([$id]);
$deptActivities = $actStmt->fetchAll();

// 3. Department Expenses
$expStmt = $db->prepare("
    SELECT e.*, u.full_name as recorder_name
    FROM expenses e
    LEFT JOIN users u ON u.id = e.recorded_by_user_id
    WHERE e.department_id = ?
    ORDER BY e.transaction_date DESC
");
$expStmt->execute([$id]);
$deptExpenses = $expStmt->fetchAll();

$totalSpent = (float)$db->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE department_id = ? AND status = 'Approved'");
$totalSpent->execute([$id]);
$sumSpent = (float)$totalSpent->fetchColumn();

// 4. Quick Member Allocation Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_member'])) {
    if (!validateCSRF()) {
        flash('danger', 'Invalid CSRF token.');
    } else {
        $memberId = (int)$_POST['member_id'];
        $roleInDept = trim($_POST['role_in_dept'] ?? 'Member');

        if ($memberId > 0) {
            $addDm = $db->prepare("
                INSERT INTO department_members (department_id, member_id, role_in_dept, joined_date, status)
                VALUES (?, ?, ?, CURDATE(), 'active')
                ON DUPLICATE KEY UPDATE role_in_dept = ?, status = 'active'
            ");
            $addDm->execute([$id, $memberId, $roleInDept, $roleInDept]);

            // Update member's primary department_id if empty
            $memUpd = $db->prepare("UPDATE members SET department_id = ? WHERE id = ? AND department_id IS NULL");
            $memUpd->execute([$id, $memberId]);

            logAudit('ASSIGN_DEPT_MEMBER', 'Departments', $id, "Assigned member ID {$memberId} to department {$department['name']} as {$roleInDept}");
            flash('success', 'Member successfully allocated to this department!');
            header("Location: " . BASE_URL . "departments/view.php?id={$id}");
            exit;
        }
    }
}

// Available members for assignment
$availableMembers = $db->query("SELECT id, first_name, last_name, member_code FROM members WHERE membership_status = 'Active' ORDER BY first_name ASC")->fetchAll();
?>

<!-- Department Portal Header Banner -->
<div class="church-card mb-4 bg-primary text-white border-0 shadow" style="background: linear-gradient(135deg, #07172b 0%, #0f2c59 100%) !important;">
    <div class="card-body p-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-warning text-dark font-monospace fs-6"><?= e($department['code']) ?></span>
                    <h3 class="fw-bold mb-0 text-white"><?= e($department['name']) ?></h3>
                    <?= getStatusBadge($department['status']) ?>
                </div>
                <p class="text-white-50 small mb-2" style="max-width: 600px;">
                    <?= e($department['description'] ?: 'Official department operational portal and ministry records.') ?>
                </p>
                <div class="d-flex flex-wrap gap-3 small text-white-50">
                    <div><i class="fas fa-user-tie text-warning me-1"></i><strong>Leader:</strong> <?= e($department['leader_name'] ?: 'None') ?></div>
                    <div><i class="fas fa-clock text-info me-1"></i><strong>Meetings:</strong> <?= e($department['meeting_schedule'] ?: 'TBD') ?></div>
                    <div><i class="fas fa-users text-light me-1"></i><strong>Roll:</strong> <?= count($deptMembers) ?> Members</div>
                </div>
            </div>
            <div class="mt-3 mt-md-0 d-flex flex-column gap-2 text-md-end">
                <div class="d-flex gap-2 justify-content-md-end">
                    <a href="<?= BASE_URL ?>departments/index.php" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>All Departments
                    </a>
                    <?php if (hasRole(['super_admin', 'head_pastor', 'church_admin'])): ?>
                        <a href="<?= BASE_URL ?>departments/edit.php?id=<?= $department['id'] ?>" class="btn btn-warning btn-sm text-dark">
                            <i class="fas fa-edit me-1"></i>Edit Ministry
                        </a>
                    <?php endif; ?>
                </div>
                <div class="bg-white bg-opacity-10 rounded p-2 text-white">
                    <div class="small text-white-50">Budget Allocation: <strong><?= formatCurrency($department['budget_allocation']) ?></strong></div>
                    <div class="small text-white-50">Total Expenses: <strong class="text-warning"><?= formatCurrency($sumSpent) ?></strong></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Department Tabs -->
<ul class="nav nav-pills mb-4 gap-2" id="deptTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active px-4" id="members-tab" data-bs-toggle="tab" data-bs-target="#members" type="button" role="tab">
            <i class="fas fa-users me-2"></i>Department Members (<?= count($deptMembers) ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link px-4" id="activities-tab" data-bs-toggle="tab" data-bs-target="#activities" type="button" role="tab">
            <i class="fas fa-calendar-check me-2"></i>Activities & Programs (<?= count($deptActivities) ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link px-4" id="finance-tab" data-bs-toggle="tab" data-bs-target="#finance" type="button" role="tab">
            <i class="fas fa-coins me-2"></i>Expenses & Stewardship
        </button>
    </li>
</ul>

<div class="tab-content" id="deptTabContent">
    <!-- TAB 1: MEMBERS -->
    <div class="tab-pane fade show active" id="members" role="tabpanel">
        <div class="church-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-users text-primary me-2"></i>Department Membership Roster</span>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                    <i class="fas fa-user-plus me-1"></i>Assign Member
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="deptMembersTable">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Member Name</th>
                                <th>Role in Department</th>
                                <th>Phone</th>
                                <th>Baptism Status</th>
                                <th>Membership</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($deptMembers)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No members assigned to this ministry yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($deptMembers as $dm): ?>
                                    <tr>
                                        <td class="fw-bold font-monospace text-primary"><?= e($dm['member_code']) ?></td>
                                        <td>
                                            <a href="<?= BASE_URL ?>members/view.php?id=<?= $dm['id'] ?>" class="text-dark fw-bold text-decoration-none">
                                                <?= e($dm['first_name'] . ' ' . $dm['last_name']) ?>
                                            </a>
                                            <div class="small text-muted"><?= e($dm['gender']) ?></div>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?= e($dm['role_in_dept'] ?? 'Member') ?></span></td>
                                        <td class="small"><?= e($dm['phone']) ?></td>
                                        <td><?= getStatusBadge($dm['baptism_status']) ?></td>
                                        <td><?= getStatusBadge($dm['membership_status']) ?></td>
                                        <td class="text-end">
                                            <a href="<?= BASE_URL ?>members/view.php?id=<?= $dm['id'] ?>" class="btn btn-sm btn-light border" title="View Profile">
                                                <i class="fas fa-eye text-primary"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 2: ACTIVITIES -->
    <div class="tab-pane fade" id="activities" role="tabpanel">
        <div class="church-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-calendar-alt text-primary me-2"></i>Department Activities & Reports</span>
                <a href="<?= BASE_URL ?>activities/create.php" class="btn btn-sm btn-primary">
                    <i class="fas fa-plus me-1"></i>Schedule Activity
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Activity Title</th>
                                <th>Date & Venue</th>
                                <th>Attendance</th>
                                <th>Status</th>
                                <th>Report</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($deptActivities)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No activities scheduled for this department yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($deptActivities as $da): ?>
                                    <tr>
                                        <td class="fw-bold font-monospace text-primary"><?= e($da['activity_code']) ?></td>
                                        <td>
                                            <a href="<?= BASE_URL ?>activities/view.php?id=<?= $da['id'] ?>" class="fw-bold text-dark text-decoration-none">
                                                <?= e($da['title']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold"><?= formatDate($da['activity_date']) ?></div>
                                            <small class="text-muted"><?= e($da['venue']) ?></small>
                                        </td>
                                        <td>
                                            <small class="text-muted">Exp: <?= (int)$da['expected_attendance'] ?></small>
                                            <?php if ($da['actual_attendance']): ?>
                                                <small class="text-success d-block fw-bold">Act: <?= (int)$da['actual_attendance'] ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= getStatusBadge($da['status']) ?></td>
                                        <td>
                                            <?php if ($da['report_id']): ?>
                                                <?= getStatusBadge($da['report_status']) ?>
                                            <?php elseif ($da['status'] === 'Completed'): ?>
                                                <a href="<?= BASE_URL ?>activities/report.php?id=<?= $da['id'] ?>" class="badge bg-warning text-dark text-decoration-none">
                                                    File Report
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small">Upcoming</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?= BASE_URL ?>activities/view.php?id=<?= $da['id'] ?>" class="btn btn-sm btn-light border">
                                                <i class="fas fa-eye text-primary"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 3: FINANCE -->
    <div class="tab-pane fade" id="finance" role="tabpanel">
        <div class="church-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-receipt text-success me-2"></i>Departmental Expenses & Budget Utilization</span>
                <span class="badge bg-light text-dark border">Total Spent: <?= formatCurrency($sumSpent) ?></span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Txn Code</th>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Payee</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($deptExpenses)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No expenses billed to this department.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($deptExpenses as $exp): ?>
                                    <tr>
                                        <td class="small font-monospace text-primary"><?= e($exp['transaction_code']) ?></td>
                                        <td class="small"><?= formatDate($exp['transaction_date']) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= e($exp['category']) ?></span></td>
                                        <td class="small"><?= e($exp['payee']) ?></td>
                                        <td class="fw-bold text-danger"><?= formatCurrency($exp['amount']) ?></td>
                                        <td><?= getStatusBadge($exp['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Assign Member to Department -->
<div class="modal fade" id="addMemberModal" tabindex="-1" aria-labelledby="addMemberModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <?= csrfField(); ?>
                <input type="hidden" name="action_add_member" value="1">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="addMemberModalLabel"><i class="fas fa-user-plus text-primary me-2"></i>Assign Member to <?= e($department['name']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Select Member <span class="text-danger">*</span></label>
                        <select name="member_id" class="form-select" required>
                            <option value="">Choose member...</option>
                            <?php foreach ($availableMembers as $am): ?>
                                <option value="<?= $am['id'] ?>">
                                    <?= e($am['first_name'] . ' ' . $am['last_name']) ?> (<?= e($am['member_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Role in Department</label>
                        <input type="text" name="role_in_dept" class="form-control" placeholder="e.g. Secretary, Tenor Singer, Lead Usher, Member" value="Member">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Member</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

