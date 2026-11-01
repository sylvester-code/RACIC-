<?php
// includes/sidebar.php - RACIC Church Management System Sidebar
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$user = currentUser();

function isActive(string $route, string $currentUri): string {
    return (strpos($currentUri, $route) !== false) ? 'active' : '';
}
?>
<aside id="sidebar">
    <!-- Brand -->
    <a href="<?= BASE_URL ?>dashboard.php" class="sidebar-brand text-decoration-none">
        <div class="brand-logo">
            <i class="fas fa-church"></i>
        </div>
        <div class="overflow-hidden">
            <div class="brand-title"><?= CHURCH_SHORT_NAME ?></div>
            <div class="brand-sub">Church Portal</div>
        </div>
    </a>

    <!-- Navigation Menu -->
    <div class="sidebar-menu">
        <!-- Main Section -->
        <div class="sidebar-heading">Core</div>
        
        <a href="<?= BASE_URL ?>dashboard.php" class="sidebar-nav-item <?= ($currentUri === BASE_URL || str_ends_with($currentUri, 'dashboard.php')) ? 'active' : '' ?>">
            <i class="fas fa-th-large"></i>
            <span>Dashboard</span>
        </a>

        <!-- Ministry & Programs -->
        <div class="sidebar-heading">Ministry & Operations</div>

        <!-- Activity Monitoring (High Priority Module) -->
        <a href="<?= BASE_URL ?>activities/index.php" class="sidebar-nav-item <?= isActive('/activities/', $currentUri) ?>">
            <i class="fas fa-clipboard-check"></i>
            <span>Activity Monitoring</span>
        </a>

        <!-- Member Management -->
        <a href="<?= BASE_URL ?>members/index.php" class="sidebar-nav-item <?= isActive('/members/', $currentUri) ?>">
            <i class="fas fa-users"></i>
            <span>Member Management</span>
        </a>

        <!-- Visitor Management -->
        <a href="<?= BASE_URL ?>visitors/index.php" class="sidebar-nav-item <?= isActive('/visitors/', $currentUri) ?>">
            <i class="fas fa-user-plus"></i>
            <span>Visitor Management</span>
        </a>

        <!-- Department Management -->
        <a href="<?= BASE_URL ?>departments/index.php" class="sidebar-nav-item <?= isActive('/departments/', $currentUri) ?>">
            <i class="fas fa-sitemap"></i>
            <span>Departments</span>
        </a>

        <?php if (!empty($user['department_id'])): ?>
            <a href="<?= BASE_URL ?>departments/view.php?id=<?= $user['department_id'] ?>" class="sidebar-nav-item <?= (strpos($currentUri, 'departments/view.php?id=' . $user['department_id']) !== false) ? 'active' : '' ?>" style="color: #f7d070;">
                <i class="fas fa-door-open text-warning"></i>
                <span>My Department Portal</span>
            </a>
        <?php endif; ?>

        <!-- Attendance Management -->
        <a href="<?= BASE_URL ?>attendance/index.php" class="sidebar-nav-item <?= isActive('/attendance/', $currentUri) ?>">
            <i class="fas fa-user-check"></i>
            <span>Attendance</span>
        </a>

        <!-- Finance Management (Restricted to Finance, Pastor, SuperAdmin) -->
        <?php if (hasRole(['super_admin', 'head_pastor', 'finance_officer'])): ?>
            <div class="sidebar-heading">Finance & Stewardship</div>
            <a href="<?= BASE_URL ?>finance/index.php" class="sidebar-nav-item <?= isActive('/finance/', $currentUri) ?>">
                <i class="fas fa-coins"></i>
                <span>Finance Management</span>
            </a>
        <?php endif; ?>

        <!-- Church Engagement & Communications -->
        <div class="sidebar-heading">Communication & Media</div>

        <a href="<?= BASE_URL ?>calendar/index.php" class="sidebar-nav-item <?= isActive('/calendar/', $currentUri) ?>">
            <i class="fas fa-calendar-alt"></i>
            <span>Church Calendar</span>
        </a>

        <a href="<?= BASE_URL ?>announcements/index.php" class="sidebar-nav-item <?= isActive('/announcements/', $currentUri) ?>">
            <i class="fas fa-bullhorn"></i>
            <span>Announcements</span>
        </a>

        <a href="<?= BASE_URL ?>media/index.php" class="sidebar-nav-item <?= isActive('/media/', $currentUri) ?>">
            <i class="fas fa-photo-film"></i>
            <span>Media Library</span>
        </a>

        <!-- Reports -->
        <div class="sidebar-heading">Intelligence & Analytics</div>

        <a href="<?= BASE_URL ?>reports/index.php" class="sidebar-nav-item <?= isActive('/reports/', $currentUri) ?>">
            <i class="fas fa-file-invoice"></i>
            <span>Reports Center</span>
        </a>

        <!-- Administration (Super Admin, Head Pastor, Church Admin) -->
        <?php if (hasRole(['super_admin', 'head_pastor', 'church_admin'])): ?>
            <div class="sidebar-heading">System Administration</div>

            <a href="<?= BASE_URL ?>admin/users.php" class="sidebar-nav-item <?= isActive('/admin/users.php', $currentUri) ?>">
                <i class="fas fa-user-shield"></i>
                <span>User Accounts</span>
            </a>

            <?php if (hasRole(['super_admin', 'head_pastor'])): ?>
                <a href="<?= BASE_URL ?>admin/roles.php" class="sidebar-nav-item <?= isActive('/admin/roles.php', $currentUri) ?>">
                    <i class="fas fa-id-badge"></i>
                    <span>Roles & Permissions</span>
                </a>

                <a href="<?= BASE_URL ?>admin/audit.php" class="sidebar-nav-item <?= isActive('/admin/audit.php', $currentUri) ?>">
                    <i class="fas fa-shield-halved"></i>
                    <span>Audit Logs</span>
                </a>

                <a href="<?= BASE_URL ?>admin/settings.php" class="sidebar-nav-item <?= isActive('/admin/settings.php', $currentUri) ?>">
                    <i class="fas fa-sliders-h"></i>
                    <span>System Settings</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Sidebar Footer User Profile -->
    <div class="sidebar-user">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2 overflow-hidden">
                <div class="rounded-circle bg-warning text-dark fw-bold d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.8rem; flex-shrink: 0;">
                    <?= strtoupper(substr($user['full_name'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="text-truncate">
                    <div class="text-white small fw-semibold text-truncate"><?= e($user['full_name']) ?></div>
                    <div class="text-white-50" style="font-size: 0.7rem;"><?= e($user['role_display']) ?></div>
                </div>
            </div>
            <a href="<?= BASE_URL ?>logout.php" class="text-white-50 hover-white" title="Sign Out">
                <i class="fas fa-power-off"></i>
            </a>
        </div>
    </div>
</aside>

