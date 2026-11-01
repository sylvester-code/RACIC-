<?php
// finance/income.php - Church Income & Tithe Registry
$pageTitle = 'Income & Tithe Registry';
require_once __DIR__ . '/../includes/header.php';
requireRole(['super_admin', 'head_pastor', 'finance_officer']);

$db = getDB();
$user = currentUser();
$errors = [];

// Handle New Income Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_record_income'])) {
    if (!validateCSRF()) {
        $errors[] = "Invalid CSRF security token.";
    } else {
        $category = $_POST['category'] ?? 'Tithes';
        $memberId = !empty($_POST['member_id']) ? (int)$_POST['member_id'] : null;
        $amount = (float)($_POST['amount'] ?? 0);
        $paymentMethod = $_POST['payment_method'] ?? 'Cash';
        $paymentRef = trim($_POST['payment_reference'] ?? '');
        $txnDate = !empty($_POST['transaction_date']) ? $_POST['transaction_date'] : date('Y-m-d');
        $notes = trim($_POST['notes'] ?? '');

        if ($amount <= 0) $errors[] = "Income amount must be greater than zero.";

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
                $txnCode = generateCode('TXN-INC-', 'income', 'transaction_code');

                $stmt = $db->prepare("
                    INSERT INTO income (
                        transaction_code, category, member_id, amount,
                        payment_method, payment_reference, transaction_date,
                        receipt_file, notes, recorded_by_user_id, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $txnCode, $category, $memberId, $amount,
                    $paymentMethod, $paymentRef, $txnDate,
                    $receiptFile, $notes, $user['id']
                ]);
                $incomeId = (int)$db->lastInsertId();

                logAudit('RECORD_INCOME', 'Finance', $incomeId, "Recorded income {$txnCode} of " . formatCurrency($amount) . " ({$category})");
                flash('success', "Income entry {$txnCode} of " . formatCurrency($amount) . " successfully recorded!");
                header("Location: " . BASE_URL . "finance/income.php");
                exit;

            } catch (Exception $e) {
                $errors[] = "Error recording income: " . $e->getMessage();
            }
        }
    }
}

