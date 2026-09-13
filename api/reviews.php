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
    requirePermission('reviews.view');
    if ($action === 'list') {
        $stmt = $pdo->prepare("SELECT r.*, c.name as customer_name
            FROM reviews r
            LEFT JOIN customers c ON r.customer_id = c.id
            WHERE r.tenant_id = ? ORDER BY r.created_at DESC");
        $stmt->execute([$tenant_id]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
    }
} elseif ($method === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    if ($action === 'create') {
        // Technically this might be submitted by a customer portal later, but for now staff adds it.
        requirePermission('reviews.moderate');

        $customer_id = $_POST['customer_id'] ?? '';
        $target_type = $_POST['target_type'] ?? '';
        $target_id = $_POST['target_id'] ?? '';
        $rating = (int)($_POST['rating'] ?? 0);
        $review_text = trim($_POST['review_text'] ?? '');
        $status = $_POST['status'] ?? 'Pending';

        if (!$customer_id || !$target_type || !$target_id || $rating < 1 || $rating > 5) {
            http_response_code(400);
            die(json_encode(['status' => 'error', 'message' => 'Invalid parameters. Rating must be 1-5.']));
        }

        $stmt = $pdo->prepare("INSERT INTO reviews (tenant_id, customer_id, target_type, target_id, rating, review_text, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$tenant_id, $customer_id, $target_type, $target_id, $rating, $review_text, $status]);

        echo json_encode(['status' => 'success', 'message' => 'Review recorded.']);

    } elseif ($action === 'moderate') {
        requirePermission('reviews.moderate');
        $id = $_POST['id'] ?? '';
        $status = $_POST['status'] ?? '';

        if (!in_array($status, ['Approved', 'Rejected'])) {
             http_response_code(400);
             die(json_encode(['status' => 'error', 'message' => 'Invalid status.']));
        }

        $stmt = $pdo->prepare("UPDATE reviews SET status = ? WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$status, $id, $tenant_id]);
        echo json_encode(['status' => 'success', 'message' => 'Review moderated.']);
    }
}
