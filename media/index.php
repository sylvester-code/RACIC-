<?php
// media/index.php - Church Media Library & Archives
$pageTitle = 'Media Library';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$user = currentUser();
$errors = [];

// Handle Media Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_upload_media'])) {
    if (!validateCSRF()) {
        $errors[] = "Invalid CSRF security token.";
    } else {
        $title = trim($_POST['title'] ?? '');
        $category = $_POST['category'] ?? 'Picture';
        $departmentId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $activityId = !empty($_POST['activity_id']) ? (int)$_POST['activity_id'] : null;

        if (empty($title)) $errors[] = "Media title is required.";

        if (empty($_FILES['media_file']['name'])) {
            $errors[] = "Please select a file to upload.";
        } else {
            try {
                $filePath = uploadFile('media_file', 'media', ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'docx']);
                $origName = $_FILES['media_file']['name'];
                $fileSize = $_FILES['media_file']['size'];
                $fileType = pathinfo($origName, PATHINFO_EXTENSION);

                $stmt = $db->prepare("
                    INSERT INTO media (
                        title, file_name, file_path, file_type, file_size,
                        category, department_id, activity_id, uploaded_by_user_id, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $title, $origName, $filePath, $fileType, $fileSize,
                    $category, $departmentId, $activityId, $user['id']
                ]);
                $mediaId = (int)$db->lastInsertId();

                logAudit('UPLOAD_MEDIA', 'Media', $mediaId, "Uploaded media '{$title}' ({$category})");
                flash('success', "Media file '{$title}' uploaded successfully!");
                header("Location: " . BASE_URL . "media/index.php");
                exit;

            } catch (Exception $e) {
                $errors[] = "Upload failed: " . $e->getMessage();
            }
        }
    }
}

// Handle Delete
if (isset($_GET['delete_id']) && validateCSRF()) {
    requireRole(['super_admin', 'head_pastor', 'church_admin']);
    $delId = (int)$_GET['delete_id'];
    $m = $db->prepare("SELECT file_path FROM media WHERE id = ?");
    $m->execute([$delId]);
    $row = $m->fetch();
    if ($row) {
        if (file_exists(UPLOAD_PATH . $row['file_path'])) {
            @unlink(UPLOAD_PATH . $row['file_path']);
        }
        $db->prepare("DELETE FROM media WHERE id = ?")->execute([$delId]);
        flash('success', 'File deleted successfully.');
    }
    header('Location: ' . BASE_URL . 'media/index.php');
    exit;
}

// Filters
$catFilter = $_GET['category'] ?? '';
$deptFilter = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : 0;

$sql = "
    SELECT m.*, d.name as department_name, act.title as activity_title, u.full_name as uploader_name
    FROM media m
    LEFT JOIN departments d ON d.id = m.department_id
    LEFT JOIN activities act ON act.id = m.activity_id
    LEFT JOIN users u ON u.id = m.uploaded_by_user_id
    WHERE 1=1
";
$params = [];

if (!empty($catFilter)) {
    $sql .= " AND m.category = ?";
    $params[] = $catFilter;
}
if (!empty($deptFilter)) {
    $sql .= " AND m.department_id = ?";
    $params[] = $deptFilter;
}

$sql .= " ORDER BY m.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$mediaFiles = $stmt->fetchAll();

