<?php
// admin/audit.php - Security Audit Logs & System Activity Trail
$pageTitle = 'Security Audit Logs';
require_once __DIR__ . '/../includes/header.php';
requireRole(['super_admin', 'head_pastor']);

$db = getDB();
$user = currentUser();

$moduleFilter = $_GET['module'] ?? '';
$actionFilter = $_GET['action'] ?? '';
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT a.*, u.full_name, u.email, r.display_name as role_display
    FROM audit_logs a
    LEFT JOIN users u ON u.id = a.user_id
    LEFT JOIN roles r ON r.id = u.role_id
    WHERE 1=1
";
$params = [];

if (!empty($moduleFilter)) {
    $sql .= " AND a.module = ?";
    $params[] = $moduleFilter;
}
if (!empty($actionFilter)) {
    $sql .= " AND a.action = ?";
    $params[] = $actionFilter;
}
if (!empty($search)) {
    $sql .= " AND (a.details LIKE ? OR a.ip_address LIKE ? OR u.full_name LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term]);
}

$sql .= " ORDER BY a.created_at DESC LIMIT 200";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$modules = $db->query("SELECT DISTINCT module FROM audit_logs ORDER BY module ASC")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">
            <i class="fas fa-shield-alt text-primary me-2"></i>Security Audit Trail & Activity Logs
        </h3>
        <p class="text-muted mb-0 small">Real-time surveillance of user logins, member registration, financial posts, activity creations, and administrative edits.</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm" onclick="exportTableToCSV('auditTable', 'racic_audit_logs')">
            <i class="fas fa-file-csv me-1"></i>Export CSV
        </button>
        <button class="btn btn-outline-dark btn-sm btn-trigger-print">
            <i class="fas fa-print me-1"></i>Print Logs
        </button>
        <a href="<?= BASE_URL ?>admin/users.php" class="btn btn-light border btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Users
        </a>
    </div>
</div>

<!-- Filters -->
<div class="church-card mb-4 no-print">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>admin/audit.php" class="row g-2 align-items-center">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control" placeholder="Search by detail keyword, user, or IP address..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-4">
                <select name="module" class="form-select">
                    <option value="">All System Modules</option>
                    <?php foreach ($modules as $m): ?>
                        <option value="<?= e($m) ?>" <?= $moduleFilter === $m ? 'selected' : '' ?>><?= e($m) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button>
                <a href="<?= BASE_URL ?>admin/audit.php" class="btn btn-light border"><i class="fas fa-redo"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Audit Logs Table -->
<div class="church-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list text-primary me-2"></i>Recent System Activity Audit (<?= count($logs) ?> entries)</span>
        <span class="badge bg-light text-muted border">Protected Immutable Ledger</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 font-monospace" id="auditTable" style="font-size: 0.825rem;">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>User Account</th>
                        <th>Action Code</th>
                        <th>Module</th>
                        <th>Activity Details</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">No audit log records match your filter.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $l): ?>
                            <tr>
                                <td class="text-muted text-nowrap"><?= formatDateTime($l['created_at']) ?></td>
                                <td>
                                    <?php if ($l['user_id']): ?>
                                        <strong class="text-dark"><?= e($l['full_name']) ?></strong>
                                        <div class="text-muted" style="font-size: 0.72rem;"><?= e($l['role_display']) ?></div>
                                    <?php else: ?>
                                        <span class="text-muted">System / Guest</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-light text-primary border"><?= e($l['action']) ?></span></td>
                                <td><span class="badge bg-secondary"><?= e($l['module']) ?></span></td>
                                <td class="text-wrap" style="max-width: 320px;"><?= e($l['details']) ?></td>
                                <td class="text-muted"><?= e($l['ip_address']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

