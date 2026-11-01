<?php
// visitors/index.php - Church Visitor Management Portal
$pageTitle = 'Visitor Management';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$user = currentUser();

// Filter parameters
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['follow_up_status'] ?? '';

$sql = "
    SELECT v.*, m.first_name as inviter_first, m.last_name as inviter_last,
           u.full_name as assigned_worker_name,
           (SELECT COUNT(*) FROM visitor_followups vf WHERE vf.visitor_id = v.id) as followup_count
    FROM visitors v
    LEFT JOIN members m ON m.id = v.invited_by_member_id
    LEFT JOIN users u ON u.id = v.assigned_to_user_id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (v.first_name LIKE ? OR v.last_name LIKE ? OR v.visitor_code LIKE ? OR v.phone LIKE ?)";
    $term = "%{$search}%";
    $params = [$term, $term, $term, $term];
}

if (!empty($statusFilter)) {
    $sql .= " AND v.follow_up_status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY v.visit_date DESC, v.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$visitors = $stmt->fetchAll();

// Statistics
$totalVisitors = (int)$db->query("SELECT COUNT(*) FROM visitors")->fetchColumn();
$newVisitors = (int)$db->query("SELECT COUNT(*) FROM visitors WHERE follow_up_status = 'New'")->fetchColumn();
$convertedVisitors = (int)$db->query("SELECT COUNT(*) FROM visitors WHERE follow_up_status = 'Converted'")->fetchColumn();
$conversionRate = $totalVisitors > 0 ? round(($convertedVisitors / $totalVisitors) * 100) : 0;
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">
            <i class="fas fa-user-friends text-primary me-2"></i>Visitor Management & Retention
        </h3>
        <p class="text-muted mb-0 small">Track first-time guests, nurture them through structured follow-ups, and convert visitors into active church members.</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <a href="<?= BASE_URL ?>visitors/create.php" class="btn btn-primary btn-sm shadow-sm">
            <i class="fas fa-plus-circle me-1"></i>Register New Visitor
        </a>
        <button class="btn btn-outline-secondary btn-sm" onclick="exportTableToCSV('visitorsTable', 'racic_visitors')">
            <i class="fas fa-file-csv me-1"></i>Export CSV
        </button>
        <button class="btn btn-outline-dark btn-sm btn-trigger-print">
            <i class="fas fa-print me-1"></i>Print List
        </button>
    </div>
</div>

<!-- Visitor Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-3">
        <div class="stat-card primary p-3">
            <div class="stat-title">Total Visitors</div>
            <div class="stat-value fs-4"><?= $totalVisitors ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="stat-card warning p-3">
            <div class="stat-title">Uncontacted / New</div>
            <div class="stat-value fs-4 text-warning"><?= $newVisitors ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="stat-card success p-3">
            <div class="stat-title">Converted Members</div>
            <div class="stat-value fs-4 text-success"><?= $convertedVisitors ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="stat-card info p-3">
            <div class="stat-title">Retention Rate</div>
            <div class="stat-value fs-4 text-info"><?= $conversionRate ?>%</div>
        </div>
    </div>
</div>

<!-- Filters Toolbar -->
<div class="church-card mb-4 no-print">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>visitors/index.php" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search by visitor name, phone, code..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select name="follow_up_status" class="form-select">
                    <option value="">All Follow-up Statuses</option>
                    <option value="New" <?= $statusFilter === 'New' ? 'selected' : '' ?>>New</option>
                    <option value="Contacted" <?= $statusFilter === 'Contacted' ? 'selected' : '' ?>>Contacted</option>
                    <option value="Visited" <?= $statusFilter === 'Visited' ? 'selected' : '' ?>>Visited</option>
                    <option value="Prayed With" <?= $statusFilter === 'Prayed With' ? 'selected' : '' ?>>Prayed With</option>
                    <option value="Converted" <?= $statusFilter === 'Converted' ? 'selected' : '' ?>>Converted to Member</option>
                    <option value="Not Interested" <?= $statusFilter === 'Not Interested' ? 'selected' : '' ?>>Not Interested</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button>
                <a href="<?= BASE_URL ?>visitors/index.php" class="btn btn-light border" title="Reset Filters"><i class="fas fa-redo"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Visitors Table -->
<div class="church-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list text-primary me-2"></i>Visitor Records (<?= count($visitors) ?> guests)</span>
        <input type="text" class="form-control form-control-sm table-search-input no-print" data-target="visitorsTable" placeholder="Instant filter table..." style="max-width: 200px;">
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="visitorsTable">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Visitor Name</th>
                        <th>Contact</th>
                        <th>Visit Date</th>
                        <th>Invited By</th>
                        <th>Follow-up Status</th>
                        <th>Interactions</th>
                        <th class="text-end no-export no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($visitors)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fas fa-user-friends fs-2 mb-2 text-secondary"></i>
                                <p class="mb-0">No visitor records found.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($visitors as $v): ?>
                            <tr>
                                <td class="fw-bold text-primary font-monospace"><?= e($v['visitor_code']) ?></td>
                                <td>
                                    <a href="<?= BASE_URL ?>visitors/view.php?id=<?= $v['id'] ?>" class="text-dark fw-bold text-decoration-none">
                                        <?= e($v['first_name'] . ' ' . $v['last_name']) ?>
                                    </a>
                                    <div class="small text-muted"><?= e($v['gender']) ?> &bull; <?= e($v['address'] ?: 'No address') ?></div>
                                </td>
                                <td>
                                    <div class="small fw-semibold"><?= e($v['phone']) ?></div>
                                    <small class="text-muted"><?= e($v['email'] ?: '—') ?></small>
                                </td>
                                <td class="small fw-semibold"><?= formatDate($v['visit_date']) ?></td>
                                <td class="small">
                                    <?php if ($v['invited_by_member_id']): ?>
                                        <i class="fas fa-user-tag text-muted me-1"></i><?= e($v['inviter_first'] . ' ' . $v['inviter_last']) ?>
                                    <?php else: ?>
                                        <span class="text-muted"><?= e($v['invited_by_name'] ?: 'None / Walk-in') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= getStatusBadge($v['follow_up_status']) ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <i class="fas fa-comments text-primary me-1"></i><?= $v['followup_count'] ?> logs
                                    </span>
                                </td>
                                <td class="text-end no-export no-print">
                                    <div class="table-actions justify-content-end">
                                        <a href="<?= BASE_URL ?>visitors/view.php?id=<?= $v['id'] ?>" class="btn btn-sm btn-light border" title="View Profile & Prayer Requests">
                                            <i class="fas fa-eye text-primary"></i>
                                        </a>
                                        <a href="<?= BASE_URL ?>visitors/follow-up.php?id=<?= $v['id'] ?>" class="btn btn-sm btn-light border" title="Log Follow-up Call/Visit">
                                            <i class="fas fa-phone-volume text-success"></i>
                                        </a>
                                        <?php if ($v['follow_up_status'] !== 'Converted'): ?>
                                            <a href="<?= BASE_URL ?>visitors/convert.php?id=<?= $v['id'] ?>" class="btn btn-sm btn-light border" title="Convert to Church Member">
                                                <i class="fas fa-user-check text-warning"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (hasRole(['super_admin', 'head_pastor'])): ?>
                                            <a href="<?= BASE_URL ?>visitors/delete.php?id=<?= $v['id'] ?>&csrf=<?= generateCSRF() ?>" class="btn btn-sm btn-light border" title="Delete Visitor" data-confirm="Are you sure you want to delete this visitor record?">
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

