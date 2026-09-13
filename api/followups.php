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
    if ($action === 'list') {
        $lead_id = !empty($_GET['lead_id']) ? (int)$_GET['lead_id'] : null;
        $customer_id = !empty($_GET['customer_id']) ? (int)$_GET['customer_id'] : null;

        $sql = "
            SELECT f.*, ft.name as type_name, u.name as user_name
            FROM followups f
            LEFT JOIN followup_types ft ON f.followup_type_id = ft.id
            LEFT JOIN users u ON f.user_id = u.id
            WHERE f.tenant_id = ?
        ";

        $params = [$tenant_id];

        if ($lead_id) {
            $sql .= " AND f.lead_id = ?";
            $params[] = $lead_id;
        } elseif ($customer_id) {
            $sql .= " AND f.customer_id = ?";
            $params[] = $customer_id;
        } else {
             http_response_code(400);
             echo json_encode(['status' => 'error', 'message' => 'Lead ID or Customer ID is required.']);
             exit();
        }

        $sql .= " ORDER BY f.followup_date ASC, f.followup_time ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $followups = $stmt->fetchAll();

        echo json_encode(['status' => 'success', 'data' => $followups]);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid action for GET method']);
    }
} elseif ($method === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    if ($action === 'create') {
        $lead_id = !empty($_POST['lead_id']) ? (int)$_POST['lead_id'] : null;
        $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;

        $followup_type_id = !empty($_POST['followup_type_id']) ? (int)$_POST['followup_type_id'] : null;
        $followup_date = $_POST['followup_date'] ?? null;
        $followup_time = !empty($_POST['followup_time']) ? $_POST['followup_time'] : null;
        $notes = trim($_POST['notes'] ?? '');
        $priority = !empty($_POST['priority']) ? $_POST['priority'] : 'Medium';

        if (empty($followup_date)) {
             http_response_code(400);
             echo json_encode(['status' => 'error', 'message' => 'Follow-up date is required.']);
             exit();
        }

        if (!$lead_id && !$customer_id) {
             http_response_code(400);
             echo json_encode(['status' => 'error', 'message' => 'Entity context required.']);
             exit();
        }

        // Ensure entity belongs to tenant
        if ($lead_id) {
            requirePermission('leads.edit');
            $stmt = $pdo->prepare("SELECT id FROM leads WHERE id = ? AND tenant_id = ? LIMIT 1");
            $stmt->execute([$lead_id, $tenant_id]);
        } else {
            requirePermission('customers.edit');
            $stmt = $pdo->prepare("SELECT id FROM customers WHERE id = ? AND tenant_id = ? LIMIT 1");
            $stmt->execute([$customer_id, $tenant_id]);
        }

        if (!$stmt->fetch()) {
             http_response_code(404);
             echo json_encode(['status' => 'error', 'message' => 'Entity not found.']);
             exit();
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO followups (tenant_id, lead_id, customer_id, user_id, followup_type_id, followup_date, followup_time, priority, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$tenant_id, $lead_id, $customer_id, $user_id, $followup_type_id, $followup_date, $followup_time, $priority, $notes]);

            if ($lead_id) {
                require_once __DIR__ . '/../includes/leads.php';
                logLeadActivity($lead_id, 'Added Follow-up');
            }

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Follow-up scheduled.']);
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Database error.']);
        }

    } elseif ($action === 'update_status' && isset($_POST['id'])) {
        $f_id = (int)$_POST['id'];
        $new_status = $_POST['status'] ?? '';
        $outcome = trim($_POST['outcome'] ?? '');

        if (!in_array($new_status, ['Pending', 'Completed', 'Overdue', 'Cancelled', 'Missed', 'Rescheduled'])) {
             http_response_code(400);
             echo json_encode(['status' => 'error', 'message' => 'Invalid status.']);
             exit();
        }

        try {
            $pdo->beginTransaction();

            // Concurrency safe read
            $stmt = $pdo->prepare("SELECT id, status, lead_id, customer_id FROM followups WHERE id = ? AND tenant_id = ? FOR UPDATE");
            $stmt->execute([$f_id, $tenant_id]);
            $followup = $stmt->fetch();

            if (!$followup) {
                 $pdo->rollBack();
                 http_response_code(404);
                 echo json_encode(['status' => 'error', 'message' => 'Follow-up not found.']);
                 exit();
            }

            if ($followup['status'] === $new_status) {
                 $pdo->rollBack();
                 echo json_encode(['status' => 'success', 'message' => 'Status unchanged.']);
                 exit();
            }

            if ($followup['lead_id']) requirePermission('leads.edit');
            if ($followup['customer_id']) requirePermission('customers.edit');

            $stmt = $pdo->prepare("UPDATE followups SET status = ?, outcome = ? WHERE id = ?");
            $stmt->execute([$new_status, $outcome, $f_id]);

            // Trigger follow up met hook if completed
            if ($new_status === 'Completed' && $followup['lead_id']) {
                require_once __DIR__ . '/../includes/leads.php';
                logLeadActivity($followup['lead_id'], 'Completed Follow-up');
            }

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Follow-up status updated.']);

        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Concurrency conflict or server error.']);
        }
    } else {
         http_response_code(400);
         echo json_encode(['status' => 'error', 'message' => 'Invalid action for POST method']);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}
