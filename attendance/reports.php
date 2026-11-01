<?php
// attendance/reports.php - Comprehensive Attendance Analytics & Reports
$pageTitle = 'Attendance Reports';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$user = currentUser();

// Filters
$startDate = $_GET['start_date'] ?? date('Y-01-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$departmentId = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : null;
$memberId = !empty($_GET['member_id']) ? (int)$_GET['member_id'] : null;
$serviceType = $_GET['service_type'] ?? '';

$departments = $db->query("SELECT id, name FROM departments ORDER BY name ASC")->fetchAll();
$members = $db->query("SELECT id, first_name, last_name, member_code FROM members WHERE membership_status = 'Active' ORDER BY first_name ASC")->fetchAll();

$memberDetails = null;
if ($memberId) {
    // Individual member report
    $mStmt = $db->prepare("SELECT * FROM members WHERE id = ?");
    $mStmt->execute([$memberId]);
    $memberDetails = $mStmt->fetch();

    $sql = "
        SELECT ad.*, a.attendance_date, s.service_name, s.service_type, s.preacher, s.theme
        FROM attendance_details ad
        INNER JOIN attendance a ON a.id = ad.attendance_id
        LEFT JOIN services s ON s.id = a.service_id
        WHERE ad.member_id = ? AND a.attendance_date BETWEEN ? AND ?
        ORDER BY a.attendance_date DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$memberId, $startDate, $endDate]);
    $memberAttendance = $stmt->fetchAll();

} else {
    // General aggregate attendance report
    $sql = "
        SELECT a.*, s.service_name, s.service_type, s.preacher, s.theme, d.name as department_name
        FROM attendance a
        LEFT JOIN services s ON s.id = a.service_id
        LEFT JOIN departments d ON d.id = a.department_id
        WHERE a.attendance_date BETWEEN ? AND ?
    ";
    $params = [$startDate, $endDate];

    if ($departmentId) {
        $sql .= " AND a.department_id = ?";
        $params[] = $departmentId;
    }
    if (!empty($serviceType)) {
        $sql .= " AND s.service_type = ?";
        $params[] = $serviceType;
    }

    $sql .= " ORDER BY a.attendance_date ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $reportRows = $stmt->fetchAll();

    $totalHeadcount = array_sum(array_column($reportRows, 'total_count'));
    $totalMen = array_sum(array_column($reportRows, 'men_count'));
    $totalWomen = array_sum(array_column($reportRows, 'women_count'));
    $totalChildren = array_sum(array_column($reportRows, 'children_count'));
    $avgHeadcount = count($reportRows) > 0 ? round($totalHeadcount / count($reportRows)) : 0;
}
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">
            <i class="fas fa-chart-line text-primary me-2"></i>Attendance Reports & Analytics
        </h3>
        <p class="text-muted mb-0 small">Generate comprehensive attendance intelligence by date range, department, and individual member.</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm" onclick="exportTableToCSV('reportTable', 'racic_attendance_report')">
            <i class="fas fa-file-csv me-1"></i>Export CSV
        </button>
        <button class="btn btn-outline-dark btn-sm btn-trigger-print">
            <i class="fas fa-print me-1"></i>Print Report
        </button>
        <a href="<?= BASE_URL ?>attendance/index.php" class="btn btn-light border btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<!-- Report Filter Box -->
