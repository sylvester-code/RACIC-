<?php
// admin/roles.php - Roles & Permissions Configuration
$pageTitle = 'Roles & Permissions';
require_once __DIR__ . '/../includes/header.php';
requireRole(['super_admin', 'head_pastor']);

$db = getDB();
$user = currentUser();

// Fetch Roles with member count
$roles = $db->query("
    SELECT r.*,
           (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id) as user_count
    FROM roles r
    ORDER BY r.id ASC
")->fetchAll();

// Fetch all permissions grouped by category
$perms = $db->query("SELECT * FROM permissions ORDER BY category ASC, name ASC")->fetchAll();
$groupedPerms = [];
foreach ($perms as $p) {
    $groupedPerms[$p['category']][] = $p;
}

// Fetch role permissions mapping
$rpRows = $db->query("SELECT role_id, permission_id FROM role_permissions")->fetchAll();
$rolePermMap = [];
foreach ($rpRows as $rp) {
    $rolePermMap[$rp['role_id']][] = $rp['permission_id'];
}
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">
            <i class="fas fa-id-badge text-primary me-2"></i>Roles & Access Control Matrix
        </h3>
        <p class="text-muted mb-0 small">Granular system permissions mapped to authorized pastoral, administrative, and worker roles.</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <a href="<?= BASE_URL ?>admin/users.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>User Accounts
        </a>
    </div>
</div>

<!-- Roles Overview Cards -->
<div class="row g-3 mb-4">
    <?php foreach ($roles as $r): ?>
        <div class="col-md-4">
            <div class="church-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark"><?= e($r['display_name']) ?></span>
                    <span class="badge bg-primary"><?= $r['user_count'] ?> Users</span>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-2"><?= e($r['description']) ?></p>
                    <div class="small fw-semibold text-secondary">
                        <i class="fas fa-shield-alt text-warning me-1"></i>
                        <?= count($rolePermMap[$r['id']] ?? []) ?> Active Capabilities
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Detailed Permissions Matrix -->
<div class="church-card">
    <div class="card-header">
        <span><i class="fas fa-th text-primary me-2"></i>System Capabilities by Role</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 30%;">Permission / Capability</th>
                        <th class="text-center">Super Admin</th>
                        <th class="text-center">Head Pastor</th>
                        <th class="text-center">Church Admin</th>
                        <th class="text-center">Dept Leader</th>
                        <th class="text-center">Finance Officer</th>
                        <th class="text-center">Church Worker</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groupedPerms as $cat => $permList): ?>
                        <tr class="table-secondary">
                            <td colspan="7" class="fw-bold text-uppercase small py-1 px-3" style="letter-spacing: 0.5px;">
                                <i class="fas fa-folder-open me-2 text-primary"></i><?= e($cat) ?> Module
                            </td>
                        </tr>
                        <?php foreach ($permList as $perm): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($perm['name']) ?></div>
                                    <small class="text-muted font-monospace" style="font-size: 0.72rem;"><?= e($perm['code']) ?></small>
                                </td>
                                <?php for ($rid = 1; $rid <= 6; $rid++): ?>
                                    <td class="text-center">
                                        <?php if ($rid === 1 || in_array($perm['id'], $rolePermMap[$rid] ?? [])): ?>
                                            <i class="fas fa-check-circle text-success fs-5"></i>
                                        <?php else: ?>
                                            <i class="fas fa-times text-muted opacity-25"></i>
                                        <?php endif; ?>
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

