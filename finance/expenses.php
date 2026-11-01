<?php
// finance/expenses.php - Church Expense Disbursements & Vouchers
$pageTitle = 'Expenditure & Vouchers';
require_once __DIR__ . '/../includes/header.php';
requireRole(['super_admin', 'head_pastor', 'finance_officer']);

$db = getDB();
$user = currentUser();
$errors = [];

// Handle New Expense Entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_record_expense'])) {
    if (!validateCSRF()) {
        $errors[] = "Invalid CSRF security token.";
    } else {
        $category = $_POST['category'] ?? 'Church Programs';
        $departmentId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $activityId = !empty($_POST['activity_id']) ? (int)$_POST['activity_id'] : null;
        $amount = (float)($_POST['amount'] ?? 0);
        $paymentMethod = $_POST['payment_method'] ?? 'Cash';
        $payee = trim($_POST['payee'] ?? '');
        $txnDate = !empty($_POST['transaction_date']) ? $_POST['transaction_date'] : date('Y-m-d');
        $status = $_POST['status'] ?? 'Approved';
        $notes = trim($_POST['notes'] ?? '');

        if ($amount <= 0) $errors[] = "Expense amount must be greater than zero.";
        if (empty($payee)) $errors[] = "Payee or vendor name is required.";

        // Handle receipt upload
        $receiptFile = null;
        if (!empty($_FILES['receipt_file']['name'])) {
            try {
                $receiptFile = uploadFile('receipt_file', 'receipts', ['jpg', 'jpeg', 'png', 'webp', 'pdf']);
            } catch (Exception $e) {
                $errors[] = "Receipt upload error: " . $e->getMessage();
            }
        }

        if (empty($errors)) {
            try {
                $txnCode = generateCode('TXN-EXP-', 'expenses', 'transaction_code');
                $approvedBy = ($status === 'Approved') ? $user['id'] : null;

                $stmt = $db->prepare("
                    INSERT INTO expenses (
                        transaction_code, category, department_id, activity_id,
                        amount, payment_method, payee, transaction_date,
                        receipt_file, status, approved_by_user_id, notes,
                        recorded_by_user_id, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $txnCode, $category, $departmentId, $activityId,
                    $amount, $paymentMethod, $payee, $txnDate,
                    $receiptFile, $status, $approvedBy, $notes,
                    $user['id']
                ]);
                $expenseId = (int)$db->lastInsertId();

                logAudit('RECORD_EXPENSE', 'Finance', $expenseId, "Recorded expense {$txnCode} of " . formatCurrency($amount) . " to {$payee}");
                flash('success', "Disbursement {$txnCode} of " . formatCurrency($amount) . " successfully posted!");
                header("Location: " . BASE_URL . "finance/expenses.php");
                exit;

            } catch (Exception $e) {
                $errors[] = "Error saving expense: " . $e->getMessage();
            }
        }
    }
}

// Handle Status Approval Toggle (Pastoral Oversight)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_approve_expense'])) {
    requireRole(['super_admin', 'head_pastor']);
    if (!validateCSRF()) {
        flash('danger', 'Invalid CSRF token.');
    } else {
        $expId = (int)$_POST['expense_id'];
        $newStatus = $_POST['new_status'] ?? 'Approved';

        $upd = $db->prepare("UPDATE expenses SET status = ?, approved_by_user_id = ? WHERE id = ?");
        $upd->execute([$newStatus, $user['id'], $expId]);

        logAudit('APPROVE_EXPENSE', 'Finance', $expId, "Changed status of expense ID {$expId} to {$newStatus}");
        flash('success', "Expense voucher status updated to {$newStatus}.");
        header("Location: " . BASE_URL . "finance/expenses.php");
        exit;
    }
}

