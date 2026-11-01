<?php
// calendar/index.php - Interactive Church Calendar
$pageTitle = 'Church Calendar';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$user = currentUser();
$errors = [];

// Handle Event Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create_event'])) {
    if (!validateCSRF()) {
        $errors[] = "Invalid CSRF security token.";
    } else {
        $title = trim($_POST['title'] ?? '');
        $eventType = $_POST['event_type'] ?? 'Special Program';
        $departmentId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $startDate = $_POST['start_date'] ?? date('Y-m-d');
        $startTime = $_POST['start_time'] ?? '09:00';
        $endDate = $_POST['end_date'] ?? $startDate;
        $endTime = $_POST['end_time'] ?? '12:00';
        $venue = trim($_POST['venue'] ?? 'Main Sanctuary');
        $color = $_POST['color'] ?? '#0f2c59';
        $description = trim($_POST['description'] ?? '');

        if (empty($title)) $errors[] = "Event title is required.";

        if (empty($errors)) {
            try {
                $startDt = $startDate . ' ' . $startTime . ':00';
                $endDt = $endDate . ' ' . $endTime . ':00';

                $stmt = $db->prepare("
                    INSERT INTO events (
                        title, event_type, department_id, start_datetime, end_datetime,
                        venue, description, color, created_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $title, $eventType, $departmentId, $startDt, $endDt,
                    $venue, $description, $color, $user['id']
                ]);
                $evId = (int)$db->lastInsertId();

                logAudit('CREATE_EVENT', 'Calendar', $evId, "Created calendar event '{$title}'");
                flash('success', "Event '{$title}' successfully added to the church calendar!");
                header("Location: " . BASE_URL . "calendar/index.php");
                exit;

            } catch (Exception $e) {
                $errors[] = "Error adding event: " . $e->getMessage();
            }
        }
    }
}

$departments = $db->query("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name ASC")->fetchAll();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">
            <i class="fas fa-calendar-alt text-primary me-2"></i>RACIC Church Calendar
        </h3>
        <p class="text-muted mb-0 small">Master schedule of Sunday services, midweek gatherings, evangelism crusades, rehearsals, and conferences.</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <?php if (hasRole(['super_admin', 'head_pastor', 'church_admin', 'dept_leader'])): ?>
            <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#createEventModal">
                <i class="fas fa-calendar-plus me-1"></i>Add Calendar Event
            </button>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>activities/create.php" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-clipboard-check me-1"></i>Schedule Activity
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

<!-- Legend Badges -->
<div class="d-flex flex-wrap gap-2 mb-3">
    <span class="badge" style="background-color: #198754;"><i class="fas fa-church me-1"></i>Church Service</span>
    <span class="badge" style="background-color: #0f2c59;"><i class="fas fa-users me-1"></i>Conferences</span>
    <span class="badge" style="background-color: #6f42c1;"><i class="fas fa-music me-1"></i>Department Activities</span>
    <span class="badge" style="background-color: #d63384;"><i class="fas fa-cross me-1"></i>Crusades / Evangelism</span>
    <span class="badge" style="background-color: #fd7e14;"><i class="fas fa-star me-1"></i>Special Programs</span>
</div>

<!-- Calendar Box -->
<div class="church-card">
    <div class="card-body p-3 p-md-4">
        <div id="churchCalendar" style="min-height: 650px;"></div>
    </div>
</div>

<!-- Modal: Event Details Display -->
<div class="modal fade" id="eventDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalEventTitle">Event Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <span class="badge bg-primary" id="modalEventType">Type</span>
                    <span class="badge bg-light text-dark border ms-1" id="modalEventDept">Dept</span>
                </div>
                <div class="mb-2">
                    <small class="text-muted d-block">Schedule</small>
                    <strong class="text-dark" id="modalEventTime">Time</strong>
                </div>
                <div class="mb-2">
                    <small class="text-muted d-block">Venue</small>
                    <span class="text-dark fw-semibold" id="modalEventVenue">Venue</span>
                </div>
                <div class="mb-0">
                    <small class="text-muted d-block mb-1">Description / Notes</small>
                    <div class="p-2 bg-light rounded border text-secondary small" id="modalEventDesc">Description</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Event -->
<div class="modal fade" id="createEventModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <?= csrfField(); ?>
                <input type="hidden" name="action_create_event" value="1">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fas fa-calendar-plus text-primary me-2"></i>Add Church Calendar Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold small">Event Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Annual Miracle & Prophetic Conference" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Event Category <span class="text-danger">*</span></label>
                            <select name="event_type" class="form-select" required>
                                <option value="Church Service">Church Service</option>
                                <option value="Conference">Conference</option>
                                <option value="Crusade">Crusade</option>
                                <option value="Evangelism">Evangelism Outreach</option>
                                <option value="Departmental Activity">Departmental Activity</option>
                                <option value="Special Program" selected>Special Program</option>
                                <option value="Meeting">Leaders Meeting</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Start Time <span class="text-danger">*</span></label>
                            <input type="time" name="start_time" class="form-control" value="09:00" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">End Date <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">End Time <span class="text-danger">*</span></label>
                            <input type="time" name="end_time" class="form-control" value="12:00" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Venue / Hall</label>
                            <input type="text" name="venue" class="form-control" value="RACIC Main Sanctuary" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Ministry / Department</label>
                            <select name="department_id" class="form-select">
                                <option value="">Entire Church</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Color Tag</label>
                            <select name="color" class="form-select">
                                <option value="#0f2c59" selected>Royal Navy</option>
                                <option value="#198754">Emerald Green</option>
                                <option value="#c59b27">Royal Gold</option>
                                <option value="#6f42c1">Royal Purple</option>
                                <option value="#d63384">Magenta</option>
                                <option value="#dc3545">Crimson</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small">Event Description & Notes</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Guest speakers, prayer points, attendees brief..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Publish to Calendar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('churchCalendar');
    if (calendarEl) {
        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            themeSystem: 'bootstrap5',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,listMonth'
            },
            events: '<?= BASE_URL ?>calendar/events_feed.php',
            eventClick: function(info) {
                if (info.event.url) {
                    // Let default link proceed
                    return true;
                }
                info.jsEvent.preventDefault();
                const props = info.event.extendedProps;
                document.getElementById('modalEventTitle').textContent = info.event.title;
                document.getElementById('modalEventType').textContent = props.type || 'Event';
                document.getElementById('modalEventDept').textContent = props.department || 'Church Wide';
                document.getElementById('modalEventVenue').textContent = props.venue || 'Sanctuary';
                document.getElementById('modalEventTime').textContent = info.event.start.toLocaleString() + (info.event.end ? ' to ' + info.event.end.toLocaleString() : '');
                document.getElementById('modalEventDesc').textContent = props.description || 'No additional details.';
                const detailModal = new bootstrap.Modal(document.getElementById('eventDetailModal'));
                detailModal.show();
            }
        });
        calendar.render();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

