<?php
// members/index.php - Church Member Directory
$pageTitle = 'Member Directory';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$user = currentUser();

// Filter parameters
$search = trim($_GET['search'] ?? '');
$deptFilter = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$statusFilter = $_GET['membership_status'] ?? '';
$baptismFilter = $_GET['baptism_status'] ?? '';
$genderFilter = $_GET['gender'] ?? '';

$sql = "
    SELECT m.*, d.name as department_name, d.code as department_code
    FROM members m
    LEFT JOIN departments d ON d.id = m.department_id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (m.first_name LIKE ? OR m.last_name LIKE ? OR m.member_code LIKE ? OR m.phone LIKE ? OR m.email LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term, $term]);
}

if (!empty($deptFilter)) {
    $sql .= " AND m.department_id = ?";
    $params[] = $deptFilter;
}

if (!empty($statusFilter)) {
    $sql .= " AND m.membership_status = ?";
    $params[] = $statusFilter;
}

if (!empty($baptismFilter)) {
    $sql .= " AND m.baptism_status = ?";
    $params[] = $baptismFilter;
}

if (!empty($genderFilter)) {
    $sql .= " AND m.gender = ?";
    $params[] = $genderFilter;
}

$sql .= " ORDER BY m.first_name ASC, m.last_name ASC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll();

// Statistics
$totalMembers = (int)$db->query("SELECT COUNT(*) FROM members")->fetchColumn();
$activeCount = (int)$db->query("SELECT COUNT(*) FROM members WHERE membership_status = 'Active'")->fetchColumn();
$baptizedCount = (int)$db->query("SELECT COUNT(*) FROM members WHERE baptism_status = 'Baptized'")->fetchColumn();
$maleCount = (int)$db->query("SELECT COUNT(*) FROM members WHERE gender = 'Male'")->fetchColumn();
$femaleCount = (int)$db->query("SELECT COUNT(*) FROM members WHERE gender = 'Female'")->fetchColumn();

$departments = $db->query("SELECT id, name, code FROM departments ORDER BY name ASC")->fetchAll();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">
            <i class="fas fa-users text-primary me-2"></i>Member Management
        </h3>
        <p class="text-muted mb-0 small">Maintain church membership registry, baptismal records, and departmental assignments.</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <a href="<?= BASE_URL ?>members/create.php" class="btn btn-primary btn-sm shadow-sm">
            <i class="fas fa-user-plus me-1"></i>Register New Member
        </a>
        <button class="btn btn-outline-secondary btn-sm" onclick="exportTableToCSV('membersTable', 'racic_members')">
            <i class="fas fa-file-csv me-1"></i>Export CSV
        </button>
        <button class="btn btn-outline-dark btn-sm btn-trigger-print">
            <i class="fas fa-print me-1"></i>Print Registry
        </button>
    </div>
</div>

<!-- Statistical Summary Bar -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-3">
        <div class="stat-card primary p-3">
            <div class="stat-title">Total Members</div>
            <div class="stat-value fs-4"><?= $totalMembers ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="stat-card success p-3">
            <div class="stat-title">Active Members</div>
            <div class="stat-value fs-4 text-success"><?= $activeCount ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="stat-card info p-3">
            <div class="stat-title">Baptized</div>
            <div class="stat-value fs-4 text-info"><?= $baptizedCount ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="stat-card secondary p-3">
            <div class="stat-title">Demographics</div>
            <div class="small fw-bold text-dark mt-1">
                <i class="fas fa-mars text-primary me-1"></i><?= $maleCount ?> Men &bull; 
                <i class="fas fa-venus text-danger me-1"></i><?= $femaleCount ?> Women
            </div>
        </div>
    </div>
</div>

