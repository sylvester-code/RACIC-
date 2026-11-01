<?php
// admin/settings.php - System & Church Portal Configuration
$pageTitle = 'System Settings';
require_once __DIR__ . '/../includes/header.php';
requireRole(['super_admin', 'head_pastor']);

$db = getDB();
$user = currentUser();

// Handle SQL Backup Download
if (isset($_GET['download_sql']) && validateCSRF()) {
    $sqlFile = BASE_PATH . 'database/racic_church_management.sql';
    if (file_exists($sqlFile)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="racic_church_management_backup_' . date('Ymd_His') . '.sql"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($sqlFile));
        readfile($sqlFile);
        exit;
    } else {
        flash('danger', 'SQL dump file not found on disk.');
    }
}
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">
            <i class="fas fa-sliders-h text-primary me-2"></i>System & Church Configuration
        </h3>
        <p class="text-muted mb-0 small">Manage ecclesiastical profile, environment variables, security defaults, and database backups.</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <a href="<?= BASE_URL ?>admin/settings.php?download_sql=1&csrf=<?= generateCSRF() ?>" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-download me-1"></i>Download SQL Database Backup
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Church Identity Card -->
    <div class="col-lg-7">
        <div class="church-card">
            <div class="card-header">
                <span><i class="fas fa-church text-primary me-2"></i>Ecclesiastical Identity & Headquarters</span>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-bold small">Church Name</label>
                        <input type="text" class="form-control" value="<?= CHURCH_NAME ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Acronym / Short Name</label>
                        <input type="text" class="form-control" value="<?= CHURCH_SHORT_NAME ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Official Currency</label>
                        <input type="text" class="form-control" value="<?= CURRENCY_SYMBOL ?> (Ghana Cedis)" readonly>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold small">Slogan / Vision Statement</label>
                        <input type="text" class="form-control" value="<?= CHURCH_SLOGAN ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Headquarters Cathedral Address</label>
                        <input type="text" class="form-control" value="<?= CHURCH_ADDRESS ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">General Contact Phone & Email</label>
                        <input type="text" class="form-control" value="<?= CHURCH_PHONE ?> &bull; <?= CHURCH_EMAIL ?>" readonly>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Diagnostics & Environment -->
    <div class="col-lg-5">
        <div class="church-card mb-4">
            <div class="card-header">
                <span><i class="fas fa-server text-secondary me-2"></i>Server & Technology Stack</span>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">PHP Version:</span>
                        <strong class="text-dark"><?= PHP_VERSION ?> (PDO Enabled)</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Database Engine:</span>
                        <strong class="text-dark">MySQL / MariaDB (InnoDB, UTF8mb4)</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Database Name:</span>
                        <strong class="text-primary font-monospace"><?= DB_NAME ?></strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Session Lifetime:</span>
                        <strong class="text-dark"><?= SESSION_TIMEOUT / 60 ?> minutes (Automatic Timeout)</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Timezone:</span>
                        <strong class="text-dark"><?= APP_TIMEZONE ?></strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Upload Max Size:</span>
                        <strong class="text-dark">10 MB (Whitelisted Extensions)</strong>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Database Maintenance Card -->
        <div class="church-card">
            <div class="card-header">
                <span><i class="fas fa-database text-warning me-2"></i>Database Backups & Disaster Recovery</span>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3">
                    Download the complete SQL export file of the church database schema and records for local backup or server deployment.
                </p>
                <a href="<?= BASE_URL ?>admin/settings.php?download_sql=1&csrf=<?= generateCSRF() ?>" class="btn btn-outline-primary w-100 btn-sm">
                    <i class="fas fa-file-export me-1"></i>Export & Download SQL Backup
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

