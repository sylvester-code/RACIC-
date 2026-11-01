<?php
// includes/auth.php - Authentication and Authorization System

/**
 * Check if a user is currently authenticated
 */
function isLoggedIn(): bool {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        return false;
    }

    // Check session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        return false;
    }

    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Get the currently logged-in user details
 */
function currentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    return $_SESSION['user'] ?? null;
}

/**
 * Require login to access a page
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        $requestedUri = $_SERVER['REQUEST_URI'] ?? '';
        $_SESSION['redirect_url'] = $requestedUri;
        flash('warning', 'Please sign in to access this page.');
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

/**
 * Check if the user has one of the specified roles
 * Role names: super_admin, head_pastor, church_admin, dept_leader, finance_officer, church_worker
 */
function hasRole(string|array $roles): bool {
    if (!isLoggedIn()) {
        return false;
    }

    $user = currentUser();
    $userRole = $user['role_name'] ?? '';

    // Super Admin has universal access
    if ($userRole === 'super_admin') {
        return true;
    }

    if (is_string($roles)) {
        $roles = [$roles];
    }

    return in_array($userRole, $roles, true);
}

/**
 * Restrict access to authorized roles only
 */
function requireRole(string|array $roles): void {
    requireLogin();
    if (!hasRole($roles)) {
        flash('danger', 'Access Denied: You do not have permission to view or manage this resource.');
        header('Location: ' . BASE_URL . 'dashboard.php');
        exit;
    }
}

/**
 * Check if user has specific permission code
 */
function hasPermission(string $permissionCode): bool {
    if (!isLoggedIn()) {
        return false;
    }
    $user = currentUser();
    if (($user['role_name'] ?? '') === 'super_admin') {
        return true;
    }
    $permissions = $_SESSION['permissions'] ?? [];
    return in_array($permissionCode, $permissions, true);
}

/**
 * Require specific permission
 */
function requirePermission(string $permissionCode): void {
    requireLogin();
    if (!hasPermission($permissionCode)) {
        flash('danger', 'Access Denied: Required permission missing (' . e($permissionCode) . ').');
        header('Location: ' . BASE_URL . 'dashboard.php');
        exit;
    }
}

/**
 * Generate and return CSRF token
 */
function generateCSRF(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output HTML hidden input with CSRF token
 */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRF()) . '">';
}

/**
 * Validate submitted CSRF token
 */
function validateCSRF(): bool {
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!$token || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Log action into audit_logs table
 */
function logAudit(string $action, string $module, ?int $recordId = null, ?string $details = null): void {
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);

        $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, module, record_id, details, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $action, $module, $recordId, $details, $ip, $ua]);
    } catch (Exception $e) {
        // Silent fail so audit logging never crashes an action
        error_log("Audit log error: " . $e->getMessage());
    }
}

/**
 * Log in a user and set up session
 */
function loginUser(array $user): void {
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user'] = [
        'id' => $user['id'],
        'role_id' => $user['role_id'],
        'username' => $user['username'] ?? '',
        'role_name' => $user['role_name'],
        'role_display' => $user['role_display'],
        'department_id' => $user['department_id'],
        'department_name' => $user['department_name'] ?? null,
        'full_name' => $user['full_name'],
        'email' => $user['email'],
        'phone' => $user['phone'],
        'avatar' => $user['avatar']
    ];
    $_SESSION['last_activity'] = time();

    // Load role permissions
    $db = getDB();
    $stmt = $db->prepare("
        SELECT p.code 
        FROM permissions p
        INNER JOIN role_permissions rp ON rp.permission_id = p.id
        WHERE rp.role_id = ?
    ");
    $stmt->execute([$user['role_id']]);
    $_SESSION['permissions'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Update last login
    $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $updateStmt->execute([$user['id']]);

    logAudit('LOGIN', 'Authentication', $user['id'], "User {$user['email']} logged in successfully.");
}

/**
 * Log out user
 */
function logoutUser(): void {
    if (isset($_SESSION['user_id'])) {
        logAudit('LOGOUT', 'Authentication', $_SESSION['user_id'], "User logged out.");
    }
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

