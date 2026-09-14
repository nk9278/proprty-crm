<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn()) {
    http_response_code(401);
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

$tenant_id = current_tenant_id();
$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$pdo = getDB();

if ($method === 'GET') {
    if ($action === 'list') {
        requirePermission('api_keys.manage');
        $stmt = $pdo->prepare("SELECT id, name, api_key, status, last_used_at, expires_at, created_at FROM api_keys WHERE tenant_id = ? ORDER BY created_at DESC");
        $stmt->execute([$tenant_id]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
    }
} elseif ($method === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    if ($action === 'create') {
        requirePermission('api_keys.manage');

        $name = trim($_POST['name'] ?? '');
        if (!$name) {
            http_response_code(400);
            die(json_encode(['status' => 'error', 'message' => 'Key Name is required.']));
        }

        $key = 'zk_' . bin2hex(random_bytes(16));
        $secret = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 year')); // Configurable naturally later

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO api_keys (tenant_id, user_id, name, api_key, api_secret, expires_at) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$tenant_id, $user_id, $name, $key, password_hash($secret, PASSWORD_DEFAULT), $expires]);
            $key_id = $pdo->lastInsertId();

            // Generate Scopes statically for Phase 21 constraints (e.g. read/write mapping safely natively)
            // In a fuller implementation, scopes would be selected via checkboxes on UI
            $stmtScope = $pdo->prepare("INSERT INTO api_key_scopes (api_key_id, scope) VALUES (?, ?), (?, ?)");
            $stmtScope->execute([$key_id, 'leads:read', $key_id, 'leads:create']);

            // Audit
            $stmtAudit = $pdo->prepare("INSERT INTO audit_logs (tenant_id, user_id, action, entity, entity_id, ip_address) VALUES (?, ?, 'create_api_key', 'api_keys', ?, ?)");
            $stmtAudit->execute([$tenant_id, $user_id, $key_id, $_SERVER['REMOTE_ADDR'] ?? '']);

            $pdo->commit();
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500);
            die(json_encode(['status' => 'error', 'message' => 'Failed to allocate API scopes cleanly.']));
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'API Key created. Please save the secret now, it will not be shown again.',
            'data' => [
                'api_key' => $key,
                'api_secret' => $secret
            ]
        ]);

    } elseif ($action === 'revoke') {
        requirePermission('api_keys.manage');
        $id = $_POST['id'] ?? '';

        $stmt = $pdo->prepare("UPDATE api_keys SET status = 'Revoked' WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenant_id]);

        echo json_encode(['status' => 'success', 'message' => 'API Key revoked successfully.']);
    }
}
