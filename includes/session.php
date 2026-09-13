<?php
// Configure secure session parameters before starting the session
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', 1);

session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /auth/login.php");
        exit();
    }
}

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        http_response_code(403);
        die("CSRF Token Verification Failed.");
    }
}

// Database Rate Limiting
function checkRateLimit($action, $limit = 5, $window = 300) {
    require_once __DIR__ . '/../config/database.php';
    $pdo = getDB();
    $ip = $_SERVER['REMOTE_ADDR'];

    // Check existing
    $stmt = $pdo->prepare("SELECT attempts, start_time FROM rate_limits WHERE action = ? AND ip_address = ?");
    $stmt->execute([$action, $ip]);
    $record = $stmt->fetch();

    if (!$record) {
        $stmt = $pdo->prepare("INSERT INTO rate_limits (action, ip_address, attempts) VALUES (?, ?, 1)");
        $stmt->execute([$action, $ip]);
        return true;
    }

    $elapsed = time() - strtotime($record['start_time']);

    if ($elapsed > $window) {
        // Reset
        $stmt = $pdo->prepare("UPDATE rate_limits SET attempts = 1, start_time = CURRENT_TIMESTAMP WHERE action = ? AND ip_address = ?");
        $stmt->execute([$action, $ip]);
        return true;
    }

    if ((int)$record['attempts'] >= $limit) {
        return false;
    }

    // Increment
    $stmt = $pdo->prepare("UPDATE rate_limits SET attempts = attempts + 1 WHERE action = ? AND ip_address = ?");
    $stmt->execute([$action, $ip]);

    return true;
}