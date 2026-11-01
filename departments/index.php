<?php
// departments/index.php - Church Departments Directory
$pageTitle = 'Departments';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$user = currentUser();

$sql = "
    SELECT d.*, u.full_name as leader_name, u.email as leader_email, u.phone as leader_phone,
           (SELECT COUNT(*) FROM members m WHERE m.department_id = d.id) as member_count,
           (SELECT COUNT(*) FROM activities a WHERE a.department_id = d.id) as activity_count,
           (SELECT COALESCE(SUM(amount), 0) FROM expenses e WHERE e.department_id = d.id AND e.status = 'Approved') as total_spent
    FROM departments d
    LEFT JOIN users u ON u.id = d.leader_id
    ORDER BY d.name ASC
";
$departments = $db->query($sql)->fetchAll();

$totalDepts = count($departments);
$totalBudget = array_sum(array_column($departments, 'budget_allocation'));
$totalDepartmentMembers = array_sum(array_column($departments, 'member_count'));
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">
            <i class="fas fa-sitemap text-primary me-2"></i>Department Management
        </h3>
        <p class="text-muted mb-0 small">Overview of church ministries, assigned leaders, budgets, and dedicated department portals.</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <?php if (hasRole(['super_admin', 'head_pastor', 'church_admin'])): ?>
            <a href="<?= BASE_URL ?>departments/create.php" class="btn btn-primary btn-sm shadow-sm">
                <i class="fas fa-plus-circle me-1"></i>Create Department
            </a>
        <?php endif; ?>
        <button class="btn btn-outline-secondary btn-sm" onclick="exportTableToCSV('departmentsTable', 'racic_departments')">
            <i class="fas fa-file-csv me-1"></i>Export CSV
        </button>
        <button class="btn btn-outline-dark btn-sm btn-trigger-print">
            <i class="fas fa-print me-1"></i>Print
        </button>
    </div>
</div>

<!-- Quick Stat Counters -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-4">
        <div class="stat-card primary p-3">
            <div class="stat-title">Active Departments</div>
            <div class="stat-value fs-4"><?= $totalDepts ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-md-4">
        <div class="stat-card info p-3">
            <div class="stat-title">Allocated Departmental Members</div>
            <div class="stat-value fs-4 text-info"><?= $totalDepartmentMembers ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-md-4">
        <div class="stat-card secondary p-3">
            <div class="stat-title">Total Annual Budget</div>
            <div class="stat-value fs-4" style="color: #9a730b;"><?= formatCurrency($totalBudget) ?></div>
        </div>
    </div>
</div>

<!-- Department Cards Grid -->
<div class="row g-4 mb-4">
    <?php foreach ($departments as $dept): ?>
        <div class="col-md-6 col-xl-4">
            <div class="church-card h-100 d-flex flex-column">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="badge bg-primary font-monospace"><?= e($dept['code']) ?></span>
                    <?= getStatusBadge($dept['status']) ?>
                </div>
                <div class="card-body flex-grow-1">
                    <h5 class="fw-bold mb-1">
                        <a href="<?= BASE_URL ?>departments/view.php?id=<?= $dept['id'] ?>" class="text-dark text-decoration-none">
                            <?= e($dept['name']) ?>
                        </a>
                    </h5>
                    <p class="text-muted small mb-3" style="min-height: 38px;">
                        <?= e(substr($dept['description'] ?? 'Department activities and ministry operations.', 0, 90)) ?>...
                    </p>

                    <ul class="list-unstyled small mb-3">
                        <li class="mb-1 text-truncate">
                            <i class="fas fa-user-tie text-secondary me-2"></i>
                            <strong>Leader:</strong> <?= e($dept['leader_name'] ?: 'Not Assigned') ?>
                        </li>
                        <li class="mb-1">
                            <i class="fas fa-clock text-info me-2"></i>
                            <strong>Meetings:</strong> <?= e($dept['meeting_schedule'] ?: 'TBD') ?>
                        </li>
                        <li class="mb-1">
                            <i class="fas fa-users text-primary me-2"></i>
                            <strong>Members:</strong> <?= $dept['member_count'] ?> registered
                        </li>
                        <li class="mb-1">
                            <i class="fas fa-wallet text-success me-2"></i>
                            <strong>Budget:</strong> <?= formatCurrency($dept['budget_allocation']) ?>
                        </li>
                    </ul>

                    <!-- Spent vs Budget -->
                    <div class="mb-2">
                        <div class="d-flex justify-content-between text-muted" style="font-size: 0.72rem;">
                            <span>Spent: <?= formatCurrency($dept['total_spent']) ?></span>
                            <span>
                                <?php 
                                    $bPct = $dept['budget_allocation'] > 0 ? round(($dept['total_spent'] / $dept['budget_allocation']) * 100) : 0;
                                    echo $bPct . '%';
                                ?>
                            </span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar <?= $bPct > 100 ? 'bg-danger' : 'bg-success' ?>" style="width: <?= min(100, $bPct) ?>%"></div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white border-top p-2 d-flex justify-content-between align-items-center">
                    <a href="<?= BASE_URL ?>departments/view.php?id=<?= $dept['id'] ?>" class="btn btn-outline-primary btn-sm w-100">
                        <i class="fas fa-external-link-alt me-1"></i>Open Management Portal
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Table View for Export & Printing -->
<div class="church-card d-none print-only">
    <div class="card-body p-0">
        <table class="table" id="departmentsTable">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Department Name</th>
                    <th>Leader</th>
                    <th>Meeting Schedule</th>
                    <th>Members</th>
                    <th>Activities</th>
                    <th>Budget Allocation</th>
                    <th>Spent</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($departments as $d): ?>
                    <tr>
                        <td><?= e($d['code']) ?></td>
                        <td><?= e($d['name']) ?></td>
                        <td><?= e($d['leader_name'] ?: 'None') ?></td>
                        <td><?= e($d['meeting_schedule']) ?></td>
                        <td><?= $d['member_count'] ?></td>
                        <td><?= $d['activity_count'] ?></td>
                        <td><?= formatCurrency($d['budget_allocation']) ?></td>
                        <td><?= formatCurrency($d['total_spent']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

