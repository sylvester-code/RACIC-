<?php
// attendance/services.php - Church Services Registry
$pageTitle = 'Church Services Registry';
require_once __DIR__ . '/../includes/header.php';
requireRole(['super_admin', 'head_pastor', 'church_admin', 'dept_leader']);

$db = getDB();
$user = currentUser();
$errors = [];

// Handle new service creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create_service'])) {
    if (!validateCSRF()) {
        $errors[] = "Invalid CSRF security token.";
    } else {
        $serviceName = trim($_POST['service_name'] ?? '');
        $serviceType = $_POST['service_type'] ?? 'Sunday Service';
        $serviceDate = !empty($_POST['service_date']) ? $_POST['service_date'] : date('Y-m-d');
        $serviceTime = !empty($_POST['service_time']) ? $_POST['service_time'] : '08:30:00';
        $preacher = trim($_POST['preacher'] ?? '');
        $theme = trim($_POST['theme'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($serviceName)) $errors[] = "Service name is required.";

        if (empty($errors)) {
            try {
                $ins = $db->prepare("
                    INSERT INTO services (service_name, service_type, service_date, service_time, preacher, theme, description, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $ins->execute([$serviceName, $serviceType, $serviceDate, $serviceTime, $preacher, $theme, $description]);
                $srvId = (int)$db->lastInsertId();

                // Also add to church calendar events!
                $cal = $db->prepare("
                    INSERT INTO events (title, event_type, start_datetime, end_datetime, venue, description, color, created_by, created_at)
                    VALUES (?, 'Church Service', ?, ?, 'RACIC Main Sanctuary', ?, '#198754', ?, NOW())
                ");
                $startDt = $serviceDate . ' ' . $serviceTime;
                $endDt = date('Y-m-d H:i:s', strtotime($startDt . ' +3 hours'));
                $cal->execute([$serviceName, $startDt, $endDt, $theme . ' - ' . $description, $user['id']]);

                logAudit('CREATE_SERVICE', 'Attendance', $srvId, "Created service '{$serviceName}' for {$serviceDate}");
                flash('success', "Service '{$serviceName}' successfully scheduled!");
                header("Location: " . BASE_URL . "attendance/services.php");
                exit;
            } catch (Exception $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Fetch all services with attendance counts
$services = $db->query("
    SELECT s.*,
           (SELECT COALESCE(SUM(total_count), 0) FROM attendance a WHERE a.service_id = s.id) as attendance_headcount
    FROM services s
    ORDER BY s.service_date DESC, s.service_time DESC
")->fetchAll();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">
            <i class="fas fa-church text-primary me-2"></i>Church Services & Programs
        </h3>
        <p class="text-muted mb-0 small">Schedule official Sunday services, midweek discipleship, all-nights, and conferences.</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#createServiceModal">
            <i class="fas fa-plus-circle me-1"></i>Schedule New Service
        </button>
        <a href="<?= BASE_URL ?>attendance/mark.php" class="btn btn-warning btn-sm text-dark shadow-sm">
            <i class="fas fa-user-check me-1"></i>Record Attendance
        </a>
        <a href="<?= BASE_URL ?>attendance/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Attendance Hub
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

<!-- Services Table -->
<div class="church-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-calendar-alt text-primary me-2"></i>Scheduled Services (<?= count($services) ?>)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Service Name</th>
                        <th>Category</th>
                        <th>Preacher</th>
                        <th>Sermon Theme</th>
                        <th class="text-center">Recorded Headcount</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($services)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No church services scheduled yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($services as $srv): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= formatDate($srv['service_date']) ?></div>
                                    <small class="text-muted"><?= date('h:i A', strtotime($srv['service_time'])) ?></small>
                                </td>
                                <td>
                                    <strong class="text-dark"><?= e($srv['service_name']) ?></strong>
                                    <?php if ($srv['description']): ?>
                                        <div class="small text-muted text-truncate" style="max-width: 250px;"><?= e($srv['description']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= e($srv['service_type']) ?></span></td>
                                <td><?= e($srv['preacher'] ?: 'Senior Pastor') ?></td>
                                <td class="small text-secondary italic">
                                    <?= $srv['theme'] ? '"' . e($srv['theme']) . '"' : '—' ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($srv['attendance_headcount'] > 0): ?>
                                        <span class="badge bg-success fs-6"><?= number_format($srv['attendance_headcount']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">Not recorded</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?= BASE_URL ?>attendance/mark.php?service_id=<?= $srv['id'] ?>" class="btn btn-sm btn-outline-primary" title="Record Attendance">
                                        <i class="fas fa-user-check me-1"></i>Take Attendance
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

<!-- Modal: Schedule Service -->
<div class="modal fade" id="createServiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <?= csrfField(); ?>
                <input type="hidden" name="action_create_service" value="1">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fas fa-plus-circle text-primary me-2"></i>Schedule Church Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold small">Service Title <span class="text-danger">*</span></label>
                            <input type="text" name="service_name" class="form-control" placeholder="e.g. Sunday Celebration & Communion Service" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Service Category <span class="text-danger">*</span></label>
                            <select name="service_type" class="form-select" required>
                                <option value="Sunday Service">Sunday Service</option>
                                <option value="Midweek Service">Midweek Service</option>
                                <option value="Prayer Meeting">Prayer Meeting</option>
                                <option value="All-Night Service">All-Night Service</option>
                                <option value="Evangelism">Evangelism</option>
                                <option value="Conference">Conference</option>
                                <option value="Special Program">Special Program</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Service Date <span class="text-danger">*</span></label>
                            <input type="date" name="service_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Start Time <span class="text-danger">*</span></label>
                            <input type="time" name="service_time" class="form-control" value="08:30" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Minister / Preacher</label>
                            <input type="text" name="preacher" class="form-control" placeholder="e.g. Bishop Dr. Sylvester K. Owusu">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Sermon Theme / Prophetic Focus</label>
                            <input type="text" name="theme" class="form-control" placeholder="e.g. Possessing Your Kingdom Inheritance">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small">Service Description / Notes</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Details of communion, baby dedications, special choir ministrations..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Schedule Service</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

