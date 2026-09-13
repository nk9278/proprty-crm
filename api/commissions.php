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
        requirePermission('commissions.view');
        $booking_id = $_GET['booking_id'] ?? '';

        $stmt = $pdo->prepare("SELECT c.*, cp.company_name, u.name as salesperson_name
            FROM commissions c
            LEFT JOIN channel_partners cp ON c.channel_partner_id = cp.id
            LEFT JOIN users u ON c.salesperson_id = u.id
            WHERE c.booking_id = ? AND c.tenant_id = ? ORDER BY c.created_at DESC");
        $stmt->execute([$booking_id, $tenant_id]);

        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
    }
} elseif ($method === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    if ($action === 'calculate') {
        requirePermission('commissions.manage');

        $booking_id = $_POST['booking_id'] ?? '';
        $cp_id = !empty($_POST['channel_partner_id']) ? $_POST['channel_partner_id'] : null;
        $sp_id = !empty($_POST['salesperson_id']) ? $_POST['salesperson_id'] : null;
        $pct = (float)($_POST['percentage'] ?? 0);
        $fixed = (float)($_POST['fixed_amount'] ?? 0);

        if (!$booking_id || (!$cp_id && !$sp_id)) {
             http_response_code(400);
             echo json_encode(['status' => 'error', 'message' => 'Missing booking or assignee context.']);
             die();
        }

        if ($pct < 0 || $fixed < 0) {
             http_response_code(400);
             echo json_encode(['status' => 'error', 'message' => 'Commission rates cannot be negative.']);
             die();
        }

        try {
            $pdo->beginTransaction();

            // Validate Booking IDOR and get base value to compute against
            $stmtB = $pdo->prepare("SELECT cs.base_price, cs.final_amount FROM booking_cost_sheets cs JOIN bookings b ON cs.booking_id = b.id WHERE b.id = ? AND b.tenant_id = ?");
            $stmtB->execute([$booking_id, $tenant_id]);
            $cs = $stmtB->fetch();

            if (!$cs) throw new \Exception("Booking/Cost sheet not found.");

            // Prefer base_price as the calculation vector for logical splits logically unless customized
            $base_calc_value = $cs['base_price'];
            if ($base_calc_value <= 0) $base_calc_value = $cs['final_amount'];

            $computed_commission = 0.00;
            if ($pct > 0) {
                 $computed_commission = ($base_calc_value * $pct) / 100;
            } elseif ($fixed > 0) {
                 $computed_commission = $fixed;
            }

            $stmtInsert = $pdo->prepare("
                INSERT INTO commissions
                (tenant_id, booking_id, channel_partner_id, salesperson_id, base_amount, commission_amount, outstanding_amount, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Estimated')
            ");
            $stmtInsert->execute([$tenant_id, $booking_id, $cp_id, $sp_id, $base_calc_value, $computed_commission, $computed_commission]);

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Commission mapped successfully.']);

        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($action === 'payout') {
        requirePermission('payouts.create');

        $commission_id = $_POST['commission_id'] ?? '';
        $amount = (float)($_POST['amount'] ?? 0);
        $method_pay = $_POST['payment_method'] ?? 'Bank Transfer';
        $ref = trim($_POST['transaction_reference'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (!$commission_id || $amount <= 0) {
             http_response_code(400);
             echo json_encode(['status' => 'error', 'message' => 'Valid Commission ID and Amount > 0 required.']);
             die();
        }

        try {
            $pdo->beginTransaction();

            // Select FOR UPDATE to lock row against race condition over-payouts
            $stmt = $pdo->prepare("SELECT outstanding_amount, amount_paid, status FROM commissions WHERE id = ? AND tenant_id = ? FOR UPDATE");
            $stmt->execute([$commission_id, $tenant_id]);
            $comm = $stmt->fetch();

            if (!$comm) throw new \Exception("Commission not found.");
            if (in_array($comm['status'], ['Cancelled', 'Reversed', 'Paid'])) {
                throw new \Exception("Cannot payout a commission in {$comm['status']} state.");
            }

            if ($amount > $comm['outstanding_amount']) {
                throw new \Exception("Payout amount ($amount) cannot exceed outstanding balance ({$comm['outstanding_amount']}).");
            }

            // Record Payout
            $stmtP = $pdo->prepare("
                INSERT INTO commission_payouts
                (tenant_id, commission_id, amount, payout_date, payment_method, transaction_reference, notes, created_by)
                VALUES (?, ?, ?, NOW(), ?, ?, ?, ?)
            ");
            $stmtP->execute([$tenant_id, $commission_id, $amount, $method_pay, $ref, $notes, $user_id]);

            // Adjust balances
            $new_outstanding = $comm['outstanding_amount'] - $amount;
            $new_paid = $comm['amount_paid'] + $amount;
            $new_status = ($new_outstanding == 0) ? 'Paid' : 'Partially Paid';

            $stmtUpd = $pdo->prepare("UPDATE commissions SET amount_paid = ?, outstanding_amount = ?, status = ? WHERE id = ?");
            $stmtUpd->execute([$new_paid, $new_outstanding, $new_status, $commission_id]);

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Payout recorded successfully.']);

        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}