<!-- Filters Toolbar -->
<div class="church-card mb-4 no-print">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>members/index.php" class="row g-2 align-items-center">
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Name, code, phone..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="department_id" class="form-select">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $deptFilter == $d['id'] ? 'selected' : '' ?>>
                            <?= e($d['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="membership_status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="Active" <?= $statusFilter === 'Active' ? 'selected' : '' ?>>Active</option>
                    <option value="Inactive" <?= $statusFilter === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="Transferred" <?= $statusFilter === 'Transferred' ? 'selected' : '' ?>>Transferred</option>
                    <option value="Deceased" <?= $statusFilter === 'Deceased' ? 'selected' : '' ?>>Deceased</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="baptism_status" class="form-select">
                    <option value="">All Baptism</option>
                    <option value="Baptized" <?= $baptismFilter === 'Baptized' ? 'selected' : '' ?>>Baptized</option>
                    <option value="Not Baptized" <?= $baptismFilter === 'Not Baptized' ? 'selected' : '' ?>>Not Baptized</option>
                    <option value="In Training" <?= $baptismFilter === 'In Training' ? 'selected' : '' ?>>In Training</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button>
                <a href="<?= BASE_URL ?>members/index.php" class="btn btn-light border" title="Reset Filters"><i class="fas fa-redo"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Members Table -->
<div class="church-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-address-book text-primary me-2"></i>Members Directory (<?= count($members) ?> records)</span>
        <input type="text" class="form-control form-control-sm table-search-input no-print" data-target="membersTable" placeholder="Instant filter table..." style="max-width: 200px;">
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="membersTable">
                <thead>
                    <tr>
                        <th>Member Code</th>
                        <th>Member Name</th>
                        <th>Contact</th>
                        <th>Department</th>
                        <th>Baptism Status</th>
                        <th>Membership</th>
                        <th>Joined Date</th>
                        <th class="text-end no-export no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($members)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fas fa-user-slash fs-2 mb-2 text-secondary"></i>
                                <p class="mb-0">No member records found.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($members as $m): ?>
                            <tr>
                                <td class="fw-bold text-primary font-monospace"><?= e($m['member_code']) ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if (!empty($m['photo'])): ?>
                                            <img src="<?= UPLOAD_URL . e($m['photo']) ?>" alt="Photo" class="rounded-circle" style="width: 36px; height: 36px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-light text-primary border d-flex align-items-center justify-content-center fw-bold" style="width: 36px; height: 36px; font-size: 0.85rem;">
                                                <?= strtoupper(substr($m['first_name'], 0, 1) . substr($m['last_name'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <a href="<?= BASE_URL ?>members/view.php?id=<?= $m['id'] ?>" class="text-dark fw-bold text-decoration-none">
                                                <?= e($m['first_name'] . ' ' . $m['last_name']) ?>
                                            </a>
                                            <div class="small text-muted">
                                                <?= e($m['gender']) ?> &bull; <?= e($m['marital_status']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="small fw-semibold"><?= e($m['phone']) ?></div>
                                    <small class="text-muted"><?= e($m['email'] ?: 'No email') ?></small>
                                </td>
                                <td>
                                    <?php if ($m['department_name']): ?>
                                        <span class="badge bg-light text-dark border"><?= e($m['department_name']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">None</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= getStatusBadge($m['baptism_status']) ?></td>
                                <td><?= getStatusBadge($m['membership_status']) ?></td>
                                <td class="small"><?= formatDate($m['membership_date']) ?></td>
                                <td class="text-end no-export no-print">
                                    <div class="table-actions justify-content-end">
                                        <a href="<?= BASE_URL ?>members/view.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-light border" title="View Profile">
                                            <i class="fas fa-eye text-primary"></i>
                                        </a>
                                        <a href="<?= BASE_URL ?>members/edit.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-light border" title="Edit Member">
                                            <i class="fas fa-edit text-secondary"></i>
                                        </a>
                                        <?php if (hasRole(['super_admin', 'head_pastor'])): ?>
                                            <a href="<?= BASE_URL ?>members/delete.php?id=<?= $m['id'] ?>&csrf=<?= generateCSRF() ?>" class="btn btn-sm btn-light border" title="Delete Member" data-confirm="Are you sure you want to delete this member? All attendance and records linked to this member will be detached.">
                                                <i class="fas fa-trash text-danger"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

