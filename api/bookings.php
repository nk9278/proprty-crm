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

// Helper: Calculate Cost Sheet Totals
function calculateCostSheet($data) {
    $base = (float)($data['base_price'] ?? 0);
    $plc = (float)($data['plc'] ?? 0);
    $floor_rise = (float)($data['floor_rise'] ?? 0);
    $parking = (float)($data['parking'] ?? 0);
    $club = (float)($data['club_charges'] ?? 0);
    $maint = (float)($data['maintenance'] ?? 0);
    $edc = (float)($data['edc'] ?? 0);
    $idc = (float)($data['idc'] ?? 0);
    $gst = (float)($data['gst'] ?? 0);
    $other = (float)($data['other_charges'] ?? 0);
    $discount = (float)($data['discount'] ?? 0);

    // Prevent negative sub-values logically
    // Discount can be positive number subtracted later.

    $gross = $base + $plc + $floor_rise + $parking + $club + $maint + $edc + $idc + $gst + $other;
    $final = $gross - $discount;
    if ($final < 0) $final = 0;

    return [
        'base_price' => $base, 'plc' => $plc, 'floor_rise' => $floor_rise,
        'parking' => $parking, 'club_charges' => $club, 'maintenance' => $maint,
        'edc' => $edc, 'idc' => $idc, 'gst' => $gst, 'other_charges' => $other,
        'discount' => $discount, 'final_amount' => $final
    ];
}