// Filters
$categoryFilter = $_GET['category'] ?? '';
$deptFilter = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$statusFilter = $_GET['status'] ?? '';
$startDate = $_GET['start_date'] ?? date('Y-01-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

$sql = "
    SELECT e.*, d.name as department_name, d.code as department_code,
           act.title as activity_title, act.activity_code,
           u.full_name as recorder_name, app.full_name as approver_name
    FROM expenses e
    LEFT JOIN departments d ON d.id = e.department_id
    LEFT JOIN activities act ON act.id = e.activity_id
    LEFT JOIN users u ON u.id = e.recorded_by_user_id
    LEFT JOIN users app ON app.id = e.approved_by_user_id
    WHERE e.transaction_date BETWEEN ? AND ?
";
$params = [$startDate, $endDate];

if (!empty($categoryFilter)) {
    $sql .= " AND e.category = ?";
    $params[] = $categoryFilter;
}
if (!empty($deptFilter)) {
    $sql .= " AND e.department_id = ?";
    $params[] = $deptFilter;
}
if (!empty($statusFilter)) {
    $sql .= " AND e.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY e.transaction_date DESC, e.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

$totalExpensesFiltered = array_sum(array_column($expenses, 'amount'));

$departments = $db->query("SELECT id, name, code FROM departments ORDER BY name ASC")->fetchAll();
$activities = $db->query("SELECT id, title, activity_code FROM activities ORDER BY activity_date DESC LIMIT 20")->fetchAll();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">
            <i class="fas fa-receipt text-danger me-2"></i>Church Expenditure & Disbursements
        </h3>
        <p class="text-muted mb-0 small">Track operational utilities, benevolence welfare, honorariums, maintenance, and departmental vouchers.</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <button type="button" class="btn btn-danger btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#recordExpenseModal">
            <i class="fas fa-plus-circle me-1"></i>Post Expense Voucher
        </button>
        <button class="btn btn-outline-secondary btn-sm" onclick="exportTableToCSV('expensesTable', 'racic_expenses')">
            <i class="fas fa-file-csv me-1"></i>Export CSV
        </button>
        <button class="btn btn-outline-dark btn-sm btn-trigger-print">
            <i class="fas fa-print me-1"></i>Print View
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

<!-- Filter Toolbar -->
<div class="church-card mb-4 no-print">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>finance/expenses.php" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Category</label>
                <select name="category" class="form-select">
                    <option value="">All Categories</option>
                    <option value="Church Programs" <?= $categoryFilter === 'Church Programs' ? 'selected' : '' ?>>Church Programs</option>
                    <option value="Welfare" <?= $categoryFilter === 'Welfare' ? 'selected' : '' ?>>Welfare</option>
                    <option value="Utilities" <?= $categoryFilter === 'Utilities' ? 'selected' : '' ?>>Utilities</option>
                    <option value="Maintenance" <?= $categoryFilter === 'Maintenance' ? 'selected' : '' ?>>Maintenance</option>
                    <option value="Departmental Expenses" <?= $categoryFilter === 'Departmental Expenses' ? 'selected' : '' ?>>Departmental Expenses</option>
                    <option value="Salaries & Honorariums" <?= $categoryFilter === 'Salaries & Honorariums' ? 'selected' : '' ?>>Salaries & Honorariums</option>
                    <option value="Other" <?= $categoryFilter === 'Other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Department</label>
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
                <label class="form-label small text-muted mb-1">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="Approved" <?= $statusFilter === 'Approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="Pending" <?= $statusFilter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="Rejected" <?= $statusFilter === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">From Date</label>
                <input type="date" name="start_date" class="form-control" value="<?= e($startDate) ?>">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button>
                <a href="<?= BASE_URL ?>finance/expenses.php" class="btn btn-light border"><i class="fas fa-redo"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Expenses Table -->
<div class="church-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list text-danger me-2"></i>Expense Vouchers (<?= count($expenses) ?> records)</span>
        <span class="badge bg-danger fs-6">Total Disbursed: <?= formatCurrency($totalExpensesFiltered) ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="expensesTable">
                <thead>
                    <tr>
                        <th>Voucher #</th>
                        <th>Category</th>
                        <th>Payee & Department</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Receipt</th>
                        <th class="text-end no-print">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($expenses)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="fas fa-receipt fs-2 mb-2 text-secondary"></i>
                                <p class="mb-0">No expenditure vouchers match this criteria.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($expenses as $exp): ?>
                            <tr>
                                <td class="fw-bold font-monospace text-danger"><?= e($exp['transaction_code']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= e($exp['category']) ?></span></td>
                                <td>
                                    <strong class="text-dark"><?= e($exp['payee']) ?></strong>
                                    <div class="small text-muted">
                                        <?= e($exp['department_name'] ?? 'General Church') ?>
                                        <?= $exp['activity_title'] ? ' &bull; ' . e($exp['activity_title']) : '' ?>
                                    </div>
                                </td>
                                <td class="small"><?= formatDate($exp['transaction_date']) ?></td>
                                <td class="fw-bold text-danger fs-6"><?= formatCurrency($exp['amount']) ?></td>
                                <td class="small text-muted"><?= e($exp['payment_method']) ?></td>
                                <td><?= getStatusBadge($exp['status']) ?></td>
                                <td>
                                    <?php if ($exp['receipt_file']): ?>
                                        <a href="<?= UPLOAD_URL . e($exp['receipt_file']) ?>" target="_blank" class="btn btn-sm btn-light border py-0 px-2" title="View Receipt">
                                            <i class="fas fa-paperclip text-primary"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end no-print">
                                    <?php if (hasRole(['super_admin', 'head_pastor']) && $exp['status'] === 'Pending'): ?>
                                        <form method="POST" action="" class="d-inline">
                                            <?= csrfField(); ?>
                                            <input type="hidden" name="action_approve_expense" value="1">
                                            <input type="hidden" name="expense_id" value="<?= $exp['id'] ?>">
                                            <input type="hidden" name="new_status" value="Approved">
                                            <button type="submit" class="btn btn-sm btn-success py-0 px-2" title="Approve Voucher">
                                                <i class="fas fa-check me-1"></i>Approve
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Post New Expense -->
<div class="modal fade" id="recordExpenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="" enctype="multipart/form-data">
                <?= csrfField(); ?>
                <input type="hidden" name="action_record_expense" value="1">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fas fa-receipt text-danger me-2"></i>Post Expense Disbursement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Expense Category <span class="text-danger">*</span></label>
                            <select name="category" class="form-select" required>
                                <option value="Church Programs">Church Programs</option>
                                <option value="Welfare">Welfare & Benevolence</option>
                                <option value="Utilities">Utilities (Power, Water, Internet)</option>
                                <option value="Maintenance">Maintenance & Repairs</option>
                                <option value="Departmental Expenses">Departmental Expenses</option>
                                <option value="Salaries & Honorariums">Salaries & Honorariums</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Amount (<?= CURRENCY_SYMBOL ?>) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="amount" class="form-control form-control-lg fw-bold text-danger" min="0.01" placeholder="0.00" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Payee / Vendor / Recipient <span class="text-danger">*</span></label>
                            <input type="text" name="payee" class="form-control" placeholder="Company or person receiving payment" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Disbursement Date <span class="text-danger">*</span></label>
                            <input type="date" name="transaction_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Charge to Department (Optional)</label>
                            <select name="department_id" class="form-select">
                                <option value="">General Church Fund...</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['id'] ?>">
                                        <?= e($d['name']) ?> (<?= e($d['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Linked Church Activity (Optional)</label>
                            <select name="activity_id" class="form-select">
                                <option value="">None / Routine Operational...</option>
                                <?php foreach ($activities as $a): ?>
                                    <option value="<?= $a['id'] ?>">
                                        <?= e($a['title']) ?> (<?= e($a['activity_code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Payment Method</label>
                            <select name="payment_method" class="form-select">
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Mobile Money">Mobile Money (MoMo)</option>
                                <option value="Cheque">Cheque</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Disbursement Status</label>
                            <select name="status" class="form-select">
                                <option value="Approved" selected>Approved (Instant Disbursal)</option>
                                <option value="Pending">Pending Pastoral Approval</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small">Upload Receipt / Invoice / Supporting Voucher</label>
                            <input type="file" name="receipt_file" class="form-control" accept="image/*,application/pdf">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small">Voucher Purpose & Justification</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Describe the itemized purchases or service provided..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4 shadow-sm">Save Disbursement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

