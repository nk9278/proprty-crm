<?php
require_once __DIR__ . '/../config/database.php';

// Action can be 'register', 'login', or 'forgot_pin'
function generateAndStoreOTP($mobile, $action) {
    $pdo = getDB();

    // Secure random OTP for demonstration
    $otp_code = (string)random_int(100000, 999999);

    // Expire in 10 minutes
    $expires_at = date('Y-m-d H:i:s', time() + 600);

    // Delete any existing OTP for this mobile/action combination to prevent clutter
    $stmt = $pdo->prepare("DELETE FROM otps WHERE mobile = ? AND action = ?");
    $stmt->execute([$mobile, $action]);

    $stmt = $pdo->prepare("INSERT INTO otps (mobile, otp_code, action, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->execute([$mobile, $otp_code, $action, $expires_at]);

    // In a real system, you would trigger SMS API here.
    return $otp_code;
}

function verifyOTP($mobile, $otp_code, $action) {
    $pdo = getDB();

    $stmt = $pdo->prepare("SELECT id, otp_code, attempts, expires_at FROM otps WHERE mobile = ? AND action = ? LIMIT 1");
    $stmt->execute([$mobile, $action]);
    $record = $stmt->fetch();

    if (!$record) {
        return ['status' => false, 'message' => 'No OTP found or it has expired.'];
    }

    // Check expiration
    if (strtotime($record['expires_at']) < time()) {
        $stmt = $pdo->prepare("DELETE FROM otps WHERE id = ?");
        $stmt->execute([$record['id']]);
        return ['status' => false, 'message' => 'OTP has expired.'];
    }

    // Check max attempts (e.g., 3 attempts)
    if ((int)$record['attempts'] >= 3) {
        $stmt = $pdo->prepare("DELETE FROM otps WHERE id = ?");
        $stmt->execute([$record['id']]);
        return ['status' => false, 'message' => 'Too many failed attempts. Please request a new OTP.'];
    }

    if ($record['otp_code'] === $otp_code) {
        // Success - clean up OTP
        $stmt = $pdo->prepare("DELETE FROM otps WHERE id = ?");
        $stmt->execute([$record['id']]);
        return ['status' => true, 'message' => 'OTP verified successfully.'];
    } else {
        // Failed attempt, increment counter
        $stmt = $pdo->prepare("UPDATE otps SET attempts = attempts + 1 WHERE id = ?");
        $stmt->execute([$record['id']]);
        return ['status' => false, 'message' => 'Invalid OTP.'];
    }
}