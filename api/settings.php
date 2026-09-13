<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$tenant_id = current_tenant_id();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Tenant settings typically restricted to Admin or Tenant Owner
requirePermission('Tenant Owner');

$pdo = getDB();

if ($method === 'GET' && $action === 'get_all') {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM tenant_settings WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $rows = $stmt->fetchAll();

    $settings = [];
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    echo json_encode(['status' => 'success', 'data' => $settings]);
} elseif ($method === 'POST' && $action === 'save') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    $key = trim($_POST['setting_key'] ?? '');
    $value = trim($_POST['setting_value'] ?? '');

    if (empty($key)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Setting key is required.']);
        exit();
    }

    // Insert or update setting
    $stmt = $pdo->prepare("
        INSERT INTO tenant_settings (tenant_id, setting_key, setting_value)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");

    if ($stmt->execute([$tenant_id, $key, $value])) {
        echo json_encode(['status' => 'success', 'message' => 'Setting saved successfully.']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error while saving setting.']);
    }
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
}