// Filters
$categoryFilter = $_GET['category'] ?? '';
$startDate = $_GET['start_date'] ?? date('Y-01-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

$sql = "
    SELECT i.*, m.first_name, m.last_name, m.member_code, u.full_name as recorder_name
    FROM income i
    LEFT JOIN members m ON m.id = i.member_id
    LEFT JOIN users u ON u.id = i.recorded_by_user_id
    WHERE i.transaction_date BETWEEN ? AND ?
";
$params = [$startDate, $endDate];

if (!empty($categoryFilter)) {
    $sql .= " AND i.category = ?";
    $params[] = $categoryFilter;
}

$sql .= " ORDER BY i.transaction_date DESC, i.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$incomes = $stmt->fetchAll();

$totalIncomeFiltered = array_sum(array_column($incomes, 'amount'));

// Members list for dropdown
$members = $db->query("SELECT id, first_name, last_name, member_code FROM members WHERE membership_status = 'Active' ORDER BY first_name ASC")->fetchAll();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">
            <i class="fas fa-hand-holding-dollar text-success me-2"></i>Income & Giving Registry
        </h3>
        <p class="text-muted mb-0 small">Log tithes, Sunday offerings, special fundraising, and ministry donations.</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <button type="button" class="btn btn-success btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#recordIncomeModal">
            <i class="fas fa-plus-circle me-1"></i>Post New Income
        </button>
        <button class="btn btn-outline-secondary btn-sm" onclick="exportTableToCSV('incomeTable', 'racic_income')">
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
        <form method="GET" action="<?= BASE_URL ?>finance/income.php" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Category</label>
                <select name="category" class="form-select">
                    <option value="">All Income Categories</option>
                    <option value="Tithes" <?= $categoryFilter === 'Tithes' ? 'selected' : '' ?>>Tithes</option>
                    <option value="Offerings" <?= $categoryFilter === 'Offerings' ? 'selected' : '' ?>>Offerings</option>
                    <option value="Donations" <?= $categoryFilter === 'Donations' ? 'selected' : '' ?>>Donations</option>
                    <option value="Fundraising" <?= $categoryFilter === 'Fundraising' ? 'selected' : '' ?>>Fundraising</option>
                    <option value="Special Contributions" <?= $categoryFilter === 'Special Contributions' ? 'selected' : '' ?>>Special Contributions</option>
                    <option value="Other" <?= $categoryFilter === 'Other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">From Date</label>
                <input type="date" name="start_date" class="form-control" value="<?= e($startDate) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">To Date</label>
                <input type="date" name="end_date" class="form-control" value="<?= e($endDate) ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button>
                <a href="<?= BASE_URL ?>finance/income.php" class="btn btn-light border"><i class="fas fa-redo"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Income Table -->
<div class="church-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list text-primary me-2"></i>Income Records (<?= count($incomes) ?> transactions)</span>
        <span class="badge bg-success fs-6">Total: <?= formatCurrency($totalIncomeFiltered) ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="incomeTable">
                <thead>
                    <tr>
                        <th>Txn Code</th>
                        <th>Category</th>
                        <th>Member / Contributor</th>
                        <th>Date</th>
                        <th>Method & Reference</th>
                        <th>Amount</th>
                        <th>Receipt</th>
                        <th>Recorder</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($incomes)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fas fa-receipt fs-2 mb-2 text-secondary"></i>
                                <p class="mb-0">No income records found for this period.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($incomes as $inc): ?>
                            <tr>
                                <td class="fw-bold font-monospace text-primary"><?= e($inc['transaction_code']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= e($inc['category']) ?></span></td>
                                <td>
                                    <?php if ($inc['member_id']): ?>
                                        <a href="<?= BASE_URL ?>members/view.php?id=<?= $inc['member_id'] ?>" class="text-dark fw-bold text-decoration-none">
                                            <?= e($inc['first_name'] . ' ' . $inc['last_name']) ?>
                                        </a>
                                        <div class="small text-muted"><?= e($inc['member_code']) ?></div>
                                    <?php else: ?>
                                        <span class="text-muted small">Congregation / Anonymous</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small"><?= formatDate($inc['transaction_date']) ?></td>
                                <td>
                                    <div class="small fw-semibold"><?= e($inc['payment_method']) ?></div>
                                    <small class="text-muted"><?= e($inc['payment_reference'] ?: '—') ?></small>
                                </td>
                                <td class="fw-bold text-success fs-6"><?= formatCurrency($inc['amount']) ?></td>
                                <td>
                                    <?php if ($inc['receipt_file']): ?>
                                        <a href="<?= UPLOAD_URL . e($inc['receipt_file']) ?>" target="_blank" class="btn btn-sm btn-light border py-0 px-2" title="View Receipt">
                                            <i class="fas fa-paperclip text-primary"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= e($inc['recorder_name']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Post New Income -->
<div class="modal fade" id="recordIncomeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="" enctype="multipart/form-data">
                <?= csrfField(); ?>
                <input type="hidden" name="action_record_income" value="1">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fas fa-hand-holding-dollar text-success me-2"></i>Record Church Income</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Income Stream Category <span class="text-danger">*</span></label>
                            <select name="category" class="form-select" required>
                                <option value="Tithes">Tithes</option>
                                <option value="Offerings">Offerings</option>
                                <option value="Donations">Donations</option>
                                <option value="Fundraising">Fundraising</option>
                                <option value="Special Contributions">Special Contributions</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Amount (<?= CURRENCY_SYMBOL ?>) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="amount" class="form-control form-control-lg fw-bold text-success" min="0.01" placeholder="0.00" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Associated Church Member (For Tithes/Pledges)</label>
                            <select name="member_id" class="form-select">
                                <option value="">Anonymous / General Congregation</option>
                                <?php foreach ($members as $m): ?>
                                    <option value="<?= $m['id'] ?>">
                                        <?= e($m['first_name'] . ' ' . $m['last_name']) ?> (<?= e($m['member_code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Transaction Date <span class="text-danger">*</span></label>
                            <input type="date" name="transaction_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Payment Method</label>
                            <select name="payment_method" class="form-select">
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Mobile Money">Mobile Money (MoMo)</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Card">Debit / Credit Card</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Payment Reference / Cheque #</label>
                            <input type="text" name="payment_reference" class="form-control" placeholder="e.g. TRF-889102 / MoMo Txn ID">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-bold small">Upload Deposit Slip / Bank Receipt / Document</label>
                            <input type="file" name="receipt_file" class="form-control" accept="image/*,application/pdf">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small">Stewardship Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Specific intention, building project allocation, etc."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success px-4 shadow-sm">Save Income Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