$departments = $db->query("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$activities = $db->query("SELECT id, title, activity_code FROM activities ORDER BY activity_date DESC LIMIT 20")->fetchAll();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">
            <i class="fas fa-photo-film text-primary me-2"></i>Media & Document Library
        </h3>
        <p class="text-muted mb-0 small">Centralized repository for church pictures, flyers, sermons, official letters, and activity archives.</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#uploadMediaModal">
            <i class="fas fa-upload me-1"></i>Upload Media File
        </button>
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

<!-- Filter Bar -->
<div class="church-card mb-4 no-print">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>media/index.php" class="row g-2 align-items-center">
            <div class="col-md-5">
                <select name="category" class="form-select">
                    <option value="">All Media Categories</option>
                    <option value="Picture" <?= $catFilter === 'Picture' ? 'selected' : '' ?>>Pictures & Photos</option>
                    <option value="Flyer" <?= $catFilter === 'Flyer' ? 'selected' : '' ?>>Program Flyers</option>
                    <option value="Church Document" <?= $catFilter === 'Church Document' ? 'selected' : '' ?>>Church Documents & Constitution</option>
                    <option value="Activity Report File" <?= $catFilter === 'Activity Report File' ? 'selected' : '' ?>>Activity Report Files</option>
                    <option value="Other" <?= $catFilter === 'Other' ? 'selected' : '' ?>>Other Archives</option>
                </select>
            </div>
            <div class="col-md-4">
                <select name="department_id" class="form-select">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $deptFilter == $d['id'] ? 'selected' : '' ?>>
                            <?= e($d['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button>
                <a href="<?= BASE_URL ?>media/index.php" class="btn btn-light border"><i class="fas fa-redo"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Media Gallery Grid -->
<div class="row g-4">
    <?php if (empty($mediaFiles)): ?>
        <div class="col-12 text-center py-5 text-muted">
            <i class="fas fa-images fs-1 mb-2 text-secondary"></i>
            <h6>No media or document files found in this category.</h6>
        </div>
    <?php else: ?>
        <?php foreach ($mediaFiles as $med): ?>
            <?php 
                $isImg = in_array(strtolower($med['file_type']), ['jpg', 'jpeg', 'png', 'webp']);
                $fileUrl = UPLOAD_URL . e($med['file_path']);
            ?>
            <div class="col-sm-6 col-md-4 col-lg-3">
                <div class="church-card h-100 d-flex flex-column">
                    <?php if ($isImg): ?>
                        <div style="height: 160px; overflow: hidden; background: #000; display: flex; align-items: center; justify-content: center;">
                            <img src="<?= $fileUrl ?>" alt="<?= e($med['title']) ?>" style="max-height: 100%; max-width: 100%; object-fit: cover;">
                        </div>
                    <?php else: ?>
                        <div style="height: 160px; background: #e2e8f0; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-file-pdf fs-1 text-danger"></i>
                        </div>
                    <?php endif; ?>

                    <div class="card-body p-3 flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge bg-light text-dark border" style="font-size: 0.65rem;"><?= e($med['category']) ?></span>
                            <small class="text-muted" style="font-size: 0.7rem;"><?= round($med['file_size'] / 1024) ?> KB</small>
                        </div>
                        <h6 class="fw-bold text-dark text-truncate mb-1" title="<?= e($med['title']) ?>"><?= e($med['title']) ?></h6>
                        <small class="text-muted d-block text-truncate"><?= e($med['department_name'] ?: 'Church Wide') ?></small>
                    </div>

                    <div class="card-footer bg-white border-top p-2 d-flex justify-content-between align-items-center">
                        <a href="<?= $fileUrl ?>" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2" download>
                            <i class="fas fa-download me-1"></i>Open / Download
                        </a>
                        <?php if (hasRole(['super_admin', 'head_pastor', 'church_admin'])): ?>
                            <a href="<?= BASE_URL ?>media/index.php?delete_id=<?= $med['id'] ?>&csrf=<?= generateCSRF() ?>" class="text-danger small" title="Delete" data-confirm="Permanently delete this file?">
                                <i class="fas fa-trash"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal: Upload Media -->
<div class="modal fade" id="uploadMediaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" enctype="multipart/form-data">
                <?= csrfField(); ?>
                <input type="hidden" name="action_upload_media" value="1">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fas fa-upload text-primary me-2"></i>Upload to Media Library</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">File Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Easter Convention Flyer / Sunday Photo Reel" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Category <span class="text-danger">*</span></label>
                        <select name="category" class="form-select" required>
                            <option value="Picture">Picture / Photograph</option>
                            <option value="Flyer">Event Flyer</option>
                            <option value="Church Document">Church Document / Constitution</option>
                            <option value="Activity Report File">Activity Report Document</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Select File <span class="text-danger">*</span></label>
                        <input type="file" name="media_file" class="form-control" required accept="image/*,application/pdf,.docx">
                        <small class="text-muted">Supported: JPG, PNG, WEBP, PDF, DOCX (Max: 10MB)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Associated Department</label>
                        <select name="department_id" class="form-select">
                            <option value="">General Church</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Linked Activity Program</label>
                        <select name="activity_id" class="form-select">
                            <option value="">None / General</option>
                            <?php foreach ($activities as $a): ?>
                                <option value="<?= $a['id'] ?>"><?= e($a['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Upload File</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

