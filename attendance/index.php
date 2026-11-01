<?php
// attendance/index.php - Church Services & Attendance Management
$pageTitle = 'Attendance Management';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$user = currentUser();

// Filter parameters
$serviceType = $_GET['service_type'] ?? '';
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

$sql = "
    SELECT a.*, s.service_name, s.service_type, s.preacher, s.theme,
           act.title as activity_title, d.name as department_name,
           u.full_name as recorder_name
    FROM attendance a
    LEFT JOIN services s ON s.id = a.service_id
    LEFT JOIN activities act ON act.id = a.activity_id
    LEFT JOIN departments d ON d.id = a.department_id
    LEFT JOIN users u ON u.id = a.recorded_by_user_id
    WHERE a.attendance_date BETWEEN ? AND ?
";
$params = [$startDate, $endDate];

if (!empty($serviceType)) {
    $sql .= " AND s.service_type = ?";
    $params[] = $serviceType;
}

$sql .= " ORDER BY a.attendance_date DESC, a.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$attendanceRecords = $stmt->fetchAll();

// Statistics
$totalHeadcount = array_sum(array_column($attendanceRecords, 'total_count'));
$menHeadcount = array_sum(array_column($attendanceRecords, 'men_count'));
$womenHeadcount = array_sum(array_column($attendanceRecords, 'women_count'));
$childrenHeadcount = array_sum(array_column($attendanceRecords, 'children_count'));
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">
            <i class="fas fa-user-check text-primary me-2"></i>Attendance Management
        </h3>
        <p class="text-muted mb-0 small">Record, track, and analyze congregation headcounts across all church services, conferences, and prayer meetings.</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <a href="<?= BASE_URL ?>attendance/mark.php" class="btn btn-primary btn-sm shadow-sm">
            <i class="fas fa-plus-circle me-1"></i>Record Attendance / Headcount
        </a>
        <a href="<?= BASE_URL ?>attendance/services.php" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-church me-1"></i>Manage Services
        </a>
        <a href="<?= BASE_URL ?>attendance/reports.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-chart-line me-1"></i>Detailed Reports
        </a>
    </div>
</div>

<!-- Attendance Metrics -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-3">
        <div class="stat-card primary p-3">
            <div class="stat-title">Total Headcount (Period)</div>
            <div class="stat-value fs-4"><?= number_format($totalHeadcount) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="stat-card info p-3">
            <div class="stat-title">Men Total</div>
            <div class="stat-value fs-4 text-primary"><?= number_format($menHeadcount) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="stat-card secondary p-3">
            <div class="stat-title">Women Total</div>
            <div class="stat-value fs-4" style="color: #c59b27;"><?= number_format($womenHeadcount) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="stat-card success p-3">
            <div class="stat-title">Children Total</div>
            <div class="stat-value fs-4 text-success"><?= number_format($childrenHeadcount) ?></div>
        </div>
    </div>
</div>

<!-- Date & Filter Controls -->
<div class="church-card mb-4 no-print">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>attendance/index.php" class="row g-2 align-items-center">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-0">From Date</label>
                <input type="date" name="start_date" class="form-control" value="<?= e($startDate) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-0">To Date</label>
                <input type="date" name="end_date" class="form-control" value="<?= e($endDate) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted mb-0">Service Category</label>
                <select name="service_type" class="form-select">
                    <option value="">All Service Types</option>
                    <option value="Sunday Service" <?= $serviceType === 'Sunday Service' ? 'selected' : '' ?>>Sunday Service</option>
                    <option value="Midweek Service" <?= $serviceType === 'Midweek Service' ? 'selected' : '' ?>>Midweek Service</option>
                    <option value="Prayer Meeting" <?= $serviceType === 'Prayer Meeting' ? 'selected' : '' ?>>Prayer Meeting</option>
                    <option value="All-Night Service" <?= $serviceType === 'All-Night Service' ? 'selected' : '' ?>>All-Night Service</option>
                    <option value="Evangelism" <?= $serviceType === 'Evangelism' ? 'selected' : '' ?>>Evangelism</option>
                    <option value="Departmental Meeting" <?= $serviceType === 'Departmental Meeting' ? 'selected' : '' ?>>Departmental Meeting</option>
                    <option value="Conference" <?= $serviceType === 'Conference' ? 'selected' : '' ?>>Conference</option>
                    <option value="Special Program" <?= $serviceType === 'Special Program' ? 'selected' : '' ?>>Special Program</option>
                </select>
            </div>
            <div class="col-md-2 mt-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Apply</button>
                <a href="<?= BASE_URL ?>attendance/index.php" class="btn btn-light border"><i class="fas fa-redo"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Attendance Records Table -->
<div class="church-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list text-primary me-2"></i>Service Attendance Logs (<?= count($attendanceRecords) ?> records)</span>
        <button class="btn btn-outline-secondary btn-sm no-print" onclick="exportTableToCSV('attendanceTable', 'racic_attendance')">
            <i class="fas fa-file-csv me-1"></i>Export CSV
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="attendanceTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Service / Gathering</th>
                        <th>Type / Ministry</th>
                        <th>Preacher / Theme</th>
                        <th class="text-center">Men</th>
                        <th class="text-center">Women</th>
                        <th class="text-center">Children</th>
                        <th class="text-center fw-bold">Total</th>
                        <th>Recorder</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attendanceRecords)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="fas fa-calendar-times fs-2 mb-2 text-secondary"></i>
                                <p class="mb-0">No attendance records found for this date range.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($attendanceRecords as $row): ?>
                            <tr>
                                <td class="fw-bold small"><?= formatDate($row['attendance_date']) ?></td>
                                <td>
                                    <strong><?= e($row['service_name'] ?? $row['activity_title'] ?? 'Church Gathering') ?></strong>
                                    <?php if ($row['notes']): ?>
                                        <div class="small text-muted text-truncate" style="max-width: 250px;"><?= e($row['notes']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?= e($row['service_type'] ?? $row['department_name'] ?? 'General') ?>
                                    </span>
                                </td>
                                <td class="small">
                                    <?php if ($row['preacher']): ?>
                                        <div class="fw-semibold text-dark"><i class="fas fa-microphone text-muted me-1"></i><?= e($row['preacher']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($row['theme']): ?>
                                        <small class="text-muted italic">"<?= e($row['theme']) ?>"</small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center fw-semibold text-primary"><?= (int)$row['men_count'] ?></td>
                                <td class="text-center fw-semibold" style="color: #c59b27;"><?= (int)$row['women_count'] ?></td>
                                <td class="text-center fw-semibold text-success"><?= (int)$row['children_count'] ?></td>
                                <td class="text-center fw-bold fs-6 text-dark bg-light"><?= (int)$row['total_count'] ?></td>
                                <td class="small text-muted"><?= e($row['recorder_name']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

