<?php
// reports/index.php - RACIC Centralized Reports Center
$pageTitle = 'Reports Management Center';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$user = currentUser();

$reportType = $_GET['type'] ?? 'membership';
$startDate = $_GET['start_date'] ?? date('Y-01-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$deptFilter = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : 0;

$departments = $db->query("SELECT id, name, code FROM departments ORDER BY name ASC")->fetchAll();
?>

<!-- Title & Export Controls -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">
            <i class="fas fa-file-invoice text-primary me-2"></i>Executive Reports Center
        </h3>
        <p class="text-muted mb-0 small">Downloadable and printable analytical intelligence across all church operations.</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm" onclick="exportTableToCSV('activeReportTable', 'racic_<?= e($reportType) ?>_report')">
            <i class="fas fa-file-csv me-1"></i>Download Excel / CSV
        </button>
        <button class="btn btn-outline-dark btn-sm btn-trigger-print">
            <i class="fas fa-print me-1"></i>Print / Save as PDF
        </button>
    </div>
</div>

<!-- Report Selection Toolbar -->
<div class="church-card mb-4 no-print">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>reports/index.php" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Select Report Module</label>
                <select name="type" class="form-select fw-semibold" onchange="this.form.submit()">
                    <option value="membership" <?= $reportType === 'membership' ? 'selected' : '' ?>>1. Church Membership Roster</option>
                    <option value="attendance" <?= $reportType === 'attendance' ? 'selected' : '' ?>>2. Service Attendance Analysis</option>
                    <option value="activities" <?= $reportType === 'activities' ? 'selected' : '' ?>>3. Church Activities & Reports</option>
                    <option value="visitors" <?= $reportType === 'visitors' ? 'selected' : '' ?>>4. Visitors & Conversion Tracking</option>
                    <option value="departments" <?= $reportType === 'departments' ? 'selected' : '' ?>>5. Departmental Performance</option>
                    <?php if (hasRole(['super_admin', 'head_pastor', 'finance_officer'])): ?>
                        <option value="finances" <?= $reportType === 'finances' ? 'selected' : '' ?>>6. Financial Stewardship Statement</option>
                    <?php endif; ?>
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
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Generate Report</button>
                <a href="<?= BASE_URL ?>reports/index.php" class="btn btn-light border"><i class="fas fa-redo"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Print Letterhead Header -->
<div class="print-only text-center mb-4">
    <h2 class="fw-bold mb-0"><?= CHURCH_NAME ?></h2>
    <div class="text-uppercase small"><?= CHURCH_SLOGAN ?></div>
    <div class="small text-muted"><?= CHURCH_ADDRESS ?> &bull; <?= CHURCH_PHONE ?></div>
    <hr>
    <h4 class="fw-bold text-uppercase mt-2"><?= strtoupper(str_replace('_', ' ', $reportType)) ?> AUDIT REPORT</h4>
    <div class="small text-muted">Generated on <?= date('F j, Y, h:i A') ?> &bull; Range: <?= formatDate($startDate) ?> to <?= formatDate($endDate) ?></div>
</div>

<!-- DYNAMIC REPORT SECTION -->
<?php if ($reportType === 'membership'): ?>
    <?php
        $mStmt = $db->query("
            SELECT m.*, d.name as department_name 
            FROM members m 
            LEFT JOIN departments d ON d.id = m.department_id 
            ORDER BY m.first_name ASC
        ");
        $members = $mStmt->fetchAll();
    ?>
    <div class="church-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-users text-primary me-2"></i>Official Membership Registry (<?= count($members) ?> members)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="activeReportTable">
                    <thead>
                        <tr>
                            <th>Member Code</th>
                            <th>Full Name</th>
                            <th>Gender</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Baptism</th>
                            <th>Status</th>
                            <th>Joined Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $m): ?>
                            <tr>
                                <td class="fw-bold font-monospace"><?= e($m['member_code']) ?></td>
                                <td><?= e($m['first_name'] . ' ' . $m['last_name']) ?></td>
                                <td><?= e($m['gender']) ?></td>
                                <td><?= e($m['phone']) ?></td>
                                <td><?= e($m['email'] ?: '—') ?></td>
                                <td><?= e($m['department_name'] ?: 'None') ?></td>
                                <td><?= getStatusBadge($m['baptism_status']) ?></td>
                                <td><?= getStatusBadge($m['membership_status']) ?></td>
                                <td><?= formatDate($m['membership_date']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($reportType === 'attendance'): ?>
    <?php
        $attStmt = $db->prepare("
            SELECT a.*, s.service_name, s.service_type, d.name as department_name, u.full_name as recorder_name
            FROM attendance a
            LEFT JOIN services s ON s.id = a.service_id
            LEFT JOIN departments d ON d.id = a.department_id
            LEFT JOIN users u ON u.id = a.recorded_by_user_id
            WHERE a.attendance_date BETWEEN ? AND ?
            ORDER BY a.attendance_date ASC
        ");
        $attStmt->execute([$startDate, $endDate]);
        $attendances = $attStmt->fetchAll();
    ?>
    <div class="church-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-chart-line text-primary me-2"></i>Service Attendance Summary</span>
            <span class="badge bg-primary">Total: <?= number_format(array_sum(array_column($attendances, 'total_count'))) ?> worshippers</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="activeReportTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Service / Gathering</th>
                            <th>Category</th>
                            <th class="text-center">Men</th>
                            <th class="text-center">Women</th>
                            <th class="text-center">Children</th>
                            <th class="text-center fw-bold">Total Headcount</th>
                            <th>Recorded By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendances as $row): ?>
                            <tr>
                                <td class="fw-bold"><?= formatDate($row['attendance_date']) ?></td>
                                <td><?= e($row['service_name'] ?: 'Church Gathering') ?></td>
                                <td><?= e($row['service_type'] ?: 'General') ?></td>
                                <td class="text-center"><?= (int)$row['men_count'] ?></td>
                                <td class="text-center"><?= (int)$row['women_count'] ?></td>
                                <td class="text-center"><?= (int)$row['children_count'] ?></td>
                                <td class="text-center fw-bold bg-light"><?= (int)$row['total_count'] ?></td>
                                <td><?= e($row['recorder_name']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($reportType === 'activities'): ?>
    <?php
        $actStmt = $db->prepare("
            SELECT a.*, d.name as department_name, 
                   ar.actual_attendance, ar.expenditure, ar.status as report_status, ar.achievements
            FROM activities a
            LEFT JOIN departments d ON d.id = a.department_id
            LEFT JOIN activity_reports ar ON ar.activity_id = a.id
            WHERE a.activity_date BETWEEN ? AND ?
            ORDER BY a.activity_date ASC
        ");
        $actStmt->execute([$startDate, $endDate]);
        $activities = $actStmt->fetchAll();
    ?>
    <div class="church-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-clipboard-check text-primary me-2"></i>Church Activities & Report Completion Performance</span>
            <span class="badge bg-primary"><?= count($activities) ?> activities</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="activeReportTable">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Activity Title</th>
                            <th>Department</th>
                            <th>Date</th>
                            <th>Expected Att.</th>
                            <th>Actual Att.</th>
                            <th>Budget</th>
                            <th>Actual Spent</th>
                            <th>Status</th>
                            <th>Report Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activities as $a): ?>
                            <tr>
                                <td class="fw-bold font-monospace"><?= e($a['activity_code']) ?></td>
                                <td><?= e($a['title']) ?></td>
                                <td><?= e($a['department_name']) ?></td>
                                <td><?= formatDate($a['activity_date']) ?></td>
                                <td><?= (int)$a['expected_attendance'] ?></td>
                                <td class="fw-bold text-success"><?= $a['actual_attendance'] ? (int)$a['actual_attendance'] : '—' ?></td>
                                <td><?= formatCurrency($a['budget']) ?></td>
                                <td class="fw-bold text-danger"><?= $a['expenditure'] ? formatCurrency($a['expenditure']) : '—' ?></td>
                                <td><?= getStatusBadge($a['status']) ?></td>
                                <td><?= $a['report_status'] ? getStatusBadge($a['report_status']) : '<span class="text-muted small">Pending</span>' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($reportType === 'visitors'): ?>
    <?php
        $vStmt = $db->prepare("
            SELECT v.*, m.first_name as inviter_first, m.last_name as inviter_last,
                   u.full_name as worker_name
            FROM visitors v
            LEFT JOIN members m ON m.id = v.invited_by_member_id
            LEFT JOIN users u ON u.id = v.assigned_to_user_id
            WHERE v.visit_date BETWEEN ? AND ?
            ORDER BY v.visit_date DESC
        ");
        $vStmt->execute([$startDate, $endDate]);
        $visitors = $vStmt->fetchAll();
    ?>
    <div class="church-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-user-friends text-primary me-2"></i>Visitors & Soul Retention Audit</span>
            <span class="badge bg-success"><?= count($visitors) ?> guests logged</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="activeReportTable">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Visitor Name</th>
                            <th>Contact</th>
                            <th>Visit Date</th>
                            <th>Invited By</th>
                            <th>Follow-up Status</th>
                            <th>Assigned Worker</th>
                            <th>Prayer Requests</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visitors as $v): ?>
                            <tr>
                                <td class="fw-bold font-monospace"><?= e($v['visitor_code']) ?></td>
                                <td><?= e($v['first_name'] . ' ' . $v['last_name']) ?></td>
                                <td><?= e($v['phone']) ?></td>
                                <td><?= formatDate($v['visit_date']) ?></td>
                                <td><?= $v['invited_by_member_id'] ? e($v['inviter_first'] . ' ' . $v['inviter_last']) : e($v['invited_by_name'] ?: 'None') ?></td>
                                <td><?= getStatusBadge($v['follow_up_status']) ?></td>
                                <td><?= e($v['worker_name'] ?: 'Unassigned') ?></td>
                                <td class="small text-truncate" style="max-width: 200px;"><?= e($v['prayer_requests'] ?: '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($reportType === 'departments'): ?>
    <?php
        $depts = $db->query("
            SELECT d.*, u.full_name as leader_name,
                   (SELECT COUNT(*) FROM members m WHERE m.department_id = d.id) as total_members,
                   (SELECT COUNT(*) FROM activities a WHERE a.department_id = d.id) as total_activities,
                   (SELECT COALESCE(SUM(amount), 0) FROM expenses e WHERE e.department_id = d.id AND e.status = 'Approved') as total_spent
            FROM departments d
            LEFT JOIN users u ON u.id = d.leader_id
            ORDER BY d.name ASC
        ")->fetchAll();
    ?>
    <div class="church-card">
        <div class="card-header">
            <span><i class="fas fa-sitemap text-primary me-2"></i>Departmental Health & Performance Index</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="activeReportTable">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Department Name</th>
                            <th>Department Leader</th>
                            <th>Meeting Time</th>
                            <th class="text-center">Members</th>
                            <th class="text-center">Activities</th>
                            <th>Budget Allocation</th>
                            <th>Actual Expenses</th>
                            <th>Variance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($depts as $d): ?>
                            <?php $var = $d['budget_allocation'] - $d['total_spent']; ?>
                            <tr>
                                <td class="fw-bold font-monospace"><?= e($d['code']) ?></td>
                                <td><strong><?= e($d['name']) ?></strong></td>
                                <td><?= e($d['leader_name'] ?: 'Unassigned') ?></td>
                                <td class="small"><?= e($d['meeting_schedule']) ?></td>
                                <td class="text-center fw-bold"><?= $d['total_members'] ?></td>
                                <td class="text-center"><?= $d['total_activities'] ?></td>
                                <td><?= formatCurrency($d['budget_allocation']) ?></td>
                                <td class="text-danger fw-semibold"><?= formatCurrency($d['total_spent']) ?></td>
                                <td class="fw-bold <?= $var >= 0 ? 'text-success' : 'text-danger' ?>"><?= formatCurrency($var) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($reportType === 'finances'): ?>
    <?php
        $incTotal = (float)$db->prepare("SELECT COALESCE(SUM(amount), 0) FROM income WHERE transaction_date BETWEEN ? AND ?");
        $incTotal->execute([$startDate, $endDate]);
        $sumInc = (float)$incTotal->fetchColumn();

        $expTotal = (float)$db->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE status = 'Approved' AND transaction_date BETWEEN ? AND ?");
        $expTotal->execute([$startDate, $endDate]);
        $sumExp = (float)$expTotal->fetchColumn();

        $incBreakdown = $db->prepare("
            SELECT category, COUNT(*) as count, SUM(amount) as total 
            FROM income WHERE transaction_date BETWEEN ? AND ? GROUP BY category
        ");
        $incBreakdown->execute([$startDate, $endDate]);
        $incomes = $incBreakdown->fetchAll();

        $expBreakdown = $db->prepare("
            SELECT category, COUNT(*) as count, SUM(amount) as total 
            FROM expenses WHERE status = 'Approved' AND transaction_date BETWEEN ? AND ? GROUP BY category
        ");
        $expBreakdown->execute([$startDate, $endDate]);
        $expenses = $expBreakdown->fetchAll();
    ?>
    <div class="row g-4" id="activeReportTable">
        <div class="col-md-6">
            <div class="church-card">
                <div class="card-header bg-success bg-opacity-10 text-success d-flex justify-content-between">
                    <strong>Total Income Inflow</strong>
                    <span><?= formatCurrency($sumInc) ?></span>
                </div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead><tr><th>Category</th><th class="text-end">Amount</th></tr></thead>
                        <tbody>
                            <?php foreach ($incomes as $inc): ?>
                                <tr>
                                    <td><?= e($inc['category']) ?> (<?= $inc['count'] ?>)</td>
                                    <td class="text-end fw-bold text-success"><?= formatCurrency($inc['total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="church-card">
                <div class="card-header bg-danger bg-opacity-10 text-danger d-flex justify-content-between">
                    <strong>Total Approved Disbursements</strong>
                    <span><?= formatCurrency($sumExp) ?></span>
                </div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead><tr><th>Category</th><th class="text-end">Amount</th></tr></thead>
                        <tbody>
                            <?php foreach ($expenses as $exp): ?>
                                <tr>
                                    <td><?= e($exp['category']) ?> (<?= $exp['count'] ?>)</td>
                                    <td class="text-end fw-bold text-danger"><?= formatCurrency($exp['total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="p-3 bg-white border rounded text-center">
                <h5 class="fw-bold mb-1">Net Operational Balance: <span class="<?= ($sumInc - $sumExp) >= 0 ? 'text-success' : 'text-danger' ?>"><?= formatCurrency($sumInc - $sumExp) ?></span></h5>
                <small class="text-muted">Calculated for period <?= formatDate($startDate) ?> to <?= formatDate($endDate) ?></small>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Signatures for printed reports -->
<div class="row mt-5 pt-4 print-only">
    <div class="col-6 text-center">
        <div class="border-top pt-2">
            <strong>Prepared By:</strong><br>
            <?= e($user['full_name']) ?> (<?= e($user['role_display']) ?>)
        </div>
    </div>
    <div class="col-6 text-center">
        <div class="border-top pt-2">
            <strong>Pastoral Approval:</strong><br>
            Rev. Emmanuel Darko / Bishop Sylvester Owusu
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

