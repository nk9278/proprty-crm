<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    die();
}

$tenant_id = current_tenant_id();
$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$pdo = getDB();

if ($method === 'GET') {
    requirePermission('marketing.view');

    if ($action === 'campaigns') {
        $stmt = $pdo->prepare("SELECT c.*, a.account_name, p.name as platform_name
            FROM campaigns c
            LEFT JOIN ad_accounts a ON c.ad_account_id = a.id
            LEFT JOIN ad_platforms p ON a.platform_id = p.id
            WHERE c.tenant_id = ? ORDER BY c.created_at DESC");
        $stmt->execute([$tenant_id]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);

    } elseif ($action === 'accounts') {
        $stmt = $pdo->prepare("SELECT a.*, p.name as platform_name FROM ad_accounts a LEFT JOIN ad_platforms p ON a.platform_id = p.id WHERE a.tenant_id = ?");
        $stmt->execute([$tenant_id]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
    }
} elseif ($method === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    requirePermission('marketing.manage');

    if ($action === 'create_campaign') {
        $account_id = $_POST['ad_account_id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $budget = (float)($_POST['budget'] ?? 0);

        if (!$account_id || !$name) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Account and Campaign Name are required.']);
            die();
        }

        // Verify Account belongs to Tenant
        $stmtA = $pdo->prepare("SELECT id FROM ad_accounts WHERE id = ? AND tenant_id = ?");
        $stmtA->execute([$account_id, $tenant_id]);
        if (!$stmtA->fetch()) {
             http_response_code(404);
             echo json_encode(['status' => 'error', 'message' => 'Ad account not found.']);
             die();
        }

        $stmt = $pdo->prepare("INSERT INTO campaigns (tenant_id, ad_account_id, name, budget, status) VALUES (?, ?, ?, ?, 'Draft')");
        $stmt->execute([$tenant_id, $account_id, $name, $budget]);

        // Audit
        $stmtAudit = $pdo->prepare("INSERT INTO audit_logs (tenant_id, user_id, action, entity, entity_id, ip_address) VALUES (?, ?, 'create_campaign', 'campaigns', ?, ?)");
        $stmtAudit->execute([$tenant_id, $user_id, $pdo->lastInsertId(), $_SERVER['REMOTE_ADDR'] ?? '']);

        echo json_encode(['status' => 'success', 'message' => 'Campaign created successfully.']);

    } elseif ($action === 'create_account') {
        $platform_id = $_POST['platform_id'] ?? '';
        $account_name = trim($_POST['account_name'] ?? '');

        if (!$platform_id || !$account_name) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Platform and Account Name required.']);
            die();
        }

        $stmt = $pdo->prepare("INSERT INTO ad_accounts (tenant_id, platform_id, account_name, status) VALUES (?, ?, ?, 'Unconfigured')");
        $stmt->execute([$tenant_id, $platform_id, $account_name]);

        echo json_encode(['status' => 'success', 'message' => 'Account created successfully (Unconfigured).']);
    }
}
