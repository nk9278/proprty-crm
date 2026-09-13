<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/otp.php';

if (isLoggedIn()) {
    header("Location: /dashboard.php");
    exit();
}

$error = '';
$success = '';
$step = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    verifyCsrfToken($csrf_token);

    if (!checkRateLimit('otp_login', 5, 300)) {
        $error = 'Too many attempts. Please try again later.';
    } else {
        $step = (int)($_POST['step'] ?? 1);

        if ($step === 1) {
            $mobile = $_POST['mobile'] ?? '';
            if (empty($mobile) || !preg_match('/^[0-9]{10,15}$/', $mobile)) {
                $error = 'Valid mobile number is required.';
            } else {
                $pdo = getDB();
                $stmt = $pdo->prepare("SELECT id FROM users WHERE mobile = ? LIMIT 1");
                $stmt->execute([$mobile]);
                $user = $stmt->fetch();
                if ($user) {
                    $otp = generateAndStoreOTP($mobile, 'login');
                    $_SESSION['login_mobile'] = $mobile;
                    $step = 2;
                    $success = "OTP Sent to your mobile.";
                } else {
                    $error = 'Mobile number not registered.';
                }
            }
        } elseif ($step === 2) {
            $otp_input = $_POST['otp'] ?? '';
            $result = verifyOTP($_SESSION['login_mobile'], $otp_input, 'login');

            if ($result['status'] === true) {
                $pdo = getDB();
                $stmt = $pdo->prepare("SELECT id, tenant_id, name, role, status FROM users WHERE mobile = ? LIMIT 1");
                $stmt->execute([$_SESSION['login_mobile']]);
                $user = $stmt->fetch();

                if ($user && $user['status'] === 'Active') {
                    // Secure Session Initialization
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['tenant_id'] = $user['tenant_id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['name'] = $user['name'];

                    unset($_SESSION['login_mobile']);

                    header("Location: /dashboard.php");
                    exit();
                } else {
                    $error = 'Your account is inactive. Please contact support.';
                    $step = 1;
                }
            } else {
                $error = $result['message'];
                $step = 2;
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
    <title>Zopa CRM - OTP Login</title>
    <style>
        body { font-family: sans-serif; background-color: #F1EDED; color: #1E1C1C; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h1 { font-size: 1.5rem; text-align: center; margin-bottom: 0.5rem; }
        p.tagline { text-align: center; font-size: 0.9rem; color: #666; margin-bottom: 2rem; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
        input[type="text"] { width: 100%; padding: 0.75rem; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 0.75rem; background-color: #CF1F3C; color: white; border: none; border-radius: 4px; font-size: 1rem; cursor: pointer; }
        button:hover { background-color: #b01a33; }
        .error { color: #CF1F3C; font-size: 0.9rem; margin-bottom: 1rem; text-align: center; }
        .success { color: green; font-size: 0.9rem; margin-bottom: 1rem; text-align: center; }
        .links { margin-top: 1rem; text-align: center; font-size: 0.9rem; }
        .links a { color: #CF1F3C; text-decoration: none; }
        .links a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="login-card">
    <h1>Zopa CRM</h1>
    <p class="tagline">Login with OTP</p>

    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <input type="hidden" name="step" value="<?php echo $step; ?>">

        <?php if ($step === 1): ?>
            <div class="form-group">
                <label for="mobile">Mobile Number</label>
                <input type="text" id="mobile" name="mobile" required pattern="[0-9]{10,15}">
            </div>
            <button type="submit">Send OTP</button>
        <?php elseif ($step === 2): ?>
            <div class="form-group">
                <label for="otp">Enter OTP</label>
                <input type="text" id="otp" name="otp" required>
            </div>
            <button type="submit">Verify & Login</button>
        <?php endif; ?>
    </form>

    <div class="links">
        <a href="/auth/login.php">Login with PIN</a>
    </div>
</div>

</body>
</html>