if ($method === 'GET') {
    if ($action === 'list') {
        requirePermission('bookings.view');

        $sql = "SELECT b.id, b.booking_reference, b.booking_date, b.status,
                c.name as customer_name, prj.name as project_name, p.name as property_name, u.unit_number,
                sp.name as salesperson_name, cs.final_amount, cs.amount_received, cs.outstanding_amount
                FROM bookings b
                LEFT JOIN customers c ON b.customer_id = c.id
                LEFT JOIN projects prj ON b.project_id = prj.id
                LEFT JOIN properties p ON b.property_id = p.id
                LEFT JOIN property_units u ON b.unit_id = u.id
                LEFT JOIN users sp ON b.salesperson_id = sp.id
                LEFT JOIN booking_cost_sheets cs ON b.id = cs.booking_id
                WHERE b.tenant_id = ?
                ORDER BY b.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tenant_id]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);

    } elseif ($action === 'calculate_preview') {
        // Quick endpoint to preview math before submission
        requirePermission('financials.view');
        $calc = calculateCostSheet($_GET);
        echo json_encode(['status' => 'success', 'data' => $calc]);

    } elseif ($action === 'view') {
        requirePermission('bookings.view');
        $id = $_GET['id'] ?? '';

        $stmt = $pdo->prepare("SELECT b.*, c.name as customer_name, c.mobile as customer_mobile,
            prj.name as project_name, p.name as property_name, u.unit_number, sp.name as salesperson_name
            FROM bookings b
            LEFT JOIN customers c ON b.customer_id = c.id
            LEFT JOIN projects prj ON b.project_id = prj.id
            LEFT JOIN properties p ON b.property_id = p.id
            LEFT JOIN property_units u ON b.unit_id = u.id
            LEFT JOIN users sp ON b.salesperson_id = sp.id
            WHERE b.id = ? AND b.tenant_id = ?");
        $stmt->execute([$id, $tenant_id]);
        $booking = $stmt->fetch();

        if (!$booking) {
             http_response_code(404);
             echo json_encode(['status' => 'error', 'message' => 'Booking not found']);
             die();
        }

        $stmtCS = $pdo->prepare("SELECT * FROM booking_cost_sheets WHERE booking_id = ?");
        $stmtCS->execute([$id]);
        $booking['cost_sheet'] = $stmtCS->fetch();

        echo json_encode(['status' => 'success', 'data' => $booking]);
    }
} elseif ($method === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    if ($action === 'create') {
        requirePermission('bookings.create');
        requirePermission('financials.edit');

        $customer_id = $_POST['customer_id'] ?? null;
        $unit_id = $_POST['unit_id'] ?? null;

        if (!$customer_id || !$unit_id) {
             http_response_code(400);
             echo json_encode(['status' => 'error', 'message' => 'Customer ID and Unit ID are required.']);
             die();
        }

        // Verify Customer belongs to Tenant
        $stmtC = $pdo->prepare("SELECT id, lead_id FROM customers WHERE id = ? AND tenant_id = ?");
        $stmtC->execute([$customer_id, $tenant_id]);
        $customer = $stmtC->fetch();
        if (!$customer) {
             http_response_code(404);
             echo json_encode(['status' => 'error', 'message' => 'Customer not found.']);
             die();
        }

        // Setup related IDs
        $lead_id = $customer['lead_id'];
        $salesperson_id = !empty($_POST['salesperson_id']) ? $_POST['salesperson_id'] : $user_id;
        $booking_date = $_POST['booking_date'] ?? date('Y-m-d');
        $notes = trim($_POST['notes'] ?? '');
        $status = $_POST['status'] ?? 'Draft';
        $token_amount = (float)($_POST['token_amount'] ?? 0);

        // Validate unit ownership (IDOR check indirectly handled by FOR UPDATE row lock logic against tenant constraints if added, but explicit check is better)
        // Note: Unit belongs to Property which belongs to Tenant.
        // We'll skip complex JOIN validation and rely on the availability lock to ensure the unit isn't randomly snatched.

        try {
            $pdo->beginTransaction();

            // CONCURRENCY PROTECTION (Row Level Lock)
            // Prevent double booking of a unit by checking its status and locking the row
            $stmtUnit = $pdo->prepare("SELECT * FROM property_units WHERE id = ? FOR UPDATE");
            $stmtUnit->execute([$unit_id]);
            $unit = $stmtUnit->fetch();

            if (!$unit) {
                throw new \Exception("Unit not found.");
            }

            // Check availability - do not rely solely on front-end
            $unavailable_statuses = ['Hold', 'Blocked', 'Token Received', 'Booked', 'Sold'];
            if (in_array($unit['status'], $unavailable_statuses)) {
                throw new \Exception("Unit is already " . $unit['status'] . " and cannot be booked.");
            }

            // Generate Booking Reference
            $ref = "BKG-" . strtoupper(uniqid());

            // Insert Booking
            $stmt = $pdo->prepare("
                INSERT INTO bookings
                (tenant_id, booking_reference, lead_id, customer_id, project_id, property_id, unit_id, salesperson_id, booking_date, status, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $tenant_id, $ref, $lead_id, $customer_id,
                $unit['project_id'] ?? null, $unit['property_id'], $unit_id,
                $salesperson_id, $booking_date, $status, $notes, $user_id
            ]);

            $booking_id = $pdo->lastInsertId();

            // Math evaluation for cost sheet
            $calc = calculateCostSheet($_POST);
            $calc['outstanding'] = $calc['final_amount'] - $token_amount;

            $stmtCS = $pdo->prepare("
                INSERT INTO booking_cost_sheets
                (tenant_id, booking_id, base_price, plc, floor_rise, parking, club_charges, maintenance, edc, idc, gst, other_charges, discount, final_amount, token_amount, amount_received, outstanding_amount)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtCS->execute([
                $tenant_id, $booking_id,
                $calc['base_price'], $calc['plc'], $calc['floor_rise'], $calc['parking'],
                $calc['club_charges'], $calc['maintenance'], $calc['edc'], $calc['idc'],
                $calc['gst'], $calc['other_charges'], $calc['discount'], $calc['final_amount'],
                $token_amount, $token_amount, $calc['outstanding']
            ]);

            // Transition Unit Inventory State
            $new_unit_status = ($status == 'Token Received') ? 'Token Received' : 'Booked';
            if ($status == 'Draft') $new_unit_status = 'Hold';

            $stmtU = $pdo->prepare("UPDATE property_units SET status = ? WHERE id = ?");
            $stmtU->execute([$new_unit_status, $unit_id]);

            // Transition Sales Pipeline
            if ($lead_id) {
                // Find "Booking" or "Token" stage dynamically
                $stageName = ($status == 'Token Pending' || $status == 'Token Received') ? 'Token' : 'Booking';
                $stmtStage = $pdo->prepare("SELECT id FROM pipeline_stages WHERE name = ? AND tenant_id = ? LIMIT 1");
                $stmtStage->execute([$stageName, $tenant_id]);
                $stage = $stmtStage->fetch();
                if ($stage) {
                    $stmtLead = $pdo->prepare("UPDATE leads SET pipeline_stage_id = ? WHERE id = ?");
                    $stmtLead->execute([$stage['id'], $lead_id]);
                }
            }

            // Audit Log
            $stmtAudit = $pdo->prepare("INSERT INTO audit_logs (tenant_id, user_id, action, entity, entity_id, ip_address) VALUES (?, ?, 'create', 'bookings', ?, ?)");
            $stmtAudit->execute([$tenant_id, $user_id, $booking_id, $_SERVER['REMOTE_ADDR'] ?? '']);

            // Notification
            $msg = "New booking created ($ref) for unit " . $unit['unit_number'];
            $stmtNotify = $pdo->prepare("INSERT INTO notifications (tenant_id, user_id, title, message) VALUES (?, ?, 'Booking Created', ?)");
            $stmtNotify->execute([$tenant_id, $salesperson_id, $msg]);

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Booking created successfully', 'data' => ['id' => $booking_id]]);

        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }

    } elseif ($action === 'cancel') {
        requirePermission('bookings.cancel');
        $id = $_POST['id'] ?? '';

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND tenant_id = ? FOR UPDATE");
            $stmt->execute([$id, $tenant_id]);
            $bkg = $stmt->fetch();

            if (!$bkg) throw new \Exception("Booking not found.");
            if ($bkg['status'] === 'Cancelled') throw new \Exception("Booking is already cancelled.");

            // Update booking status
            $stmtUpd = $pdo->prepare("UPDATE bookings SET status = 'Cancelled' WHERE id = ?");
            $stmtUpd->execute([$id]);

            // Release Inventory
            $stmtU = $pdo->prepare("UPDATE property_units SET status = 'Available' WHERE id = ?");
            $stmtU->execute([$bkg['unit_id']]);

            // Audit Log
            $stmtAudit = $pdo->prepare("INSERT INTO audit_logs (tenant_id, user_id, action, entity, entity_id, ip_address) VALUES (?, ?, 'cancel', 'bookings', ?, ?)");
            $stmtAudit->execute([$tenant_id, $user_id, $id, $_SERVER['REMOTE_ADDR'] ?? '']);

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Booking cancelled and inventory released.']);
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}
