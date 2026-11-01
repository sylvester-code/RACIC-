<?php
// activities/index.php - Church Activities Monitoring Portal
$pageTitle = 'Church Activities';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$user = currentUser();

// Filter parameters
$statusFilter = $_GET['status'] ?? '';
$deptFilter = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT a.*, d.name as department_name, d.code as department_code, 
           u.full_name as creator_name,
           ar.id as report_id, ar.status as report_status, ar.actual_attendance, ar.expenditure
    FROM activities a
    LEFT JOIN departments d ON d.id = a.department_id
    LEFT JOIN users u ON u.id = a.created_by
    LEFT JOIN activity_reports ar ON ar.activity_id = a.id
    WHERE 1=1
";
$params = [];

if (!empty($statusFilter)) {
    $sql .= " AND a.status = ?";
    $params[] = $statusFilter;
}

if (!empty($deptFilter)) {
    $sql .= " AND a.department_id = ?";
    $params[] = $deptFilter;
}

if (!empty($search)) {
    $sql .= " AND (a.title LIKE ? OR a.activity_code LIKE ? OR a.venue LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY a.activity_date DESC, a.start_time DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$activities = $stmt->fetchAll();

// Fetch departments for filter
$departments = $db->query("SELECT id, name, code FROM departments ORDER BY name ASC")->fetchAll();

// Summary stats
$totalCount = (int)$db->query("SELECT COUNT(*) FROM activities")->fetchColumn();
$upcomingCount = (int)$db->query("SELECT COUNT(*) FROM activities WHERE status = 'Upcoming'")->fetchColumn();
$completedCount = (int)$db->query("SELECT COUNT(*) FROM activities WHERE status = 'Completed'")->fetchColumn();
$pendingReportCount = (int)$db->query("
    SELECT COUNT(*) 
    FROM activities a 
    LEFT JOIN activity_reports ar ON ar.activity_id = a.id 
    WHERE a.status = 'Completed' AND (ar.id IS NULL OR ar.status = 'Submitted')
")->fetchColumn();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">
            <i class="fas fa-clipboard-check text-primary me-2"></i>Church Activity Monitoring
        </h3>
        <p class="text-muted mb-0 small">Schedule programs, monitor department execution, and review post-event evaluation reports.</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <a href="<?= BASE_URL ?>activities/create.php" class="btn btn-primary btn-sm shadow-sm">
            <i class="fas fa-plus-circle me-1"></i>Schedule New Activity
        </a>
        <button class="btn btn-outline-secondary btn-sm" onclick="exportTableToCSV('activitiesTable', 'racic_activities')">
            <i class="fas fa-file-csv me-1"></i>Export CSV
        </button>
        <button class="btn btn-outline-dark btn-sm btn-trigger-print">
            <i class="fas fa-print me-1"></i>Print View
        </button>
    </div>
</div>

<!-- Quick Stat Counters -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-3">
        <div class="stat-card primary p-3">
            <div class="stat-title">Total Activities</div>
            <div class="stat-value fs-4"><?= $totalCount ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="stat-card info p-3">
            <div class="stat-title">Upcoming</div>
            <div class="stat-value fs-4 text-info"><?= $upcomingCount ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="stat-card success p-3">
            <div class="stat-title">Completed</div>
            <div class="stat-value fs-4 text-success"><?= $completedCount ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="stat-card warning p-3">
            <div class="stat-title">Reports Pending/Review</div>
            <div class="stat-value fs-4 text-warning"><?= $pendingReportCount ?></div>
        </div>
    </div>
</div>

<!-- Filters & Search Toolbar -->
<div class="church-card mb-4 no-print">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>activities/index.php" class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search by title, code or venue..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="department_id" class="form-select">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $deptFilter == $d['id'] ? 'selected' : '' ?>>
                            <?= e($d['name']) ?> (<?= e($d['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Activity Statuses</option>
                    <option value="Upcoming" <?= $statusFilter === 'Upcoming' ? 'selected' : '' ?>>Upcoming</option>
                    <option value="In Progress" <?= $statusFilter === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="Completed" <?= $statusFilter === 'Completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="Postponed" <?= $statusFilter === 'Postponed' ? 'selected' : '' ?>>Postponed</option>
                    <option value="Cancelled" <?= $statusFilter === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button>
                <a href="<?= BASE_URL ?>activities/index.php" class="btn btn-light border" title="Reset Filters"><i class="fas fa-redo"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Activities Data Table -->
<div class="church-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list text-primary me-2"></i>Activities Log (<?= count($activities) ?> items)</span>
        <input type="text" class="form-control form-control-sm table-search-input no-print" data-target="activitiesTable" placeholder="Instant filter table..." style="max-width: 200px;">
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="activitiesTable">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Activity Title & Venue</th>
                        <th>Department</th>
                        <th>Schedule</th>
                        <th>Attendance</th>
                        <th>Budget</th>
                        <th>Event Status</th>
                        <th>Post-Event Report</th>
                        <th class="text-end no-export no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($activities)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="fas fa-folder-open fs-2 mb-2 text-secondary"></i>
                                <p class="mb-0">No activities match your criteria.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($activities as $act): ?>
                            <tr>
                                <td class="fw-bold text-primary font-monospace"><?= e($act['activity_code']) ?></td>
                                <td>
                                    <a href="<?= BASE_URL ?>activities/view.php?id=<?= $act['id'] ?>" class="fw-bold text-dark text-decoration-none">
                                        <?= e($act['title']) ?>
                                    </a>
                                    <div class="small text-muted"><i class="fas fa-map-marker-alt text-danger me-1"></i><?= e($act['venue']) ?></div>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= e($act['department_name'] ?? 'General') ?></span></td>
                                <td>
                                    <div class="fw-semibold small"><?= formatDate($act['activity_date']) ?></div>
                                    <small class="text-muted"><?= date('h:i A', strtotime($act['start_time'])) ?> - <?= date('h:i A', strtotime($act['end_time'])) ?></small>
                                </td>
                                <td>
                                    <div class="small">
                                        <span class="text-muted">Exp:</span> <strong><?= (int)$act['expected_attendance'] ?></strong>
                                    </div>
                                    <?php if ($act['actual_attendance']): ?>
                                        <div class="small text-success">
                                            <span>Act:</span> <strong><?= (int)$act['actual_attendance'] ?></strong>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small fw-semibold"><?= formatCurrency($act['budget']) ?></div>
                                    <?php if ($act['expenditure']): ?>
                                        <div class="small text-muted">Spent: <?= formatCurrency($act['expenditure']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= getStatusBadge($act['status']) ?></td>
                                <td>
                                    <?php if ($act['report_id']): ?>
                                        <a href="<?= BASE_URL ?>activities/view.php?id=<?= $act['id'] ?>#report" class="text-decoration-none">
                                            <?= getStatusBadge($act['report_status']) ?>
                                        </a>
                                    <?php elseif ($act['status'] === 'Completed'): ?>
                                        <a href="<?= BASE_URL ?>activities/report.php?id=<?= $act['id'] ?>" class="btn btn-warning btn-xs py-1 px-2 rounded small fw-bold text-dark text-decoration-none">
                                            <i class="fas fa-pen me-1"></i>File Report
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">Upcoming</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end no-export no-print">
                                    <div class="table-actions justify-content-end">
                                        <a href="<?= BASE_URL ?>activities/view.php?id=<?= $act['id'] ?>" class="btn btn-sm btn-light border" title="View Full Activity & Report">
                                            <i class="fas fa-eye text-primary"></i>
                                        </a>
                                        <a href="<?= BASE_URL ?>activities/edit.php?id=<?= $act['id'] ?>" class="btn btn-sm btn-light border" title="Edit Activity">
                                            <i class="fas fa-edit text-secondary"></i>
                                        </a>
                                        <?php if (hasRole(['super_admin', 'head_pastor'])): ?>
                                            <a href="<?= BASE_URL ?>activities/delete.php?id=<?= $act['id'] ?>&csrf=<?= generateCSRF() ?>" class="btn btn-sm btn-light border" title="Delete Activity" data-confirm="Are you sure you want to delete this activity record and all attached reports?">
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

