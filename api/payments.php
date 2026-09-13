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
    if ($action === 'list_for_booking') {
        requirePermission('payments.view');
        $booking_id = $_GET['booking_id'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM payments WHERE booking_id = ? AND tenant_id = ? ORDER BY payment_date DESC, created_at DESC");
        $stmt->execute([$booking_id, $tenant_id]);

        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
    }
} elseif ($method === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    if ($action === 'record') {
        requirePermission('payments.create');

        $booking_id = $_POST['booking_id'] ?? '';
        $amount = (float)($_POST['amount'] ?? 0);
        $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
        $mode = $_POST['payment_mode'] ?? '';
        $reference = trim($_POST['transaction_reference'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $milestone_id = !empty($_POST['milestone_id']) ? $_POST['milestone_id'] : null;

        if (!$booking_id || $amount <= 0 || !$mode) {
             http_response_code(400);
             echo json_encode(['status' => 'error', 'message' => 'Booking ID, valid Amount, and Payment Mode are required.']);
             die();
        }

        $valid_modes = ['Cash', 'Cheque', 'Bank Transfer', 'Credit Card', 'Online', 'Other'];
        if (!in_array($mode, $valid_modes)) {
             http_response_code(400);
             echo json_encode(['status' => 'error', 'message' => 'Invalid payment mode.']);
             die();
        }

        try {
            $pdo->beginTransaction();

            // Validate Booking and get Cost Sheet context WITH LOCK to prevent race conditions on balance evaluation
            $stmtB = $pdo->prepare("
                SELECT b.id, cs.outstanding_amount, cs.amount_received, cs.final_amount, b.status
                FROM bookings b
                LEFT JOIN booking_cost_sheets cs ON b.id = cs.booking_id
                WHERE b.id = ? AND b.tenant_id = ?
                FOR UPDATE
            ");
            $stmtB->execute([$booking_id, $tenant_id]);
            $booking = $stmtB->fetch();

            if (!$booking) {
                throw new \Exception("Booking not found or access denied.");
            }
            if ($booking['status'] === 'Cancelled') {
                throw new \Exception("Cannot record payment against a cancelled booking.");
            }

            // Check limits
            if ($amount > $booking['outstanding_amount']) {
                // Not throwing an error, but logging it conceptually. Could cap it or throw.
                // We'll throw to prevent math manipulation attacks pushing outstanding balances negative.
                throw new \Exception("Payment amount ({$amount}) cannot exceed the outstanding balance ({$booking['outstanding_amount']}).");
            }

            // Generate receipt number
            $receipt_no = "RCT-" . date('Ym') . "-" . rand(1000, 9999);

            $stmtInsert = $pdo->prepare("
                INSERT INTO payments
                (tenant_id, booking_id, milestone_id, amount, payment_date, payment_mode, transaction_reference, receipt_number, status, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Completed', ?, ?)
            ");
            $stmtInsert->execute([
                $tenant_id, $booking_id, $milestone_id, $amount, $payment_date, $mode, $reference, $receipt_no, $notes, $user_id
            ]);
            $payment_id = $pdo->lastInsertId();

            // Update Cost Sheet Balances safely inside the lock block
            $new_received = $booking['amount_received'] + $amount;
            $new_outstanding = $booking['outstanding_amount'] - $amount;
            if ($new_outstanding < 0) $new_outstanding = 0;

            $stmtUpdateCS = $pdo->prepare("UPDATE booking_cost_sheets SET amount_received = ?, outstanding_amount = ? WHERE booking_id = ?");
            $stmtUpdateCS->execute([$new_received, $new_outstanding, $booking_id]);

            // Audit Log
            $stmtAudit = $pdo->prepare("INSERT INTO audit_logs (tenant_id, user_id, action, entity, entity_id, ip_address) VALUES (?, ?, 'record_payment', 'bookings', ?, ?)");
            $stmtAudit->execute([$tenant_id, $user_id, $booking_id, $_SERVER['REMOTE_ADDR'] ?? '']);

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Payment recorded successfully.', 'data' => ['receipt_number' => $receipt_no, 'payment_id' => $payment_id]]);

        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}
