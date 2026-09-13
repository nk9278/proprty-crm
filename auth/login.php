<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';

if (isLoggedIn()) {
    header("Location: /dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    verifyCsrfToken($csrf_token);

    if (!checkRateLimit('login', 5, 300)) { // 5 attempts per 5 minutes
        $error = 'Too many login attempts. Please try again later.';
    } else {
        $mobile = $_POST['mobile'] ?? '';
        $pin = $_POST['pin'] ?? '';

        if (empty($mobile) || empty($pin)) {
            $error = 'Please enter mobile number and PIN.';
        } else {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT id, tenant_id, name, pin_hash, role, status FROM users WHERE mobile = ? LIMIT 1");
            $stmt->execute([$mobile]);
            $user = $stmt->fetch();

            if ($user && password_verify($pin, $user['pin_hash'])) {
                if ($user['status'] === 'Active') {
                    // Secure Session Initialization
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['tenant_id'] = $user['tenant_id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['name'] = $user['name'];

                    header("Location: /dashboard.php");
                    exit();
                } else {
                    $error = 'Your account is inactive. Please contact support.';
                }
            } else {
                $error = 'Invalid mobile number or PIN.';
            }
        }
    }
}
$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zopa CRM - Login</title>
    <style>
        body { font-family: sans-serif; background-color: #F1EDED; color: #1E1C1C; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h1 { font-size: 1.5rem; text-align: center; margin-bottom: 0.5rem; }
        p.tagline { text-align: center; font-size: 0.9rem; color: #666; margin-bottom: 2rem; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
        input[type="text"], input[type="password"] { width: 100%; padding: 0.75rem; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 0.75rem; background-color: #CF1F3C; color: white; border: none; border-radius: 4px; font-size: 1rem; cursor: pointer; }
        button:hover { background-color: #b01a33; }
        .error { color: #CF1F3C; font-size: 0.9rem; margin-bottom: 1rem; text-align: center; }
        .links { margin-top: 1rem; text-align: center; font-size: 0.9rem; }
        .links a { color: #CF1F3C; text-decoration: none; }
        .links a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="login-card">
    <h1>Zopa CRM</h1>
    <p class="tagline">One CRM for Property Sales, Leads & Growth</p>

    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <div class="form-group">
            <label for="mobile">Mobile Number</label>
            <input type="text" id="mobile" name="mobile" required pattern="[0-9]{10,15}" title="Please enter a valid mobile number">
        </div>
        <div class="form-group">
            <label for="pin">6-Digit PIN</label>
            <input type="password" id="pin" name="pin" required pattern="[0-9]{6}" title="Please enter your 6-digit PIN">
        </div>
        <button type="submit">Login</button>
    </form>

    <div class="links">
        <a href="/auth/register.php">Register New Account</a> | <a href="/auth/forgot_pin.php">Forgot PIN?</a> | <a href="/auth/otp.php">Login with OTP</a>
    </div>
</div>

</body>
</html>