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
    if ($action === 'view') {
        requirePermission('post_sale.manage');
        $booking_id = $_GET['booking_id'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM post_sale_handovers WHERE booking_id = ? AND tenant_id = ?");
        $stmt->execute([$booking_id, $tenant_id]);
        $handover = $stmt->fetch();

        echo json_encode(['status' => 'success', 'data' => $handover]);
    }
} elseif ($method === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    if ($action === 'update') {
        requirePermission('post_sale.manage');

        $booking_id = $_POST['booking_id'] ?? '';
        $expected = !empty($_POST['expected_possession_date']) ? $_POST['expected_possession_date'] : null;
        $actual = !empty($_POST['actual_possession_date']) ? $_POST['actual_possession_date'] : null;
        $handover_date = !empty($_POST['handover_date']) ? $_POST['handover_date'] : null;
        $status = $_POST['handover_status'] ?? 'Pending';
        $keys = isset($_POST['keys_delivered']) ? 1 : 0;
        $docs = isset($_POST['documents_delivered']) ? 1 : 0;
        $snagging = trim($_POST['snagging_list'] ?? '');
        $confirmed = isset($_POST['customer_confirmation']) ? 1 : 0;
        $notes = trim($_POST['notes'] ?? '');

        if (!$booking_id) {
            http_response_code(400);
            die(json_encode(['status' => 'error', 'message' => 'Booking ID is required.']));
        }

        // Verify Booking Tenant Isolation
        $stmtB = $pdo->prepare("SELECT id FROM bookings WHERE id = ? AND tenant_id = ?");
        $stmtB->execute([$booking_id, $tenant_id]);
        if (!$stmtB->fetch()) {
             http_response_code(404);
             die(json_encode(['status' => 'error', 'message' => 'Booking not found.']));
        }

        try {
            $pdo->beginTransaction();

            $stmtH = $pdo->prepare("SELECT id FROM post_sale_handovers WHERE booking_id = ? AND tenant_id = ? FOR UPDATE");
            $stmtH->execute([$booking_id, $tenant_id]);
            $existing = $stmtH->fetch();

            if ($existing) {
                $stmtU = $pdo->prepare("
                    UPDATE post_sale_handovers SET
                    expected_possession_date = ?, actual_possession_date = ?, handover_date = ?, handover_status = ?,
                    keys_delivered = ?, documents_delivered = ?, snagging_list = ?, customer_confirmation = ?, notes = ?, updated_by = ?
                    WHERE booking_id = ?
                ");
                $stmtU->execute([$expected, $actual, $handover_date, $status, $keys, $docs, $snagging, $confirmed, $notes, $user_id, $booking_id]);
            } else {
                $stmtI = $pdo->prepare("
                    INSERT INTO post_sale_handovers
                    (tenant_id, booking_id, expected_possession_date, actual_possession_date, handover_date, handover_status,
                     keys_delivered, documents_delivered, snagging_list, customer_confirmation, notes, updated_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtI->execute([$tenant_id, $booking_id, $expected, $actual, $handover_date, $status, $keys, $docs, $snagging, $confirmed, $notes, $user_id]);
            }

            // Audit Log
            $stmtAudit = $pdo->prepare("INSERT INTO audit_logs (tenant_id, user_id, action, entity, entity_id, ip_address) VALUES (?, ?, 'update', 'post_sale_handovers', ?, ?)");
            $stmtAudit->execute([$tenant_id, $user_id, $booking_id, $_SERVER['REMOTE_ADDR'] ?? '']);

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Post-sale handover updated.']);

        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Database error.']);
        }
    }
}
