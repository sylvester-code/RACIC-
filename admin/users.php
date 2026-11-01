<?php
// admin/users.php - User Accounts & Role Assignment
$pageTitle = 'User Management';
require_once __DIR__ . '/../includes/header.php';
requireRole(['super_admin', 'head_pastor']);

$db = getDB();
$user = currentUser();
$errors = [];

// Handle New User Creation by Super Admin / Head Pastor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create_user'])) {
    if (!validateCSRF()) {
        $errors[] = "Invalid CSRF security token.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $roleId = (int)($_POST['role_id'] ?? 0);
        $departmentId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $phone = trim($_POST['phone'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if (empty($username)) $errors[] = "Username is required.";
        if (preg_match('/\s/', $username)) $errors[] = "Username must not contain spaces.";
        if (empty($fullName)) $errors[] = "Full name is required.";
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email address is required.";
        if (empty($password) || strlen($password) < 4) $errors[] = "Password must be at least 4 characters.";
        if ($roleId <= 0) $errors[] = "Please select a user role.";

        // Username or Email uniqueness check
        $chk = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $chk->execute([$username, $email]);
        if ($chk->fetch()) {
            $errors[] = "A user account with this username or email address already exists.";
        }

        if (empty($errors)) {
            try {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $ins = $db->prepare("
                    INSERT INTO users (role_id, username, department_id, full_name, email, password, phone, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $ins->execute([$roleId, $username, $departmentId, $fullName, $email, $hash, $phone, $status]);
                $newUserId = (int)$db->lastInsertId();

                // If department selected and role is dept_leader, also update department leader_id
                if ($departmentId && $roleId === 4) {
                    $updDept = $db->prepare("UPDATE departments SET leader_id = ? WHERE id = ?");
                    $updDept->execute([$newUserId, $departmentId]);
                }

                logAudit('CREATE_USER', 'Administration', $newUserId, "Super Admin created account for {$fullName} (Username: {$username})");
                flash('success', "User account for '{$fullName}' (Username: {$username}) created successfully!");
                header("Location: " . BASE_URL . "admin/users.php");
                exit;

            } catch (Exception $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Handle Status Toggle (Activate / Deactivate)
if (isset($_GET['toggle_id']) && isset($_GET['new_status']) && validateCSRF()) {
    requireRole(['super_admin', 'head_pastor']);
    $toggleId = (int)$_GET['toggle_id'];
    $newStatus = in_array($_GET['new_status'], ['active', 'inactive', 'suspended']) ? $_GET['new_status'] : 'active';

    if ($toggleId === $user['id']) {
        flash('danger', 'You cannot change the status of your own currently active account.');
    } else {
        $upd = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
        $upd->execute([$newStatus, $toggleId]);
        logAudit('TOGGLE_USER_STATUS', 'Administration', $toggleId, "Changed user status to {$newStatus}");
        flash('success', "User status updated to {$newStatus}.");
    }
    header('Location: ' . BASE_URL . 'admin/users.php');
    exit;
}

// Handle Password Reset by Super Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_reset_pwd'])) {
    requireRole(['super_admin', 'head_pastor']);
    if (!validateCSRF()) {
        flash('danger', 'Invalid CSRF token.');
    } else {
        $targetUserId = (int)$_POST['target_user_id'];
        $newPwd = $_POST['new_password'] ?? '';
        if (strlen($newPwd) >= 4) {
            $hash = password_hash($newPwd, PASSWORD_BCRYPT);
            $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $targetUserId]);
            logAudit('RESET_PASSWORD', 'Administration', $targetUserId, "Reset password for user ID {$targetUserId}");
            flash('success', 'User password successfully updated.');
        } else {
            flash('danger', 'Password must be at least 4 characters.');
        }
        header('Location: ' . BASE_URL . 'admin/users.php');
        exit;
    }
}

// Fetch Users List
$sql = "
    SELECT u.*, r.display_name as role_display, d.name as department_name, d.code as department_code
    FROM users u
    INNER JOIN roles r ON r.id = u.role_id
    LEFT JOIN departments d ON d.id = u.department_id
    ORDER BY u.id ASC
";
$usersList = $db->query($sql)->fetchAll();

$roles = $db->query("SELECT id, display_name FROM roles ORDER BY id ASC")->fetchAll();
$departments = $db->query("SELECT id, name, code FROM departments WHERE status = 'active' ORDER BY name ASC")->fetchAll();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">
            <i class="fas fa-user-shield text-primary me-2"></i>User & Role Administration
        </h3>
        <p class="text-muted mb-0 small">Create system accounts, assign passwords and roles to administrators, and grant department leaders access to their dedicated portals.</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#createUserModal">
            <i class="fas fa-user-plus me-1"></i>Create New User Account
        </button>
        <a href="<?= BASE_URL ?>admin/roles.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-id-badge me-1"></i>Roles Matrix
        </a>
        <a href="<?= BASE_URL ?>admin/audit.php" class="btn btn-outline-dark btn-sm">
            <i class="fas fa-shield-alt me-1"></i>Audit Trail
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

<!-- Users Table -->
<div class="church-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-users-cog text-primary me-2"></i>Configured User Accounts (<?= count($usersList) ?>)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="usersTable">
                <thead>
                    <tr>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Email Address</th>
                        <th>Assigned Role</th>
                        <th>Department Portal</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usersList as $u): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width: 34px; height: 34px; font-size: 0.8rem;">
                                        <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?= e($u['full_name']) ?></div>
                                        <small class="text-muted"><?= e($u['phone'] ?: 'No phone') ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-primary border font-monospace fs-6"><?= e($u['username']) ?></span>
                            </td>
                            <td><small class="text-muted"><?= e($u['email']) ?></small></td>
                            <td><span class="badge bg-secondary"><?= e($u['role_display']) ?></span></td>
                            <td>
                                <?php if ($u['department_id']): ?>
                                    <a href="<?= BASE_URL ?>departments/view.php?id=<?= $u['department_id'] ?>" class="badge bg-info text-dark text-decoration-none">
                                        <i class="fas fa-sitemap me-1"></i><?= e($u['department_name'] ?? 'Department') ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">All Departments / General</span>
                                <?php endif; ?>
                            </td>
                            <td><?= getStatusBadge($u['status']) ?></td>
                            <td class="small text-muted"><?= formatDateTime($u['last_login']) ?></td>
                            <td class="text-end">
                                <div class="dropdown d-inline-block">
                                    <button class="btn btn-sm btn-light border py-0 px-2" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                        <li>
                                            <a class="dropdown-item py-1" href="#" onclick="openResetModal(<?= $u['id'] ?>, '<?= e(addslashes($u['full_name'])) ?>', '<?= e(addslashes($u['username'])) ?>')">
                                                <i class="fas fa-key me-2 text-warning"></i>Change Password
                                            </a>
                                        </li>
                                        <?php if ($u['id'] !== $user['id']): ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <?php if ($u['status'] === 'active'): ?>
                                                <li>
                                                    <a class="dropdown-item py-1 text-danger" href="<?= BASE_URL ?>admin/users.php?toggle_id=<?= $u['id'] ?>&new_status=inactive&csrf=<?= generateCSRF() ?>" data-confirm="Deactivate this user account?">
                                                        <i class="fas fa-user-slash me-2"></i>Deactivate Account
                                                    </a>
                                                </li>
                                            <?php else: ?>
                                                <li>
                                                    <a class="dropdown-item py-1 text-success" href="<?= BASE_URL ?>admin/users.php?toggle_id=<?= $u['id'] ?>&new_status=active&csrf=<?= generateCSRF() ?>">
                                                        <i class="fas fa-user-check me-2"></i>Activate Account
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Create User -->
<div class="modal fade" id="createUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <?= csrfField(); ?>
                <input type="hidden" name="action_create_user" value="1">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fas fa-user-plus text-primary me-2"></i>Create New User Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Username (Login ID) <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" placeholder="e.g. pastor_darko, finance_officer" required>
                            <small class="text-muted">Username used to sign into the system (no spaces).</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" placeholder="Set user password" required>
                            <small class="text-muted">Password chosen and provided to the user by Super Admin.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" placeholder="e.g. Rev. Emmanuel Darko" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" placeholder="user@racic.org" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Contact Phone Number</label>
                            <input type="text" name="phone" class="form-control" placeholder="+233 24 000 0000">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Assigned System Role <span class="text-danger">*</span></label>
                            <select name="role_id" class="form-select" required>
                                <option value="">Select Role...</option>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r['id'] ?>"><?= e($r['display_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Assigned Department (Access to Department Portal)</label>
                            <select name="department_id" class="form-select">
                                <option value="">None / General Church Administrator</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= e($d['name']) ?> (<?= e($d['code']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Allows department leaders and workers direct access to manage their ministry portal.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Account Status</label>
                            <select name="status" class="form-select">
                                <option value="active" selected>Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 shadow-sm">Save & Issue Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Change Password -->
<div class="modal fade" id="resetPwdModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <?= csrfField(); ?>
                <input type="hidden" name="action_reset_pwd" value="1">
                <input type="hidden" name="target_user_id" id="resetTargetId" value="">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fas fa-key text-warning me-2"></i>Change User Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="small text-muted mb-3">Updating credentials for: <strong id="resetTargetName" class="text-dark">User</strong> (<span id="resetTargetUsername" class="font-monospace text-primary"></span>)</p>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">New Password <span class="text-danger">*</span></label>
                        <input type="text" name="new_password" class="form-control" placeholder="Enter new password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Save New Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openResetModal(userId, userName, username) {
        document.getElementById('resetTargetId').value = userId;
        document.getElementById('resetTargetName').textContent = userName;
        document.getElementById('resetTargetUsername').textContent = username;
        const resetModal = new bootstrap.Modal(document.getElementById('resetPwdModal'));
        resetModal.show();
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
