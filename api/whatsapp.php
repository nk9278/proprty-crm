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
    if ($action === 'templates') {
        requirePermission('whatsapp.view');
        $stmt = $pdo->prepare("SELECT * FROM message_templates WHERE tenant_id = ? AND status = 'Active'");
        $stmt->execute([$tenant_id]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);

    } elseif ($action === 'history') {
        requirePermission('whatsapp.view');
        $lead_id = $_GET['lead_id'] ?? null;
        $customer_id = $_GET['customer_id'] ?? null;

        $sql = "SELECT m.*, u.name as sender_name
                FROM whatsapp_messages m
                LEFT JOIN users u ON m.sender_id = u.id
                WHERE m.tenant_id = ?";
        $params = [$tenant_id];

        if ($lead_id) {
            $sql .= " AND m.lead_id = ?";
            $params[] = $lead_id;
        } elseif ($customer_id) {
            $sql .= " AND m.customer_id = ?";
            $params[] = $customer_id;
        }

        $sql .= " ORDER BY m.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
    }
} elseif ($method === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    if ($action === 'send') {
        requirePermission('whatsapp.send');

        $lead_id = !empty($_POST['lead_id']) ? $_POST['lead_id'] : null;
        $customer_id = !empty($_POST['customer_id']) ? $_POST['customer_id'] : null;
        $message_type = $_POST['message_type'] ?? 'Text';
        $content = trim($_POST['content'] ?? '');

        if ((!$lead_id && !$customer_id) || empty($content)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Valid Recipient and Content are required.']);
            die();
        }

        // Find suitable configured active provider.
        $stmtA = $pdo->prepare("SELECT id FROM whatsapp_accounts WHERE tenant_id = ? AND status = 'Active' LIMIT 1");
        $stmtA->execute([$tenant_id]);
        $account = $stmtA->fetch();

        if (!$account) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'No active WhatsApp Provider is configured for this workspace.']);
            die();
        }

        // Consent Verification check (mocking logic but strict evaluation in db)
        $mobile = '';
        if ($lead_id) {
            $stmtL = $pdo->prepare("SELECT mobile FROM leads WHERE id = ? AND tenant_id = ?");
            $stmtL->execute([$lead_id, $tenant_id]);
            $mobile = $stmtL->fetchColumn();
        } else {
            $stmtC = $pdo->prepare("SELECT mobile FROM customers WHERE id = ? AND tenant_id = ?");
            $stmtC->execute([$customer_id, $tenant_id]);
            $mobile = $stmtC->fetchColumn();
        }

        if (!$mobile) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Recipient mobile not found or access denied.']);
            die();
        }

        $stmtCons = $pdo->prepare("SELECT has_consent FROM communication_consents WHERE tenant_id = ? AND mobile = ? ORDER BY id DESC LIMIT 1");
        $stmtCons->execute([$tenant_id, $mobile]);
        $consent = $stmtCons->fetch();

        if ($consent && $consent['has_consent'] == 0) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Customer has opted out of WhatsApp communications.']);
            die();
        }

        // Log the message attempt
        $stmtIns = $pdo->prepare("
            INSERT INTO whatsapp_messages
            (tenant_id, whatsapp_account_id, lead_id, customer_id, sender_id, direction, message_type, content, delivery_status)
            VALUES (?, ?, ?, ?, ?, 'Outbound', ?, ?, 'Failed')
        ");
        $stmtIns->execute([$tenant_id, $account['id'], $lead_id, $customer_id, $user_id, $message_type, $content]);
        $msg_id = $pdo->lastInsertId();

        // Here we simulate the provider failure natively since we don't have actual credentials
        // External Provider logic would happen here, followed by an UPDATE to the delivery_status.

        echo json_encode([
            'status' => 'error',
            'message' => 'Message logged, but WhatsApp Provider configuration is currently mocked. Delivery failed.'
        ]);
    }
}
