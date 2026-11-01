<?php
// announcements/index.php - Church Announcements & Bulletins
$pageTitle = 'Announcements';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$user = currentUser();
$errors = [];

// Handle New Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create_announcement'])) {
    requireRole(['super_admin', 'head_pastor', 'church_admin', 'dept_leader']);
    if (!validateCSRF()) {
        $errors[] = "Invalid CSRF security token.";
    } else {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $targetAudience = $_POST['target_audience'] ?? 'Entire Church';
        $departmentId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $priority = $_POST['priority'] ?? 'Normal';
        $publishDate = !empty($_POST['publish_date']) ? $_POST['publish_date'] : date('Y-m-d');
        $expiryDate = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;

        if (empty($title)) $errors[] = "Announcement title is required.";
        if (empty($content)) $errors[] = "Announcement text content is required.";

        if (empty($errors)) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO announcements (
                        title, content, target_audience, department_id,
                        priority, publish_date, expiry_date, status,
                        created_by_user_id, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Active', ?, NOW())
                ");
                $stmt->execute([
                    $title, $content, $targetAudience, $departmentId,
                    $priority, $publishDate, $expiryDate, $user['id']
                ]);
                $ancId = (int)$db->lastInsertId();

                logAudit('PUBLISH_ANNOUNCEMENT', 'Announcements', $ancId, "Published announcement '{$title}' to {$targetAudience}");
                flash('success', "Announcement '{$title}' successfully published!");
                header("Location: " . BASE_URL . "announcements/index.php");
                exit;

            } catch (Exception $e) {
                $errors[] = "Error publishing announcement: " . $e->getMessage();
            }
        }
    }
}

// Handle Delete/Archive
if (isset($_GET['delete_id']) && validateCSRF()) {
    requireRole(['super_admin', 'head_pastor', 'church_admin']);
    $delId = (int)$_GET['delete_id'];
    $db->prepare("DELETE FROM announcements WHERE id = ?")->execute([$delId]);
    flash('success', 'Announcement removed.');
    header('Location: ' . BASE_URL . 'announcements/index.php');
    exit;
}

// Fetch Announcements
$announcements = $db->query("
    SELECT a.*, d.name as department_name, u.full_name as author_name
    FROM announcements a
    LEFT JOIN departments d ON d.id = a.department_id
    LEFT JOIN users u ON u.id = a.created_by_user_id
    ORDER BY FIELD(a.priority, 'Urgent', 'Important', 'Normal'), a.publish_date DESC
")->fetchAll();

$departments = $db->query("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name ASC")->fetchAll();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">
            <i class="fas fa-bullhorn text-warning me-2"></i>Church Announcements & Bulletins
        </h3>
        <p class="text-muted mb-0 small">Publish and target urgent notices, pastoral messages, and departmental updates.</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <?php if (hasRole(['super_admin', 'head_pastor', 'church_admin', 'dept_leader'])): ?>
            <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                <i class="fas fa-plus-circle me-1"></i>Publish New Notice
            </button>
        <?php endif; ?>
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

<!-- Announcements List -->
<div class="row g-4">
    <?php if (empty($announcements)): ?>
        <div class="col-12 text-center py-5 text-muted">
            <i class="fas fa-bell-slash fs-1 mb-2 text-secondary"></i>
            <h6>No Active Church Announcements</h6>
        </div>
    <?php else: ?>
        <?php foreach ($announcements as $anc): ?>
            <?php 
                $borderClass = match($anc['priority']) {
                    'Urgent' => 'border-danger border-start border-4',
                    'Important' => 'border-warning border-start border-4',
                    default => 'border-primary border-start border-4'
                };
            ?>
            <div class="col-md-6">
                <div class="church-card h-100 <?= $borderClass ?>">
                    <div class="card-header d-flex justify-content-between align-items-center bg-white">
                        <div class="d-flex align-items-center gap-2">
                            <?= getStatusBadge($anc['priority']) ?>
                            <span class="badge bg-light text-dark border"><i class="fas fa-users me-1"></i><?= e($anc['target_audience']) ?></span>
                            <?php if ($anc['department_name']): ?>
                                <span class="badge bg-info text-dark"><?= e($anc['department_name']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (hasRole(['super_admin', 'head_pastor', 'church_admin'])): ?>
                            <a href="<?= BASE_URL ?>announcements/index.php?delete_id=<?= $anc['id'] ?>&csrf=<?= generateCSRF() ?>" class="text-danger small" title="Delete" data-confirm="Delete this announcement?">
                                <i class="fas fa-trash"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <h5 class="fw-bold text-dark mb-2"><?= e($anc['title']) ?></h5>
                        <p class="text-secondary small mb-3"><?= nl2br(e($anc['content'])) ?></p>
                        <div class="d-flex justify-content-between align-items-center text-muted small border-top pt-2">
                            <span><i class="fas fa-user-edit me-1"></i><?= e($anc['author_name']) ?></span>
                            <span><i class="far fa-clock me-1"></i><?= formatDate($anc['publish_date']) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal: Create Announcement -->
<div class="modal fade" id="createAnnouncementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <?= csrfField(); ?>
                <input type="hidden" name="action_create_announcement" value="1">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fas fa-bullhorn text-warning me-2"></i>Publish Church Notice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold small">Notice Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Workers Alignment Summit" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Priority Level <span class="text-danger">*</span></label>
                            <select name="priority" class="form-select" required>
                                <option value="Normal">Normal</option>
                                <option value="Important" selected>Important</option>
                                <option value="Urgent">Urgent</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Target Audience <span class="text-danger">*</span></label>
                            <select name="target_audience" class="form-select" required>
                                <option value="Entire Church">Entire Church</option>
                                <option value="Specific Department">Specific Department</option>
                                <option value="Church Workers">Church Workers</option>
                                <option value="Department Leaders">Department Leaders</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Department (If Specific Department)</label>
                            <select name="department_id" class="form-select">
                                <option value="">None / General</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Publish Date <span class="text-danger">*</span></label>
                            <input type="date" name="publish_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Expiry Date</label>
                            <input type="date" name="expiry_date" class="form-control" value="<?= date('Y-m-d', strtotime('+14 days')) ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small">Announcement Content <span class="text-danger">*</span></label>
                            <textarea name="content" class="form-control" rows="4" placeholder="Enter bulletin details to be broadcasted..." required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Publish Notice</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

