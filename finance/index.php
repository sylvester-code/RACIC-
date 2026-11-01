<?php
// finance/index.php - Church Finance & Stewardship Portal
$pageTitle = 'Finance Management';
require_once __DIR__ . '/../includes/header.php';
requireRole(['super_admin', 'head_pastor', 'finance_officer']);

$db = getDB();
$user = currentUser();

// 1. Overall Financial Figures
$totalIncome = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM income")->fetchColumn();
$totalExpenses = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE status = 'Approved'")->fetchColumn();
$pendingExpenses = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE status = 'Pending'")->fetchColumn();
$netSurplus = $totalIncome - $totalExpenses;

// 2. Breakdown by Category (Income)
$incomeByCategory = $db->query("
    SELECT category, COALESCE(SUM(amount), 0) as total 
    FROM income 
    GROUP BY category 
    ORDER BY total DESC
")->fetchAll();

// 3. Breakdown by Category (Expenses)
$expenseByCategory = $db->query("
    SELECT category, COALESCE(SUM(amount), 0) as total 
    FROM expenses 
    WHERE status = 'Approved'
    GROUP BY category 
    ORDER BY total DESC
")->fetchAll();

// 4. Recent Income Records
$recentIncome = $db->query("
    SELECT i.*, m.first_name, m.last_name, m.member_code, u.full_name as recorder_name
    FROM income i
    LEFT JOIN members m ON m.id = i.member_id
    LEFT JOIN users u ON u.id = i.recorded_by_user_id
    ORDER BY i.transaction_date DESC, i.id DESC
    LIMIT 6
")->fetchAll();

// 5. Recent Expenses Records
$recentExpenses = $db->query("
    SELECT e.*, d.name as department_name, u.full_name as recorder_name
    FROM expenses e
    LEFT JOIN departments d ON d.id = e.department_id
    LEFT JOIN users u ON u.id = e.recorded_by_user_id
    ORDER BY e.transaction_date DESC, e.id DESC
    LIMIT 6
")->fetchAll();

// 6. Monthly Trend for Chart (Last 6 months)
$months = [];
$incData = [];
$expData = [];
for ($i = 5; $i >= 0; $i--) {
    $mDate = date('Y-m', strtotime("-{$i} months"));
    $mLabel = date('M Y', strtotime("-{$i} months"));
    $months[] = $mLabel;

    $mInc = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM income WHERE DATE_FORMAT(transaction_date, '%Y-%m') = '{$mDate}'")->fetchColumn();
    $mExp = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE status = 'Approved' AND DATE_FORMAT(transaction_date, '%Y-%m') = '{$mDate}'")->fetchColumn();

    $incData[] = $mInc;
    $expData[] = $mExp;
}
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">
            <i class="fas fa-coins text-warning me-2"></i>Finance & Stewardship Center
        </h3>
        <p class="text-muted mb-0 small">Manage tithes, offerings, donations, expenditures, departmental budgets, and financial audits.</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <a href="<?= BASE_URL ?>finance/income.php" class="btn btn-success btn-sm shadow-sm">
            <i class="fas fa-hand-holding-dollar me-1"></i>Record Income
        </a>
        <a href="<?= BASE_URL ?>finance/expenses.php" class="btn btn-danger btn-sm shadow-sm">
            <i class="fas fa-receipt me-1"></i>Disburse Expense
        </a>
        <a href="<?= BASE_URL ?>finance/budgets.php" class="btn btn-primary btn-sm shadow-sm">
            <i class="fas fa-wallet me-1"></i>Budgets
        </a>
        <a href="<?= BASE_URL ?>finance/reports.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-file-invoice-dollar me-1"></i>Financial Statements
        </a>
    </div>
</div>

<!-- Financial Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-3">
        <div class="stat-card success p-3">
            <div class="stat-title">Total Church Income</div>
            <div class="stat-value text-success fs-4"><?= formatCurrency($totalIncome) ?></div>
            <small class="text-muted">Tithes, Offerings & Gifts</small>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="stat-card danger p-3">
            <div class="stat-title">Total Expenditures</div>
            <div class="stat-value text-danger fs-4"><?= formatCurrency($totalExpenses) ?></div>
            <small class="text-muted">Approved disbursements</small>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="stat-card <?= $netSurplus >= 0 ? 'primary' : 'warning' ?> p-3">
            <div class="stat-title">Net Financial Position</div>
            <div class="stat-value <?= $netSurplus >= 0 ? 'text-primary' : 'text-danger' ?> fs-4"><?= formatCurrency($netSurplus) ?></div>
            <small class="text-muted"><?= $netSurplus >= 0 ? 'Operational Surplus' : 'Deficit' ?></small>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="stat-card warning p-3">
            <div class="stat-title">Pending Approvals</div>
            <div class="stat-value text-warning fs-4"><?= formatCurrency($pendingExpenses) ?></div>
            <small class="text-muted">Awaiting authorization</small>
        </div>
    </div>
</div>

<!-- Interactive Chart: Monthly Inflow vs Outflow -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="church-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-chart-bar text-primary me-2"></i>Income vs. Expenditure Flow (Last 6 Months)</span>
                <span class="badge bg-light text-muted border"><?= CURRENCY_SYMBOL ?> Monthly</span>
            </div>
            <div class="card-body">
                <canvas id="financeChart" height="230"></canvas>
            </div>
        </div>
    </div>

    <!-- Income Categories Breakdown -->
    <div class="col-lg-4">
        <div class="church-card h-100">
            <div class="card-header">
                <span><i class="fas fa-pie-chart text-secondary me-2"></i>Income Stream Distribution</span>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <?php if (empty($incomeByCategory)): ?>
                        <li class="list-group-item text-muted text-center py-4 small">No income entries recorded yet.</li>
                    <?php else: ?>
                        <?php foreach ($incomeByCategory as $ic): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <div>
                                    <i class="fas fa-circle me-2 text-success" style="font-size: 0.6rem;"></i>
                                    <span class="fw-semibold text-dark"><?= e($ic['category']) ?></span>
                                </div>
                                <span class="fw-bold text-dark"><?= formatCurrency($ic['total']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
                <div class="border-top pt-3 mt-3">
                    <a href="<?= BASE_URL ?>finance/income.php" class="btn btn-outline-success btn-sm w-100">
                        <i class="fas fa-list me-1"></i>View All Incomes
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Inflow & Outflow Transactions Rows -->
<div class="row g-4 mb-4">
    <!-- Recent Income -->
    <div class="col-lg-6">
        <div class="church-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-arrow-down text-success me-2"></i>Recent Income Receipts</span>
                <a href="<?= BASE_URL ?>finance/income.php" class="btn btn-sm btn-outline-success">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Category / Giver</th>
                                <th>Date</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentIncome)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No income records posted yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentIncome as $inc): ?>
                                    <tr>
                                        <td class="font-monospace small text-primary"><?= e($inc['transaction_code']) ?></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= e($inc['category']) ?></div>
                                            <small class="text-muted">
                                                <?= $inc['member_id'] ? e($inc['first_name'] . ' ' . $inc['last_name']) : 'General Congregation' ?> &bull; <?= e($inc['payment_method']) ?>
                                            </small>
                                        </td>
                                        <td class="small"><?= formatDate($inc['transaction_date']) ?></td>
                                        <td class="text-end fw-bold text-success"><?= formatCurrency($inc['amount']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Expenses -->
    <div class="col-lg-6">
        <div class="church-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-arrow-up text-danger me-2"></i>Recent Expense Disbursements</span>
                <a href="<?= BASE_URL ?>finance/expenses.php" class="btn btn-sm btn-outline-danger">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Category / Payee</th>
                                <th>Date</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentExpenses)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No expenses posted yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentExpenses as $exp): ?>
                                    <tr>
                                        <td class="font-monospace small text-danger"><?= e($exp['transaction_code']) ?></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= e($exp['category']) ?></div>
                                            <small class="text-muted"><?= e($exp['payee']) ?> (<?= e($exp['department_name'] ?? 'General') ?>)</small>
                                        </td>
                                        <td class="small"><?= formatDate($exp['transaction_date']) ?></td>
                                        <td class="text-end fw-bold text-danger"><?= formatCurrency($exp['amount']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const finCtx = document.getElementById('financeChart');
    if (finCtx) {
        new Chart(finCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($months) ?>,
                datasets: [
                    {
                        label: 'Inflow (Income)',
                        data: <?= json_encode($incData) ?>,
                        backgroundColor: '#198754',
                        borderRadius: 6
                    },
                    {
                        label: 'Outflow (Expenses)',
                        data: <?= json_encode($expData) ?>,
                        backgroundColor: '#dc3545',
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

