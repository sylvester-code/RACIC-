<?php
// finance/budgets.php - Departmental Budgets & Stewardship Allocation
$pageTitle = 'Departmental Budgets';
require_once __DIR__ . '/../includes/header.php';
requireRole(['super_admin', 'head_pastor', 'finance_officer']);

$db = getDB();
$user = currentUser();
$errors = [];
$currentYear = (int)date('Y');
$selectedYear = !empty($_GET['year']) ? (int)$_GET['year'] : $currentYear;

// Handle New Budget Creation / Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_budget'])) {
    if (!validateCSRF()) {
        $errors[] = "Invalid CSRF security token.";
    } else {
        $deptId = (int)$_POST['department_id'];
        $fiscalYear = (int)$_POST['fiscal_year'];
        $title = trim($_POST['title'] ?? '');
        $allocated = (float)($_POST['allocated_amount'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        if ($deptId <= 0) $errors[] = "Please select a department.";
        if (empty($title)) $errors[] = "Budget title is required.";
        if ($allocated <= 0) $errors[] = "Allocated amount must be greater than zero.";

        if (empty($errors)) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO budgets (department_id, fiscal_year, title, allocated_amount, notes, created_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$deptId, $fiscalYear, $title, $allocated, $notes, $user['id']]);
                $budgetId = (int)$db->lastInsertId();

                // Also sync departments.budget_allocation
                $updDept = $db->prepare("UPDATE departments SET budget_allocation = ? WHERE id = ?");
                $updDept->execute([$allocated, $deptId]);

                logAudit('CREATE_BUDGET', 'Finance', $budgetId, "Allocated budget of " . formatCurrency($allocated) . " to department ID {$deptId} for FY{$fiscalYear}");
                flash('success', "Budget allocation for FY{$fiscalYear} successfully saved!");
                header("Location: " . BASE_URL . "finance/budgets.php?year={$fiscalYear}");
                exit;

            } catch (Exception $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Fetch budgets with actual spent figures
$sql = "
    SELECT b.*, d.name as department_name, d.code as department_code,
           u.full_name as creator_name,
           (SELECT COALESCE(SUM(e.amount), 0) 
            FROM expenses e 
            WHERE e.department_id = b.department_id 
              AND e.status = 'Approved' 
              AND YEAR(e.transaction_date) = b.fiscal_year) as actual_spent
    FROM budgets b
    INNER JOIN departments d ON d.id = b.department_id
    LEFT JOIN users u ON u.id = b.created_by
    WHERE b.fiscal_year = ?
    ORDER BY b.allocated_amount DESC
";
$stmt = $db->prepare($sql);
$stmt->execute([$selectedYear]);
$budgets = $stmt->fetchAll();

$totalAllocated = array_sum(array_column($budgets, 'allocated_amount'));
$totalSpent = array_sum(array_column($budgets, 'actual_spent'));
$totalRemaining = $totalAllocated - $totalSpent;

$departments = $db->query("SELECT id, name, code FROM departments WHERE status = 'active' ORDER BY name ASC")->fetchAll();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">
            <i class="fas fa-wallet text-primary me-2"></i>Departmental Budgets (FY <?= $selectedYear ?>)
        </h3>
        <p class="text-muted mb-0 small">Allocate annual ministry expenditure limits and monitor burn rates across church departments.</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#createBudgetModal">
            <i class="fas fa-plus-circle me-1"></i>New Budget Allocation
        </button>
        <button class="btn btn-outline-secondary btn-sm" onclick="exportTableToCSV('budgetsTable', 'racic_budgets')">
            <i class="fas fa-file-csv me-1"></i>Export CSV
        </button>
        <a href="<?= BASE_URL ?>finance/index.php" class="btn btn-light border btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Finance Hub
        </a>
    </div>
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

<!-- Fiscal Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-4">
        <div class="stat-card primary p-3">
            <div class="stat-title">Total Allocated Budget</div>
            <div class="stat-value fs-4"><?= formatCurrency($totalAllocated) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-md-4">
        <div class="stat-card danger p-3">
            <div class="stat-title">Total Departmental Spent</div>
            <div class="stat-value text-danger fs-4"><?= formatCurrency($totalSpent) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-md-4">
        <div class="stat-card success p-3">
            <div class="stat-title">Remaining Unspent Balance</div>
            <div class="stat-value text-success fs-4"><?= formatCurrency($totalRemaining) ?></div>
        </div>
    </div>
</div>

<!-- Budgets Table -->
<div class="church-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-chart-pie text-primary me-2"></i>Ministry Budget Allocations</span>
        <div class="d-flex align-items-center gap-2">
            <label class="small text-muted mb-0">Fiscal Year:</label>
            <select class="form-select form-select-sm" style="width: 100px;" onchange="window.location.href='?year=' + this.value">
                <option value="2026" <?= $selectedYear == 2026 ? 'selected' : '' ?>>2026</option>
                <option value="2025" <?= $selectedYear == 2025 ? 'selected' : '' ?>>2025</option>
                <option value="2027" <?= $selectedYear == 2027 ? 'selected' : '' ?>>2027</option>
            </select>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="budgetsTable">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Budget Title</th>
                        <th>Allocated</th>
                        <th>Actual Spent</th>
                        <th>Remaining</th>
                        <th style="width: 25%;">Utilization Burn Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($budgets)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">No budget allocations found for FY <?= $selectedYear ?>.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($budgets as $b): ?>
                            <?php 
                                $pct = $b['allocated_amount'] > 0 ? round(($b['actual_spent'] / $b['allocated_amount']) * 100) : 0;
                                $rem = $b['allocated_amount'] - $b['actual_spent'];
                            ?>
                            <tr>
                                <td>
                                    <strong><?= e($b['department_name']) ?></strong>
                                    <span class="badge bg-light text-dark border ms-1 font-monospace"><?= e($b['department_code']) ?></span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($b['title']) ?></div>
                                    <small class="text-muted"><?= e($b['notes'] ?: 'Annual ministry allocation') ?></small>
                                </td>
                                <td class="fw-bold text-dark"><?= formatCurrency($b['allocated_amount']) ?></td>
                                <td class="fw-bold text-danger"><?= formatCurrency($b['actual_spent']) ?></td>
                                <td class="fw-bold text-success"><?= formatCurrency($rem) ?></td>
                                <td>
                                    <div class="d-flex justify-content-between small text-muted mb-1">
                                        <span><?= $pct ?>% Spent</span>
                                        <span><?= 100 - $pct ?>% Left</span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar <?= $pct > 90 ? 'bg-danger' : ($pct > 70 ? 'bg-warning' : 'bg-primary') ?>" style="width: <?= min(100, $pct) ?>%"></div>
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

<!-- Modal: Allocate Budget -->
<div class="modal fade" id="createBudgetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <?= csrfField(); ?>
                <input type="hidden" name="action_save_budget" value="1">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fas fa-wallet text-primary me-2"></i>Set Department Budget</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Department <span class="text-danger">*</span></label>
                        <select name="department_id" class="form-select" required>
                            <option value="">Select Department...</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>">
                                    <?= e($d['name']) ?> (<?= e($d['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Fiscal Year <span class="text-danger">*</span></label>
                        <input type="number" name="fiscal_year" class="form-control" value="<?= $currentYear ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Budget Title / Description <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Annual Ministry Operations, Equipment & Outreaches" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Allocated Amount (<?= CURRENCY_SYMBOL ?>) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="allocated_amount" class="form-control form-control-lg fw-bold" placeholder="0.00" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Notes / Constraints</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Conditions, milestone disbursement instructions..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Save Budget Allocation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