<div class="church-card mb-4 no-print">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>attendance/reports.php" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">From Date</label>
                <input type="date" name="start_date" class="form-control" value="<?= e($startDate) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">To Date</label>
                <input type="date" name="end_date" class="form-control" value="<?= e($endDate) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Department</label>
                <select name="department_id" class="form-select">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $departmentId == $d['id'] ? 'selected' : '' ?>>
                            <?= e($d['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Individual Member Filter</label>
                <select name="member_id" class="form-select">
                    <option value="">Aggregate / All Congregation</option>
                    <?php foreach ($members as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= $memberId == $m['id'] ? 'selected' : '' ?>>
                            <?= e($m['first_name'] . ' ' . $m['last_name']) ?> (<?= e($m['member_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Generate</button>
                <a href="<?= BASE_URL ?>attendance/reports.php" class="btn btn-light border"><i class="fas fa-redo"></i></a>
            </div>
        </form>
    </div>
</div>

<?php if ($memberDetails): ?>
    <!-- INDIVIDUAL MEMBER ATTENDANCE REPORT -->
    <div class="church-card mb-4">
        <div class="card-header bg-light">
            <span><i class="fas fa-user-check text-primary me-2"></i>Attendance Log for: <strong><?= e($memberDetails['first_name'] . ' ' . $memberDetails['last_name']) ?></strong> (<?= e($memberDetails['member_code']) ?>)</span>
            <span class="badge bg-primary"><?= count($memberAttendance) ?> services attended</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="reportTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Service Name</th>
                            <th>Category</th>
                            <th>Preacher / Theme</th>
                            <th>Check-in Time</th>
                            <th>Attendance Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($memberAttendance)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No attendance entries recorded for this member within the selected period.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($memberAttendance as $row): ?>
                                <tr>
                                    <td class="fw-bold"><?= formatDate($row['attendance_date']) ?></td>
                                    <td><?= e($row['service_name'] ?: 'Regular Worship Service') ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= e($row['service_type'] ?: 'Sunday Service') ?></span></td>
                                    <td class="small"><?= e($row['preacher']) ?> <?= $row['theme'] ? ' - "' . e($row['theme']) . '"' : '' ?></td>
                                    <td class="small"><?= $row['check_in_time'] ? date('h:i A', strtotime($row['check_in_time'])) : '—' ?></td>
                                    <td><span class="badge bg-success">Present</span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- AGGREGATE SUMMARY -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-md-3">
            <div class="stat-card primary p-3">
                <div class="stat-title">Total Headcount</div>
                <div class="stat-value fs-4"><?= number_format($totalHeadcount) ?></div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="stat-card info p-3">
                <div class="stat-title">Average Per Service</div>
                <div class="stat-value fs-4 text-info"><?= number_format($avgHeadcount) ?></div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="stat-card secondary p-3">
                <div class="stat-title">Total Men & Women</div>
                <div class="small fw-bold text-dark mt-2">
                    Men: <?= number_format($totalMen) ?> &bull; Women: <?= number_format($totalWomen) ?>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="stat-card success p-3">
                <div class="stat-title">Total Children</div>
                <div class="stat-value fs-4 text-success"><?= number_format($totalChildren) ?></div>
            </div>
        </div>
    </div>

    <!-- Aggregate Table -->
    <div class="church-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-table text-primary me-2"></i>Attendance Ledger (<?= formatDate($startDate) ?> to <?= formatDate($endDate) ?>)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="reportTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Service / Event</th>
                            <th>Department</th>
                            <th class="text-center">Men</th>
                            <th class="text-center">Women</th>
                            <th class="text-center">Children</th>
                            <th class="text-center fw-bold">Total Headcount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportRows)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">No attendance data matches this date and department filter.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reportRows as $row): ?>
                                <tr>
                                    <td class="fw-bold small"><?= formatDate($row['attendance_date']) ?></td>
                                    <td>
                                        <strong><?= e($row['service_name'] ?? 'General Meeting') ?></strong>
                                        <div class="small text-muted"><?= e($row['service_type']) ?></div>
                                    </td>
                                    <td><?= e($row['department_name'] ?: 'Church Wide') ?></td>
                                    <td class="text-center"><?= (int)$row['men_count'] ?></td>
                                    <td class="text-center"><?= (int)$row['women_count'] ?></td>
                                    <td class="text-center"><?= (int)$row['children_count'] ?></td>
                                    <td class="text-center fw-bold fs-6 bg-light"><?= (int)$row['total_count'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

