<?php
// attendance/mark.php - Record Service Headcount & Member Roll Call
$pageTitle = 'Record Attendance';
require_once __DIR__ . '/../includes/header.php';
requireRole(['super_admin', 'head_pastor', 'church_admin', 'dept_leader', 'church_worker']);

$db = getDB();
$user = currentUser();
$errors = [];

$services = $db->query("SELECT id, service_name, service_type, service_date FROM services ORDER BY service_date DESC LIMIT 15")->fetchAll();
$activities = $db->query("SELECT id, activity_code, title, activity_date FROM activities ORDER BY activity_date DESC LIMIT 15")->fetchAll();
$departments = $db->query("SELECT id, name, code FROM departments WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$members = $db->query("SELECT id, member_code, first_name, last_name, phone FROM members WHERE membership_status = 'Active' ORDER BY first_name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $errors[] = "Invalid CSRF security token.";
    } else {
        $serviceId = !empty($_POST['service_id']) ? (int)$_POST['service_id'] : null;
        $activityId = !empty($_POST['activity_id']) ? (int)$_POST['activity_id'] : null;
        $departmentId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $attendanceDate = !empty($_POST['attendance_date']) ? $_POST['attendance_date'] : date('Y-m-d');
        $menCount = (int)($_POST['men_count'] ?? 0);
        $womenCount = (int)($_POST['women_count'] ?? 0);
        $childrenCount = (int)($_POST['children_count'] ?? 0);
        $totalCount = $menCount + $womenCount + $childrenCount;
        $notes = trim($_POST['notes'] ?? '');
        $presentMembers = $_POST['present_members'] ?? [];

        if (empty($attendanceDate)) $errors[] = "Attendance date is required.";
        if ($totalCount <= 0 && empty($presentMembers)) {
            $errors[] = "Please provide headcounts or check in present members.";
        }

        // If presentMembers given and totalCount was not manually specified, calculate total
        if ($totalCount === 0 && !empty($presentMembers)) {
            $totalCount = count($presentMembers);
        }

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                $stmt = $db->prepare("
                    INSERT INTO attendance (
                        service_id, activity_id, department_id, attendance_date,
                        men_count, women_count, children_count, total_count,
                        recorded_by_user_id, notes, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $serviceId, $activityId, $departmentId, $attendanceDate,
                    $menCount, $womenCount, $childrenCount, $totalCount,
                    $user['id'], $notes
                ]);
                $attendanceId = (int)$db->lastInsertId();

                // Save individual member roll call check-ins
                if (!empty($presentMembers) && is_array($presentMembers)) {
                    $detailStmt = $db->prepare("
                        INSERT INTO attendance_details (attendance_id, member_id, check_in_time, status)
                        VALUES (?, ?, CURTIME(), 'Present')
                    ");
                    foreach ($presentMembers as $mId) {
                        $mId = (int)$mId;
                        if ($mId > 0) {
                            $detailStmt->execute([$attendanceId, $mId]);
                        }
                    }
                }

                $db->commit();
                logAudit('RECORD_ATTENDANCE', 'Attendance', $attendanceId, "Recorded attendance of {$totalCount} for date {$attendanceDate}");
                flash('success', "Service attendance of {$totalCount} successfully recorded!");
                header("Location: " . BASE_URL . "attendance/index.php");
                exit;

            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = "Error recording attendance: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold text-dark mb-0"><i class="fas fa-clipboard-check text-primary me-2"></i>Record Service Attendance & Headcount</h4>
                <small class="text-muted">Enter congregation headcount breakdown or perform individual member roll call.</small>
            </div>
            <a href="<?= BASE_URL ?>attendance/index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Back
            </a>
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

        <form method="POST" action="">
            <?= csrfField(); ?>

            <!-- Headcount Form Card -->
            <div class="church-card mb-4">
                <div class="card-header">
                    <span><i class="fas fa-calculator text-primary me-2"></i>Service Gathering & Headcount Statistics</span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Church Service (Optional)</label>
                            <select name="service_id" class="form-select">
                                <option value="">Select Service / Meeting...</option>
                                <?php foreach ($services as $s): ?>
                                    <option value="<?= $s['id'] ?>">
                                        <?= e($s['service_name']) ?> (<?= formatDate($s['service_date']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Or Church Activity Program</label>
                            <select name="activity_id" class="form-select">
                                <option value="">Select Activity...</option>
                                <?php foreach ($activities as $a): ?>
                                    <option value="<?= $a['id'] ?>">
                                        <?= e($a['title']) ?> (<?= e($a['activity_code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Department (If departmental meeting)</label>
                            <select name="department_id" class="form-select">
                                <option value="">Entire Church / General...</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['id'] ?>">
                                        <?= e($d['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Attendance Date <span class="text-danger">*</span></label>
                            <input type="date" name="attendance_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Total Congregation Headcount</label>
                            <input type="text" id="totalDisplay" class="form-control fw-bold bg-light fs-5 text-primary text-center" value="0" readonly>
                        </div>

                        <!-- Breakdown Counters -->
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-primary"><i class="fas fa-male me-1"></i>Men Count</label>
                            <input type="number" name="men_count" id="menInput" class="form-control form-control-lg headcount-calc" min="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small" style="color: #c59b27;"><i class="fas fa-female me-1"></i>Women Count</label>
                            <input type="number" name="women_count" id="womenInput" class="form-control form-control-lg headcount-calc" min="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-success"><i class="fas fa-child me-1"></i>Children Count</label>
                            <input type="number" name="children_count" id="childrenInput" class="form-control form-control-lg headcount-calc" min="0" value="0">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small">Service Notes & Spiritual Atmosphere</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Holy Communion Sunday, revival atmosphere, altar call response..."></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Optional Individual Member Roll Call Check-in -->
            <div class="church-card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-user-check text-success me-2"></i>Individual Member Roll Call (Optional Check-in)</span>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAllCheckboxes(true)">Check All</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAllCheckboxes(false)">Uncheck All</button>
                    </div>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Check the boxes beside the members who were present for this service.</p>
                    <div class="row g-2" style="max-height: 280px; overflow-y: auto;">
                        <?php foreach ($members as $m): ?>
                            <div class="col-sm-6 col-md-4">
                                <div class="form-check p-2 border rounded bg-white">
                                    <input class="form-check-input member-check ms-1 me-2" type="checkbox" name="present_members[]" value="<?= $m['id'] ?>" id="mem_<?= $m['id'] ?>">
                                    <label class="form-check-label small fw-semibold text-dark" for="mem_<?= $m['id'] ?>">
                                        <?= e($m['first_name'] . ' ' . $m['last_name']) ?>
                                        <span class="text-muted d-block" style="font-size: 0.7rem;"><?= e($m['member_code']) ?></span>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mb-5">
                <a href="<?= BASE_URL ?>attendance/index.php" class="btn btn-light border">Cancel</a>
                <button type="submit" class="btn btn-primary px-4 shadow-sm">
                    <i class="fas fa-check-circle me-1"></i>Save Attendance Record
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function calculateTotal() {
        const men = parseInt(document.getElementById('menInput').value) || 0;
        const women = parseInt(document.getElementById('womenInput').value) || 0;
        const children = parseInt(document.getElementById('childrenInput').value) || 0;
        document.getElementById('totalDisplay').value = men + women + children;
    }

    document.querySelectorAll('.headcount-calc').forEach(function(input) {
        input.addEventListener('input', calculateTotal);
    });

    function toggleAllCheckboxes(checked) {
        document.querySelectorAll('.member-check').forEach(function(cb) {
            cb.checked = checked;
        });
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

