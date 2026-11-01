<?php
// includes/header.php - RACIC Church Management System Global Header
require_once __DIR__ . '/../config/app.php';
requireLogin();

$user = currentUser();
$pageTitle = $pageTitle ?? 'Dashboard';

// Fetch recent notifications for top bar
$db = getDB();
$notifStmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$notifStmt->execute([$user['id']]);
$notifications = $notifStmt->fetchAll();

$unreadCountStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$unreadCountStmt->execute([$user['id']]);
$unreadCount = (int)$unreadCountStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle); ?> | <?= CHURCH_SHORT_NAME ?> - Royal Ambassadors of Christ Int. Church</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    
    <!-- FullCalendar CSS for calendar pages -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">

    <!-- RACIC Theme Stylesheet -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/racic-theme.css">
    
    <!-- Favicon (Inline SVG) -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%230f2c59'/><text x='50%' y='65%' font-size='50' font-weight='bold' fill='%23c59b27' text-anchor='middle'>R</text></svg>">
</head>
<body>

<div id="wrapper">
    <!-- Overlay for mobile drawer -->
    <div id="sidebarOverlay" class="position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50 d-none" style="z-index: 999;"></div>

    <!-- Sidebar Navigation -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- Main Content Wrapper -->
    <div id="content-wrapper">
        <!-- Top Navbar -->
        <header class="top-navbar">
            <div class="d-flex align-items-center gap-3">
                <button type="button" id="sidebarToggle" class="navbar-toggle-btn" title="Toggle Navigation">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="d-none d-md-flex flex-column">
                    <span class="fw-bold text-dark fs-6"><?= CHURCH_NAME ?></span>
                    <small class="text-muted" style="font-size: 0.75rem;"><i class="fas fa-cross text-warning me-1"></i><?= CHURCH_SLOGAN ?></small>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <!-- Today's Date & Time -->
                <div class="d-none d-lg-block text-end me-2">
                    <small class="d-block text-muted" style="font-size: 0.72rem;"><?= date('l') ?></small>
                    <span class="badge bg-light text-dark border px-2 py-1"><i class="far fa-calendar-alt text-primary me-1"></i><?= date('F j, Y') ?></span>
                </div>

                <!-- Notifications Dropdown -->
                <div class="dropdown">
                    <button class="btn btn-light rounded-circle position-relative" type="button" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="width: 40px; height: 40px; padding: 0;">
                        <i class="far fa-bell"></i>
                        <?php if ($unreadCount > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
                                <?= $unreadCount ?>
                            </span>
                        <?php endif; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 py-0" aria-labelledby="notifDropdown" style="width: 320px; max-width: 90vw;">
                        <li class="bg-primary text-white p-3 d-flex justify-content-between align-items-center rounded-top">
                            <span class="fw-bold"><i class="fas fa-bell me-2"></i>Notifications</span>
                            <span class="badge bg-white text-primary"><?= $unreadCount ?> New</span>
                        </li>
                        <div style="max-height: 280px; overflow-y: auto;">
                            <?php if (empty($notifications)): ?>
                                <li class="p-3 text-center text-muted small">No notifications found.</li>
                            <?php else: ?>
                                <?php foreach ($notifications as $n): ?>
                                    <li>
                                        <a class="dropdown-item p-3 border-bottom text-wrap <?= $n['is_read'] ? 'bg-white' : 'bg-light' ?>" href="<?= !empty($n['link']) ? BASE_URL . $n['link'] : '#' ?>">
                                            <div class="fw-semibold text-dark small mb-1"><?= e($n['title']) ?></div>
                                            <div class="text-muted small mb-1"><?= e($n['message']) ?></div>
                                            <div class="text-secondary" style="font-size: 0.7rem;"><i class="far fa-clock me-1"></i><?= formatDate($n['created_at'], 'M d, h:i A') ?></div>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <li class="p-2 text-center bg-light rounded-bottom">
                            <small class="text-muted">RACIC Real-time Alert System</small>
                        </li>
                    </ul>
                </div>

                <!-- User Dropdown -->
                <div class="dropdown">
                    <button class="btn btn-light d-flex align-items-center gap-2 border px-2 py-1 rounded-pill" type="button" id="userMenuDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem; font-weight: 600;">
                            <?= strtoupper(substr($user['full_name'] ?? 'U', 0, 1)) ?>
                        </div>
                        <div class="text-start d-none d-sm-block pe-1">
                            <div class="fw-bold small text-dark" style="line-height: 1.1;"><?= e(explode(' ', $user['full_name'])[0] ?? 'User') ?></div>
                            <span class="badge bg-secondary" style="font-size: 0.65rem;"><?= e($user['role_display'] ?? 'User') ?></span>
                        </div>
                        <i class="fas fa-chevron-down text-muted small"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" aria-labelledby="userMenuDropdown">
                        <li class="px-3 py-2 border-bottom">
                            <div class="fw-bold text-dark"><?= e($user['full_name']) ?></div>
                            <small class="text-muted d-block"><?= e($user['email']) ?></small>
                            <span class="badge bg-primary mt-1"><?= e($user['role_display']) ?></span>
                        </li>
                        <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>admin/users.php"><i class="fas fa-user-circle me-2 text-primary"></i>My Profile & Users</a></li>
                        <?php if (hasRole(['super_admin', 'head_pastor'])): ?>
                            <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>admin/settings.php"><i class="fas fa-cog me-2 text-secondary"></i>System Settings</a></li>
                            <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>admin/audit.php"><i class="fas fa-shield-alt me-2 text-info"></i>Audit Logs</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item py-2 text-danger" href="<?= BASE_URL ?>logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sign Out</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Main Page Container -->
        <main class="flex-grow-1 p-3 p-md-4">
            <!-- Flash Message Alerts -->
            <?= displayFlash(); ?>

