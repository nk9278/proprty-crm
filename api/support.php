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
    requirePermission('support.view');
    if ($action === 'list') {
        $stmt = $pdo->prepare("SELECT s.*, c.name as customer_name, u.name as assigned_name
            FROM support_tickets s
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN users u ON s.assigned_user_id = u.id
            WHERE s.tenant_id = ? ORDER BY s.created_at DESC");
        $stmt->execute([$tenant_id]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
    }
} elseif ($method === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    if ($action === 'create') {
        requirePermission('support.manage');

        $customer_id = !empty($_POST['customer_id']) ? $_POST['customer_id'] : null;
        $category = trim($_POST['category'] ?? 'General');
        $priority = $_POST['priority'] ?? 'Medium';
        $subject = trim($_POST['subject'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (!$subject || !$description) {
            http_response_code(400);
            die(json_encode(['status' => 'error', 'message' => 'Subject and Description are required.']));
        }

        // IDOR Verification
        if ($customer_id) {
            $stmtC = $pdo->prepare("SELECT id FROM customers WHERE id = ? AND tenant_id = ?");
            $stmtC->execute([$customer_id, $tenant_id]);
            if (!$stmtC->fetch()) {
                 http_response_code(404);
                 die(json_encode(['status' => 'error', 'message' => 'Customer not found.']));
            }
        }

        $stmt = $pdo->prepare("INSERT INTO support_tickets (tenant_id, customer_id, category, priority, subject, description, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$tenant_id, $customer_id, $category, $priority, $subject, $description, $user_id]);

        echo json_encode(['status' => 'success', 'message' => 'Ticket created successfully.']);
    } elseif ($action === 'update_status') {
        requirePermission('support.manage');
        $ticket_id = $_POST['ticket_id'] ?? '';
        $status = $_POST['status'] ?? '';

        if (!$ticket_id || !$status) {
            http_response_code(400);
            die(json_encode(['status' => 'error', 'message' => 'Missing fields.']));
        }

        $stmt = $pdo->prepare("UPDATE support_tickets SET status = ? WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$status, $ticket_id, $tenant_id]);
        echo json_encode(['status' => 'success', 'message' => 'Ticket status updated.']);
    }
}
