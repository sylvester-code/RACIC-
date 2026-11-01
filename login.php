<?php
// login.php - RACIC Church Management System Authentication Page
require_once __DIR__ . '/config/app.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $error = "Security token expired. Please try submitting again.";
    } else {
        $identity = trim($_POST['identity'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($identity) || empty($password)) {
            $error = "Please enter your username/email and password.";
        } else {
            $db = getDB();
            $stmt = $db->prepare("
                SELECT u.*, r.name as role_name, r.display_name as role_display, d.name as department_name
                FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                LEFT JOIN departments d ON d.id = u.department_id
                WHERE u.username = ? OR u.email = ?
                LIMIT 1
            ");
            $stmt->execute([$identity, $identity]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] !== 'active') {
                    $error = "Your account is currently " . htmlspecialchars($user['status']) . ". Please contact the Super Administrator.";
                } else {
                    loginUser($user);
                    $redirect = $_SESSION['redirect_url'] ?? BASE_URL . 'dashboard.php';
                    unset($_SESSION['redirect_url']);
                    flash('success', "Welcome back, {$user['full_name']}!");
                    header("Location: {$redirect}");
                    exit;
                }
            } else {
                $error = "Invalid username or password combination.";
                logAudit('FAILED_LOGIN', 'Authentication', null, "Failed login attempt for identity: {$identity}");
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | <?= CHURCH_SHORT_NAME ?> - Royal Ambassadors of Christ Int. Church</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #07172b 0%, #0f2c59 60%, #1e3a66 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            padding: 1.5rem;
        }
        .login-card {
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
            max-width: 480px;
            width: 100%;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .login-header {
            background: #0f2c59;
            color: #ffffff;
            padding: 2.25rem 2rem 1.75rem;
            text-align: center;
            position: relative;
        }
        .login-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #c59b27, #f7d070, #c59b27);
        }
        .church-icon-circle {
            width: 72px;
            height: 72px;
            background: #c59b27;
            color: #07172b;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 15px rgba(197, 155, 39, 0.4);
        }
        .btn-racic-primary {
            background-color: #0f2c59;
            border-color: #0f2c59;
            color: #ffffff;
            font-weight: 600;
            padding: 0.75rem 1.25rem;
            border-radius: 10px;
            transition: all 0.2s;
        }
        .btn-racic-primary:hover {
            background-color: #091a34;
            border-color: #091a34;
            color: #ffffff;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <div class="church-icon-circle">
            <i class="fas fa-church"></i>
        </div>
        <h4 class="fw-bold mb-1 tracking-wide"><?= CHURCH_SHORT_NAME ?></h4>
        <div class="text-white-50 small mb-2 text-uppercase fw-semibold" style="letter-spacing: 1px;">Royal Ambassadors of Christ Int. Church</div>
        <small class="badge bg-warning text-dark px-3 py-1 rounded-pill"><i class="fas fa-cross me-1"></i> Church Management Portal</small>
    </div>

    <div class="p-4 p-sm-5">
        <?= displayFlash(); ?>

        <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center mb-4 py-2" role="alert">
                <i class="fas fa-exclamation-circle me-2 fs-5"></i>
                <div class="small"><?= e($error) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= BASE_URL ?>login.php" id="loginForm">
            <?= csrfField(); ?>

            <div class="mb-3">
                <label for="identity" class="form-label small fw-bold text-dark">Username or Email Address</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="fas fa-user-circle"></i></span>
                    <input type="text" class="form-control" id="identity" name="identity" value="<?= e($_POST['identity'] ?? 'RACIC') ?>" required placeholder="Enter Username (e.g. RACIC) or Email" autocomplete="username">
                </div>
            </div>

            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label for="password" class="form-label small fw-bold text-dark mb-0">Password</label>
                </div>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" value="AMBASSADORS" required placeholder="••••••••" autocomplete="current-password">
                    <button class="btn btn-outline-secondary" type="button" id="togglePasswordBtn">
                        <i class="far fa-eye" id="togglePasswordIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-racic-primary w-100 mb-4 shadow-sm">
                <i class="fas fa-sign-in-alt me-2"></i>Sign In to Dashboard
            </button>
        </form>

        <!-- Access Instruction Notice -->
        <div class="border-top pt-3 text-center">
            <div class="alert alert-light border small text-muted mb-0 p-2">
                <div class="fw-bold text-dark mb-1"><i class="fas fa-shield-alt text-warning me-1"></i> Super Admin Initial Login</div>
                <div>Username: <strong class="text-primary font-monospace">RACIC</strong> &bull; Password: <strong class="text-primary font-monospace">AMBASSADORS</strong></div>
                <div class="text-secondary mt-1" style="font-size: 0.72rem;">All department portals and administrative accounts are created by the Super Admin in User Management.</div>
            </div>
        </div>
    </div>
</div>

<script>
    const toggleBtn = document.getElementById('togglePasswordBtn');
    const pwdInput = document.getElementById('password');
    const pwdIcon = document.getElementById('togglePasswordIcon');

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            if (pwdInput.type === 'password') {
                pwdInput.type = 'text';
                pwdIcon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                pwdInput.type = 'password';
                pwdIcon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });
    }
</script>

</body>
</html>
