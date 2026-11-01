<?php
// dashboard.php - RACIC Church Management System Main Executive Dashboard
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

$db = getDB();
$user = currentUser();

// 1. Core Metrics
// Membership Stats
$totalMembers = (int)$db->query("SELECT COUNT(*) FROM members")->fetchColumn();
$activeMembers = (int)$db->query("SELECT COUNT(*) FROM members WHERE membership_status = 'Active'")->fetchColumn();
$newMembers = (int)$db->query("SELECT COUNT(*) FROM members WHERE membership_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();

// Visitors Stats
$totalVisitors = (int)$db->query("SELECT COUNT(*) FROM visitors")->fetchColumn();
$pendingVisitors = (int)$db->query("SELECT COUNT(*) FROM visitors WHERE follow_up_status IN ('New', 'Contacted')")->fetchColumn();

// Departments
$totalDepartments = (int)$db->query("SELECT COUNT(*) FROM departments WHERE status = 'active'")->fetchColumn();

// Activities & Reports
$upcomingActivities = (int)$db->query("SELECT COUNT(*) FROM activities WHERE status = 'Upcoming'")->fetchColumn();
$completedActivities = (int)$db->query("SELECT COUNT(*) FROM activities WHERE status = 'Completed'")->fetchColumn();
$pendingReports = (int)$db->query("SELECT COUNT(*) FROM activity_reports WHERE status = 'Submitted'")->fetchColumn();

// Financial Summaries (Only calculated if authorized or displayed in masked summary)
$totalIncome = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM income")->fetchColumn();
$totalExpenses = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE status = 'Approved'")->fetchColumn();
$netSurplus = $totalIncome - $totalExpenses;

// 2. Recent Activities
$recentActivities = $db->query("
    SELECT a.*, d.name as department_name, d.code as department_code, ar.id as report_id, ar.status as report_status
    FROM activities a
    LEFT JOIN departments d ON d.id = a.department_id
    LEFT JOIN activity_reports ar ON ar.activity_id = a.id
    ORDER BY a.activity_date DESC, a.start_time DESC
    LIMIT 6
")->fetchAll();

// 3. Pending Reports requiring review
$pendingReportsList = $db->query("
    SELECT ar.*, a.title as activity_title, a.activity_code, a.activity_date, d.name as department_name, u.full_name as submitter_name
    FROM activity_reports ar
    INNER JOIN activities a ON a.id = ar.activity_id
    INNER JOIN departments d ON d.id = a.department_id
    INNER JOIN users u ON u.id = ar.submitted_by_user_id
    WHERE ar.status = 'Submitted'
    ORDER BY ar.submitted_at DESC
    LIMIT 5
")->fetchAll();

// 4. Recent Visitors
$recentVisitors = $db->query("
    SELECT v.*, m.first_name as inviter_first, m.last_name as inviter_last
    FROM visitors v
    LEFT JOIN members m ON m.id = v.invited_by_member_id
    ORDER BY v.visit_date DESC
    LIMIT 5
")->fetchAll();

// 5. Active Announcements
$announcements = $db->query("
    SELECT a.*, d.name as department_name, u.full_name as author_name
    FROM announcements a
    LEFT JOIN departments d ON d.id = a.department_id
    LEFT JOIN users u ON u.id = a.created_by_user_id
    WHERE a.status = 'Active' AND (a.expiry_date IS NULL OR a.expiry_date >= CURDATE())
    ORDER BY FIELD(a.priority, 'Urgent', 'Important', 'Normal'), a.publish_date DESC
    LIMIT 4
")->fetchAll();

// 6. Data for Attendance Chart (Last 6 services)
$attendanceRows = $db->query("
    SELECT service_name, attendance_date, men_count, women_count, children_count, total_count
    FROM (
        SELECT COALESCE(s.service_name, 'Meeting') as service_name, a.attendance_date, a.men_count, a.women_count, a.children_count, a.total_count
        FROM attendance a
        LEFT JOIN services s ON s.id = a.service_id
        ORDER BY a.attendance_date DESC
        LIMIT 6
    ) as sub ORDER BY attendance_date ASC
")->fetchAll();

$attLabels = [];
$attMen = [];
$attWomen = [];
$attChildren = [];
$attTotal = [];
foreach ($attendanceRows as $row) {
    $attLabels[] = formatDate($row['attendance_date'], 'M d') . ' (' . substr($row['service_name'], 0, 15) . '...)';
    $attMen[] = (int)$row['men_count'];
    $attWomen[] = (int)$row['women_count'];
    $attChildren[] = (int)$row['children_count'];
    $attTotal[] = (int)$row['total_count'];
}

// 7. Department Distribution Data
$deptStats = $db->query("
    SELECT d.name, COUNT(m.id) as member_count
    FROM departments d
    LEFT JOIN members m ON m.department_id = d.id
    GROUP BY d.id
    ORDER BY member_count DESC
    LIMIT 6
")->fetchAll();

$deptLabels = array_column($deptStats, 'name');
$deptCounts = array_map('intval', array_column($deptStats, 'member_count'));
?>

<!-- Page Heading -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">
            <i class="fas fa-chart-pie text-primary me-2"></i>Executive Leadership Dashboard
        </h3>
        <p class="text-muted mb-0 small">Overview of church membership, departmental activities, services attendance, and stewardship.</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <a href="<?= BASE_URL ?>activities/create.php" class="btn btn-primary btn-sm shadow-sm">
            <i class="fas fa-plus-circle me-1"></i>New Activity
        </a>
        <a href="<?= BASE_URL ?>members/create.php" class="btn btn-success btn-sm shadow-sm">
            <i class="fas fa-user-plus me-1"></i>New Member
        </a>
        <a href="<?= BASE_URL ?>attendance/mark.php" class="btn btn-warning btn-sm text-dark shadow-sm">
            <i class="fas fa-user-check me-1"></i>Mark Attendance
        </a>
    </div>
</div>

<!-- Primary Metric Cards Row -->
<div class="row g-3 mb-4">
    <!-- Total Membership -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card primary">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-title">Total Members</div>
                    <div class="stat-value"><?= number_format($totalMembers) ?></div>
                    <small class="text-success fw-semibold"><i class="fas fa-check me-1"></i><?= $activeMembers ?> Active</small>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- New Members (30 Days) -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-title">New Members</div>
                    <div class="stat-value text-success"><?= number_format($newMembers) ?></div>
                    <small class="text-muted">Registered in 30 days</small>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-user-check"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Visitors & Follow-ups -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card info">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-title">Total Visitors</div>
                    <div class="stat-value text-info"><?= number_format($totalVisitors) ?></div>
                    <small class="text-warning fw-semibold"><i class="fas fa-phone me-1"></i><?= $pendingVisitors ?> Pending Follow-up</small>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-user-friends"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Church Departments -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card secondary">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-title">Departments</div>
                    <div class="stat-value" style="color: #9a730b;"><?= number_format($totalDepartments) ?></div>
                    <small class="text-muted">Active Ministries</small>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-sitemap"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Secondary Activities & Finance Cards Row -->
<div class="row g-3 mb-4">
    <!-- Upcoming Activities -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card primary">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-title">Upcoming Activities</div>
                    <div class="stat-value text-primary"><?= number_format($upcomingActivities) ?></div>
                    <small class="text-muted"><?= $completedActivities ?> Completed this term</small>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Activity Reports -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card warning">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-title">Pending Reports</div>
                    <div class="stat-value text-warning"><?= number_format($pendingReports) ?></div>
                    <small class="text-danger fw-semibold">Awaiting Pastoral Review</small>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-file-signature"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Income -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-title">Total Income (YTD)</div>
                    <div class="stat-value text-success" style="font-size: 1.45rem;">
                        <?= hasRole(['super_admin', 'head_pastor', 'finance_officer']) ? formatCurrency($totalIncome) : '••••••' ?>
                    </div>
                    <small class="text-muted">Tithes, Offerings & Gifts</small>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-hand-holding-dollar"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Expenses & Net Balance -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card <?= $netSurplus >= 0 ? 'primary' : 'danger' ?>">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-title">Net Balance</div>
                    <div class="stat-value <?= $netSurplus >= 0 ? 'text-primary' : 'text-danger' ?>" style="font-size: 1.45rem;">
                        <?= hasRole(['super_admin', 'head_pastor', 'finance_officer']) ? formatCurrency($netSurplus) : '••••••' ?>
                    </div>
                    <small class="text-muted">Exp: <?= hasRole(['super_admin', 'head_pastor', 'finance_officer']) ? formatCurrency($totalExpenses) : '••••' ?></small>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-scale-balanced"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Interactive Charts Section -->
<div class="row g-4 mb-4">
    <!-- Attendance Trend Chart -->
    <div class="col-lg-8">
        <div class="church-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-chart-line text-primary me-2"></i>Service Attendance Trend (Headcount)</span>
                <a href="<?= BASE_URL ?>attendance/reports.php" class="btn btn-sm btn-outline-primary">Full Attendance Report</a>
            </div>
            <div class="card-body">
                <?php if (empty($attendanceRows)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-chart-line fs-2 mb-2 text-secondary"></i>
                        <p class="mb-0 small">No service attendance recorded yet. Use <strong>Mark Attendance</strong> to log headcounts.</p>
                    </div>
                <?php else: ?>
                    <canvas id="attendanceChart" height="240"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Department Membership Distribution -->
    <div class="col-lg-4">
        <div class="church-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-chart-pie text-secondary me-2"></i>Department Distribution</span>
                <a href="<?= BASE_URL ?>departments/index.php" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="card-body d-flex flex-column justify-content-center align-items-center">
                <?php if (empty($deptStats)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-sitemap fs-2 mb-2 text-secondary"></i>
                        <p class="mb-0 small">No departments established yet. Use <strong>Departments</strong> module to create church ministries.</p>
                    </div>
                <?php else: ?>
                    <div style="width: 100%; max-height: 230px; position: relative;">
                        <canvas id="departmentChart"></canvas>
                    </div>
                    <div class="mt-3 small text-muted text-center">
                        Top ministries by member allocation
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Church Activities & Reports Monitoring Section -->
<div class="row g-4 mb-4">
    <!-- Church Activities Monitor (Crucial Module) -->
    <div class="col-lg-8">
        <div class="church-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-calendar-alt text-primary me-2"></i>Church Activities Monitoring</span>
                <div>
                    <a href="<?= BASE_URL ?>activities/create.php" class="btn btn-sm btn-primary me-1"><i class="fas fa-plus me-1"></i>New</a>
                    <a href="<?= BASE_URL ?>activities/index.php" class="btn btn-sm btn-outline-secondary">View All</a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Activity Title</th>
                                <th>Department</th>
                                <th>Date & Time</th>
                                <th>Status</th>
                                <th>Report Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentActivities)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No church activities scheduled yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentActivities as $act): ?>
                                    <tr>
                                        <td class="fw-semibold text-primary"><?= e($act['activity_code']) ?></td>
                                        <td>
                                            <a href="<?= BASE_URL ?>activities/view.php?id=<?= $act['id'] ?>" class="text-dark fw-bold text-decoration-none">
                                                <?= e($act['title']) ?>
                                            </a>
                                            <div class="small text-muted"><i class="fas fa-map-marker-alt me-1 text-danger"></i><?= e($act['venue']) ?></div>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?= e($act['department_code'] ?? 'GEN') ?></span></td>
                                        <td>
                                            <div class="small fw-semibold"><?= formatDate($act['activity_date']) ?></div>
                                            <div class="text-muted small"><?= date('h:i A', strtotime($act['start_time'])) ?></div>
                                        </td>
                                        <td><?= getStatusBadge($act['status']) ?></td>
                                        <td>
                                            <?php if ($act['report_id']): ?>
                                                <a href="<?= BASE_URL ?>activities/view.php?id=<?= $act['id'] ?>#report" class="badge bg-success text-decoration-none">
                                                    <i class="fas fa-check me-1"></i><?= e($act['report_status']) ?>
                                                </a>
                                            <?php elseif ($act['status'] === 'Completed'): ?>
                                                <a href="<?= BASE_URL ?>activities/report.php?id=<?= $act['id'] ?>" class="badge bg-warning text-dark text-decoration-none">
                                                    <i class="fas fa-exclamation-triangle me-1"></i>Submit Report
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small">Pending Event</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?= BASE_URL ?>activities/view.php?id=<?= $act['id'] ?>" class="btn btn-sm btn-light border" title="View Activity">
                                                <i class="fas fa-eye text-primary"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Reports Needing Pastoral Review -->
    <div class="col-lg-4">
        <div class="church-card h-100">
            <div class="card-header bg-warning bg-opacity-10 text-dark d-flex justify-content-between align-items-center">
                <span><i class="fas fa-clock text-warning me-2"></i>Pending Reports (<?= count($pendingReportsList) ?>)</span>
                <span class="badge bg-warning text-dark">Action Needed</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($pendingReportsList)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-check-double text-success fs-1 mb-2"></i>
                        <p class="mb-0 small">All activity reports have been reviewed and approved!</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($pendingReportsList as $pr): ?>
                            <div class="list-group-item p-3">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <h6 class="fw-bold mb-0 text-dark"><?= e($pr['activity_title']) ?></h6>
                                    <span class="badge bg-info text-dark">Awaiting Review</span>
                                </div>
                                <div class="small text-muted mb-2">
                                    <i class="fas fa-user-edit me-1"></i>By: <?= e($pr['submitter_name']) ?> (<?= e($pr['department_name']) ?>)
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-secondary"><i class="far fa-calendar me-1"></i><?= formatDate($pr['activity_date']) ?></small>
                                    <a href="<?= BASE_URL ?>activities/view.php?id=<?= $pr['activity_id'] ?>#report" class="btn btn-sm btn-outline-primary py-0 px-2">
                                        Review Report <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Visitors and Announcements Row -->
<div class="row g-4 mb-4">
    <!-- Recent Visitors -->
    <div class="col-lg-6">
        <div class="church-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-user-plus text-success me-2"></i>Recent Visitors & Follow-up</span>
                <a href="<?= BASE_URL ?>visitors/index.php" class="btn btn-sm btn-outline-success">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Visit Date</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentVisitors)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No visitors registered recently.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentVisitors as $v): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= BASE_URL ?>visitors/view.php?id=<?= $v['id'] ?>" class="fw-bold text-dark text-decoration-none">
                                                <?= e($v['first_name'] . ' ' . $v['last_name']) ?>
                                            </a>
                                            <div class="small text-muted"><?= e($v['visitor_code']) ?></div>
                                        </td>
                                        <td class="small"><?= e($v['phone']) ?></td>
                                        <td class="small"><?= formatDate($v['visit_date']) ?></td>
                                        <td><?= getStatusBadge($v['follow_up_status']) ?></td>
                                        <td class="text-end">
                                            <a href="<?= BASE_URL ?>visitors/follow-up.php?id=<?= $v['id'] ?>" class="btn btn-sm btn-light border" title="Log Follow-up">
                                                <i class="fas fa-phone-volume text-primary"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Church Announcements -->
    <div class="col-lg-6">
        <div class="church-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-bullhorn text-warning me-2"></i>Announcements & Bulletins</span>
                <a href="<?= BASE_URL ?>announcements/index.php" class="btn btn-sm btn-outline-warning text-dark">All Notices</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($announcements)): ?>
                    <div class="text-center py-5 text-muted">No active announcements.</div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($announcements as $anc): ?>
                            <div class="list-group-item p-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <h6 class="fw-bold mb-0 text-dark"><?= e($anc['title']) ?></h6>
                                    <?= getStatusBadge($anc['priority']) ?>
                                </div>
                                <p class="text-muted small mb-2 text-truncate"><?= e($anc['content']) ?></p>
                                <div class="d-flex justify-content-between align-items-center small text-secondary">
                                    <span><i class="fas fa-users me-1 text-primary"></i><?= e($anc['target_audience']) ?></span>
                                    <span><i class="far fa-clock me-1"></i><?= formatDate($anc['publish_date']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Chart Scripts Initialization -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Attendance Trend Chart
    const attCtx = document.getElementById('attendanceChart');
    if (attCtx) {
        new Chart(attCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($attLabels) ?>,
                datasets: [
                    {
                        label: 'Men',
                        data: <?= json_encode($attMen) ?>,
                        backgroundColor: '#0f2c59',
                        borderRadius: 4
                    },
                    {
                        label: 'Women',
                        data: <?= json_encode($attWomen) ?>,
                        backgroundColor: '#c59b27',
                        borderRadius: 4
                    },
                    {
                        label: 'Children',
                        data: <?= json_encode($attChildren) ?>,
                        backgroundColor: '#198754',
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: { mode: 'index', intersect: false }
                },
                scales: {
                    x: { stacked: true },
                    y: { stacked: true, beginAtZero: true }
                }
            }
        });
    }

    // 2. Department Chart
    const deptCtx = document.getElementById('departmentChart');
    if (deptCtx) {
        new Chart(deptCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($deptLabels) ?>,
                datasets: [{
                    data: <?= json_encode($deptCounts) ?>,
                    backgroundColor: [
                        '#0f2c59', '#c59b27', '#198754', '#0dcaf0', '#6f42c1', '#fd7e14'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

