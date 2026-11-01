<?php
// finance/reports.php - Financial Statements & Audited Ledger Reports
$pageTitle = 'Financial Reports & Statements';
require_once __DIR__ . '/../includes/header.php';
requireRole(['super_admin', 'head_pastor', 'finance_officer']);

$db = getDB();
$user = currentUser();

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Income summary for period
$incStmt = $db->prepare("
    SELECT category, COUNT(*) as txn_count, COALESCE(SUM(amount), 0) as total_amount
    FROM income
    WHERE transaction_date BETWEEN ? AND ?
    GROUP BY category
    ORDER BY total_amount DESC
");
$incStmt->execute([$startDate, $endDate]);
$incomeSummary = $incStmt->fetchAll();
$periodIncomeTotal = array_sum(array_column($incomeSummary, 'total_amount'));

// Expense summary for period
$expStmt = $db->prepare("
    SELECT category, COUNT(*) as txn_count, COALESCE(SUM(amount), 0) as total_amount
    FROM expenses
    WHERE status = 'Approved' AND transaction_date BETWEEN ? AND ?
    GROUP BY category
    ORDER BY total_amount DESC
");
$expStmt->execute([$startDate, $endDate]);
$expenseSummary = $expStmt->fetchAll();
$periodExpenseTotal = array_sum(array_column($expenseSummary, 'total_amount'));

$periodNet = $periodIncomeTotal - $periodExpenseTotal;
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">
            <i class="fas fa-file-invoice-dollar text-primary me-2"></i>Financial Statement of Accounts
        </h3>
        <p class="text-muted mb-0 small">Official income and expenditure statement for audited stewardship reporting.</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm" onclick="exportTableToCSV('finReportTable', 'racic_financial_statement')">
            <i class="fas fa-file-csv me-1"></i>Export CSV
        </button>
        <button class="btn btn-outline-dark btn-sm btn-trigger-print">
            <i class="fas fa-print me-1"></i>Print Statement
        </button>
        <a href="<?= BASE_URL ?>finance/index.php" class="btn btn-light border btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<!-- Date Filter Box -->
<div class="church-card mb-4 no-print">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>finance/reports.php" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Statement Period (From)</label>
                <input type="date" name="start_date" class="form-control" value="<?= e($startDate) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Statement Period (To)</label>
                <input type="date" name="end_date" class="form-control" value="<?= e($endDate) ?>">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-calculator me-1"></i>Generate Statement</button>
                <a href="<?= BASE_URL ?>finance/reports.php" class="btn btn-light border"><i class="fas fa-redo"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Statement Print Header (Visible in print) -->
<div class="print-only text-center mb-4">
    <h2 class="fw-bold mb-0"><?= CHURCH_NAME ?></h2>
    <div class="text-uppercase small"><?= CHURCH_SLOGAN ?></div>
    <div class="small text-muted"><?= CHURCH_ADDRESS ?> &bull; <?= CHURCH_PHONE ?></div>
    <hr>
    <h4 class="fw-bold mt-2">STATEMENT OF CHURCH FINANCIAL OPERATIONS</h4>
    <div class="small">Period: <?= formatDate($startDate) ?> to <?= formatDate($endDate) ?></div>
</div>

<!-- Statement Cards Summary -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card success p-3">
            <div class="stat-title">Period Inflow (Income)</div>
            <div class="stat-value text-success fs-4"><?= formatCurrency($periodIncomeTotal) ?></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card danger p-3">
            <div class="stat-title">Period Outflow (Expenses)</div>
            <div class="stat-value text-danger fs-4"><?= formatCurrency($periodExpenseTotal) ?></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card <?= $periodNet >= 0 ? 'primary' : 'warning' ?> p-3">
            <div class="stat-title">Net Operating Surplus / (Deficit)</div>
            <div class="stat-value <?= $periodNet >= 0 ? 'text-primary' : 'text-danger' ?> fs-4"><?= formatCurrency($periodNet) ?></div>
        </div>
    </div>
</div>

<div class="row g-4" id="finReportTable">
    <!-- Income Statement Table -->
    <div class="col-lg-6">
        <div class="church-card h-100">
            <div class="card-header bg-success bg-opacity-10 text-success d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fas fa-arrow-down me-2"></i>Income Streams Breakdown</span>
                <span class="badge bg-success"><?= formatCurrency($periodIncomeTotal) ?></span>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Stream Category</th>
                            <th class="text-center">Transactions</th>
                            <th class="text-end">Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($incomeSummary)): ?>
                            <tr>
                                <td colspan="3" class="text-center py-4 text-muted">No income posted in this date range.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($incomeSummary as $row): ?>
                                <tr>
                                    <td class="fw-semibold text-dark"><?= e($row['category']) ?></td>
                                    <td class="text-center text-muted"><?= $row['txn_count'] ?></td>
                                    <td class="text-end fw-bold text-success"><?= formatCurrency($row['total_amount']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="table-light fw-bold">
                                <td>TOTAL INFLOW</td>
                                <td class="text-center"><?= array_sum(array_column($incomeSummary, 'txn_count')) ?></td>
                                <td class="text-end text-success"><?= formatCurrency($periodIncomeTotal) ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Expenditure Statement Table -->
    <div class="col-lg-6">
        <div class="church-card h-100">
            <div class="card-header bg-danger bg-opacity-10 text-danger d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fas fa-arrow-up me-2"></i>Disbursement Expenditure Breakdown</span>
                <span class="badge bg-danger"><?= formatCurrency($periodExpenseTotal) ?></span>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Expenditure Category</th>
                            <th class="text-center">Vouchers</th>
                            <th class="text-end">Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($expenseSummary)): ?>
                            <tr>
                                <td colspan="3" class="text-center py-4 text-muted">No expenses posted in this date range.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($expenseSummary as $row): ?>
                                <tr>
                                    <td class="fw-semibold text-dark"><?= e($row['category']) ?></td>
                                    <td class="text-center text-muted"><?= $row['txn_count'] ?></td>
                                    <td class="text-end fw-bold text-danger"><?= formatCurrency($row['total_amount']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="table-light fw-bold">
                                <td>TOTAL OUTFLOW</td>
                                <td class="text-center"><?= array_sum(array_column($expenseSummary, 'txn_count')) ?></td>
                                <td class="text-end text-danger"><?= formatCurrency($periodExpenseTotal) ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Signoff / Audit Signature Section for Printed Statements -->
<div class="row mt-5 pt-4 print-only">
    <div class="col-4 text-center">
        <div class="border-top pt-2">
            <strong>Head Pastor</strong><br>
            <small class="text-muted">Signature & Date</small>
        </div>
    </div>
    <div class="col-4 text-center">
        <div class="border-top pt-2">
            <strong>Finance Officer</strong><br>
            <small class="text-muted">Signature & Date</small>
        </div>
    </div>
    <div class="col-4 text-center">
        <div class="border-top pt-2">
            <strong>Church Auditor</strong><br>
            <small class="text-muted">Signature & Date</small>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

