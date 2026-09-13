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
    requirePermission('site_visits.view');

    if ($action === 'list') {
        $status = $_GET['status'] ?? '';
        $salesperson = $_GET['salesperson_id'] ?? '';
        $lead_id = $_GET['lead_id'] ?? '';
        $customer_id = $_GET['customer_id'] ?? '';

        $sql = "SELECT sv.*,
                l.name as lead_name, c.name as customer_name,
                p.title as property_title, prj.name as project_name,
                u.unit_number,
                sp.name as salesperson_name
                FROM site_visits sv
                LEFT JOIN leads l ON sv.lead_id = l.id
                LEFT JOIN customers c ON sv.customer_id = c.id
                LEFT JOIN properties p ON sv.property_id = p.id
                LEFT JOIN projects prj ON sv.project_id = prj.id
                LEFT JOIN property_units u ON sv.unit_id = u.id
                LEFT JOIN users sp ON sv.salesperson_id = sp.id
                WHERE sv.tenant_id = ?";
        $params = [$tenant_id];

        if ($status) {
            $sql .= " AND sv.status = ?";
            $params[] = $status;
        }
        if ($salesperson) {
            $sql .= " AND sv.salesperson_id = ?";
            $params[] = $salesperson;
        }
        if ($lead_id) {
            $sql .= " AND sv.lead_id = ?";
            $params[] = $lead_id;
        }
        if ($customer_id) {
            $sql .= " AND sv.customer_id = ?";
            $params[] = $customer_id;
        }

        $sql .= " ORDER BY sv.scheduled_date DESC, sv.scheduled_time DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
    }
} elseif ($method === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    if ($action === 'create') {
        requirePermission('site_visits.create');

        $lead_id = !empty($_POST['lead_id']) ? $_POST['lead_id'] : null;
        $customer_id = !empty($_POST['customer_id']) ? $_POST['customer_id'] : null;

        if (!$lead_id && !$customer_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Lead or Customer ID is required.']);
            die();
        }

        $project_id = !empty($_POST['project_id']) ? $_POST['project_id'] : null;
        $property_id = !empty($_POST['property_id']) ? $_POST['property_id'] : null;
        $unit_id = !empty($_POST['unit_id']) ? $_POST['unit_id'] : null;

        if ($lead_id && !$property_id) {
             $stmt = $pdo->prepare("SELECT property_id, project_id FROM leads WHERE id = ? AND tenant_id = ?");
             $stmt->execute([$lead_id, $tenant_id]);
             $l = $stmt->fetch();
             if($l) {
                 $property_id = $property_id ?: $l['property_id'];
                 $project_id = $project_id ?: $l['project_id'];
             }
        }

        $salesperson_id = !empty($_POST['salesperson_id']) ? $_POST['salesperson_id'] : $user_id;
        $scheduled_date = $_POST['scheduled_date'] ?? '';
        $scheduled_time = $_POST['scheduled_time'] ?? '';
        $visitor_count = !empty($_POST['visitor_count']) ? (int)$_POST['visitor_count'] : 1;
        $visitor_names = trim($_POST['visitor_names'] ?? '');

        if (empty($scheduled_date) || empty($scheduled_time)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Schedule date and time are required.']);
            die();
        }

        $stmt = $pdo->prepare("
            INSERT INTO site_visits
            (tenant_id, lead_id, customer_id, project_id, property_id, unit_id, salesperson_id, scheduled_date, scheduled_time, visitor_count, visitor_names, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Scheduled', ?)
        ");

        try {
            $stmt->execute([
                $tenant_id, $lead_id, $customer_id, $project_id, $property_id, $unit_id,
                $salesperson_id, $scheduled_date, $scheduled_time, $visitor_count, $visitor_names, $user_id
            ]);
            $sv_id = $pdo->lastInsertId();

            if ($salesperson_id != $user_id) {
                $msg = "A new site visit has been scheduled for you on " . $scheduled_date;
                $stmtNotify = $pdo->prepare("INSERT INTO notifications (tenant_id, user_id, title, message) VALUES (?, ?, 'Site Visit Scheduled', ?)");
                $stmtNotify->execute([$tenant_id, $salesperson_id, $msg]);
            }

            echo json_encode(['status' => 'success', 'message' => 'Site Visit Scheduled successfully.', 'data' => ['id' => $sv_id]]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Database error.']);
        }
    } elseif ($action === 'update_status') {
        requirePermission('site_visits.edit');

        $id = $_POST['id'] ?? '';
        $new_status = $_POST['status'] ?? '';

        if (!$id || !$new_status) {
             http_response_code(400);
             echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
             die();
        }

        $valid_statuses = ['Scheduled', 'Confirmed', 'Rescheduled', 'Completed', 'Cancelled', 'No-Show'];
        if (!in_array($new_status, $valid_statuses)) {
             http_response_code(400);
             echo json_encode(['status' => 'error', 'message' => 'Invalid status']);
             die();
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT * FROM site_visits WHERE id = ? AND tenant_id = ? FOR UPDATE");
            $stmt->execute([$id, $tenant_id]);
            $visit = $stmt->fetch();

            if (!$visit) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Visit not found.']);
                die();
            }

            $check_in_q = "";
            $check_in_params = [];

            if ($new_status === 'Completed' && $visit['status'] !== 'Completed') {
                 $check_in_q = ", check_out_time = NOW()";
                 if (empty($visit['check_in_time'])) {
                     $check_in_q = ", check_in_time = NOW(), check_out_time = NOW()";
                 }
            }

            $stmtUpdate = $pdo->prepare("UPDATE site_visits SET status = ? $check_in_q WHERE id = ?");
            $stmtUpdate->execute(array_merge([$new_status], $check_in_params, [$id]));

            if ($new_status === 'Completed' && isset($_POST['feedback'])) {
                $feedback = trim($_POST['feedback']);
                $interest = $_POST['interest_level'] ?? 'Medium';
                $notes = trim($_POST['notes'] ?? '');

                $stmtFb = $pdo->prepare("UPDATE site_visits SET feedback = ?, interest_level = ?, notes = ? WHERE id = ?");
                $stmtFb->execute([$feedback, $interest, $notes, $id]);

                if ($visit['lead_id']) {
                    $score = 0;
                    $temp = 'Warm';
                    if ($interest === 'High') { $score = 30; $temp = 'Hot'; }
                    elseif ($interest === 'Low') { $temp = 'Cold'; }

                    $stmtLead = $pdo->prepare("UPDATE leads SET temperature = ?, score = score + ? WHERE id = ?");
                    $stmtLead->execute([$temp, $score, $visit['lead_id']]);
                }
            }

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Status updated successfully.']);
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Database error.']);
        }
    } elseif ($action === 'check_in') {
        requirePermission('site_visits.edit');
        $id = $_POST['id'] ?? '';

        $stmt = $pdo->prepare("UPDATE site_visits SET check_in_time = NOW(), status = 'Confirmed' WHERE id = ? AND tenant_id = ? AND check_in_time IS NULL");
        $stmt->execute([$id, $tenant_id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Checked in successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid visit or already checked in.']);
        }
    } elseif ($action === 'check_out') {
        requirePermission('site_visits.edit');
        $id = $_POST['id'] ?? '';

        $stmt = $pdo->prepare("UPDATE site_visits SET check_out_time = NOW() WHERE id = ? AND tenant_id = ? AND check_in_time IS NOT NULL AND check_out_time IS NULL");
        $stmt->execute([$id, $tenant_id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Checked out successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid visit, not checked in, or already checked out.']);
        }
    }
}
