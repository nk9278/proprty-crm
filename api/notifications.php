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
$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$pdo = getDB();

if ($method === 'GET') {
    if ($action === 'list_unread') {
        $stmt = $pdo->prepare("
            SELECT id, title, message, link, created_at
            FROM notifications
            WHERE tenant_id = ? AND user_id = ? AND is_read = FALSE
            ORDER BY created_at DESC
        ");
        $stmt->execute([$tenant_id, $user_id]);
        $notifications = $stmt->fetchAll();

        echo json_encode(['status' => 'success', 'data' => $notifications]);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid action for GET method']);
    }
} elseif ($method === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    if ($action === 'mark_read' && isset($_POST['id'])) {
        $notif_id = (int)$_POST['id'];

        // Scope securely to the current user to prevent IDOR
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND tenant_id = ? AND user_id = ?");
        $stmt->execute([$notif_id, $tenant_id, $user_id]);

        echo json_encode(['status' => 'success', 'message' => 'Notification marked as read.']);

    } elseif ($action === 'mark_all_read') {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE tenant_id = ? AND user_id = ? AND is_read = FALSE");
        $stmt->execute([$tenant_id, $user_id]);

        echo json_encode(['status' => 'success', 'message' => 'All notifications marked as read.']);
    } else {
         http_response_code(400);
         echo json_encode(['status' => 'error', 'message' => 'Invalid action for POST method']);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}
