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

// Check if mobile number is passed from index.php routing
$mobile = $_SESSION['routing_mobile'] ?? '';

// We will use 3 steps for registration:
// 1. Mobile (pre-filled or entered) -> Sends OTP
// 2. OTP Verification
// 3. User Details & PIN Creation
$step = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    verifyCsrfToken($csrf_token);

    if (!checkRateLimit('register', 5, 300)) {
        $error = 'Too many attempts. Please try again later.';
    } else {
        $step = (int)($_POST['step'] ?? 1);
        $mobile = $_POST['mobile'] ?? $mobile; // Get from POST or session

        if ($step === 1) {
            if (empty($mobile) || !preg_match('/^[0-9]{10,15}$/', $mobile)) {
                $error = 'Valid mobile number is required.';
            } else {
                $pdo = getDB();
                // Check if already registered
                $stmt = $pdo->prepare("SELECT id FROM users WHERE mobile = ? LIMIT 1");
                $stmt->execute([$mobile]);
                if ($stmt->fetch()) {
                    $error = 'Mobile number already registered. Please login.';
                } else {
                    $otp = generateAndStoreOTP($mobile, 'register');
                    $_SESSION['reg_mobile'] = $mobile;
                    // For dev purposes only - we can echo it or let it fail gracefully if no SMS is set up.
                    $success = "OTP Sent to your mobile.";
                    // We can log the OTP for testing purposes
                    error_log("OTP for register $mobile: $otp");
                    $step = 2;
                }
            }
        } elseif ($step === 2) {
            $otp_input = $_POST['otp'] ?? '';
            $result = verifyOTP($_SESSION['reg_mobile'], $otp_input, 'register');

            if ($result['status'] === true) {
                $step = 3;
                $success = 'OTP Verified. Please complete your registration.';
            } else {
                $error = $result['message'];
                $step = 2; // Stay on step 2
            }
        } elseif ($step === 3) {
            $name = $_POST['name'] ?? '';
            $email = $_POST['email'] ?? '';
            $company = $_POST['company'] ?? '';
            $business_type = $_POST['business_type'] ?? '';
            $pin = $_POST['pin'] ?? '';
            $confirm_pin = $_POST['confirm_pin'] ?? '';
            $reg_mobile = $_SESSION['reg_mobile'] ?? '';

            if (empty($reg_mobile) || empty($name) || empty($company) || empty($pin) || empty($confirm_pin)) {
                $error = 'All required fields must be filled.';
                $step = 3;
            } elseif ($pin !== $confirm_pin) {
                $error = 'PINs do not match.';
                $step = 3;
            } elseif (!preg_match('/^[0-9]{6}$/', $pin)) {
                $error = 'PIN must be exactly 6 digits.';
                $step = 3;
            } else {
                $pdo = getDB();

                try {
                    $pdo->beginTransaction();

                    // Create Tenant
                    $stmt = $pdo->prepare("INSERT INTO tenants (name, status) VALUES (?, 'Trial')");
                    $stmt->execute([$company]);
                    $tenant_id = $pdo->lastInsertId();

                    // Get Role ID for Tenant Owner
                    $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'Tenant Owner' LIMIT 1");
                    $stmt->execute();
                    $role_id = $stmt->fetchColumn();

                    if (!$role_id) {
                        throw new \Exception("Role 'Tenant Owner' not found in database.");
                    }

                    // Create User (Tenant Owner)
                    $pin_hash = password_hash($pin, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (tenant_id, name, mobile, email, pin_hash, role_id, status) VALUES (?, ?, ?, ?, ?, ?, 'Active')");
                    $stmt->execute([$tenant_id, $name, $reg_mobile, $email, $pin_hash, $role_id]);
                    $user_id = $pdo->lastInsertId();

                    // Assign to user_roles
                    $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                    $stmt->execute([$user_id, $role_id]);

                    $pdo->commit();

                    // Cleanup Session variables
                    unset($_SESSION['reg_mobile']);
                    unset($_SESSION['routing_mobile']);

                    // Auto Login
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['tenant_id'] = $tenant_id;
                    $_SESSION['role_id'] = $role_id;
                    $_SESSION['role_name'] = 'Tenant Owner';
                    $_SESSION['name'] = $name;

                    header("Location: /dashboard.php");
                    exit();
                } catch (\Exception $e) {
                    $pdo->rollBack();
                    error_log("Registration Error: " . $e->getMessage());
                    $error = 'An error occurred during registration. Please try again.';
                    $step = 3;
                }
            }
        }
    }
} else {
    // GET request logic
    if (!empty($mobile)) {
        // If mobile is set from index routing, automatically trigger step 1 logic behind the scenes
        // to send OTP if we wanted to. For simplicity and user confirmation, we present it in form step 1.
    }
}
$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zopa CRM - Register</title>
    <style>
        body { font-family: sans-serif; background-color: #F1EDED; color: #1E1C1C; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h1 { font-size: 1.5rem; text-align: center; margin-bottom: 0.5rem; }
        p.tagline { text-align: center; font-size: 0.9rem; color: #666; margin-bottom: 2rem; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
        input[type="text"], input[type="password"], input[type="email"], select { width: 100%; padding: 0.75rem; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
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
    <p class="tagline">Create your workspace</p>

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
                <input type="text" id="mobile" name="mobile" required pattern="[0-9]{10,15}" title="Please enter a valid mobile number" value="<?php echo htmlspecialchars($mobile); ?>">
            </div>
            <button type="submit">Send OTP</button>
        <?php elseif ($step === 2): ?>
            <div class="form-group">
                <label for="otp">Enter OTP</label>
                <input type="text" id="otp" name="otp" required>
            </div>
            <button type="submit">Verify OTP</button>
        <?php elseif ($step === 3): ?>
            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="company">Company Name</label>
                <input type="text" id="company" name="company" required value="<?php echo htmlspecialchars($_POST['company'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="business_type">Business Type</label>
                <select id="business_type" name="business_type">
                    <option value="Builder">Property Builder</option>
                    <option value="Broker">Real Estate Broker</option>
                    <option value="Agency">Real Estate Agency</option>
                    <option value="Consultant">Property Consultant</option>
                </select>
            </div>
            <div class="form-group">
                <label for="pin">Create 6-Digit PIN</label>
                <input type="password" id="pin" name="pin" required pattern="[0-9]{6}" title="Please enter a 6-digit PIN">
            </div>
            <div class="form-group">
                <label for="confirm_pin">Confirm PIN</label>
                <input type="password" id="confirm_pin" name="confirm_pin" required pattern="[0-9]{6}">
            </div>
            <button type="submit">Complete Registration</button>
        <?php endif; ?>
    </form>

    <div class="links">
        <a href="/auth/login.php">Already have an account? Login</a>
    </div>
</div>

</body>
</html>