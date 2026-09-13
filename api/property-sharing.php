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

if ($method === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    if ($action === 'share') {
        $lead_id = !empty($_POST['lead_id']) ? (int)$_POST['lead_id'] : null;
        $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
        $channel = $_POST['channel'] ?? '';
        $message = trim($_POST['message'] ?? '');
        $property_ids = isset($_POST['property_ids']) && is_array($_POST['property_ids']) ? $_POST['property_ids'] : [];

        if (empty($channel) || empty($property_ids)) {
             http_response_code(400);
             echo json_encode(['status' => 'error', 'message' => 'Channel and selected properties are required.']);
             exit();
        }

        if (!$lead_id && !$customer_id) {
             http_response_code(400);
             echo json_encode(['status' => 'error', 'message' => 'Lead ID or Customer ID is required.']);
             exit();
        }

        // Tenant security check
        if ($lead_id) {
            $stmt = $pdo->prepare("SELECT id FROM leads WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL LIMIT 1");
            $stmt->execute([$lead_id, $tenant_id]);
            if (!$stmt->fetch()) {
                 http_response_code(404);
                 echo json_encode(['status' => 'error', 'message' => 'Lead not found.']);
                 exit();
            }
        }
        if ($customer_id) {
            $stmt = $pdo->prepare("SELECT id FROM customers WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL LIMIT 1");
            $stmt->execute([$customer_id, $tenant_id]);
            if (!$stmt->fetch()) {
                 http_response_code(404);
                 echo json_encode(['status' => 'error', 'message' => 'Customer not found.']);
                 exit();
            }
        }

        try {
            $pdo->beginTransaction();

            // Insert primary share record
            $stmt = $pdo->prepare("
                INSERT INTO property_shares (tenant_id, lead_id, customer_id, shared_by, channel, message)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$tenant_id, $lead_id, $customer_id, $user_id, $channel, $message]);
            $share_id = $pdo->lastInsertId();

            // Validate properties and insert links securely
            foreach ($property_ids as $p_id) {
                $p_id = (int)$p_id;
                $stmt = $pdo->prepare("SELECT id FROM properties WHERE id = ? AND tenant_id = ? LIMIT 1");
                $stmt->execute([$p_id, $tenant_id]);
                if ($stmt->fetch()) {
                    $stmtIns = $pdo->prepare("INSERT INTO property_share_items (share_id, property_id) VALUES (?, ?)");
                    $stmtIns->execute([$share_id, $p_id]);
                }
            }

            // Log hook into lead_activities if applicable
            if ($lead_id) {
                require_once __DIR__ . '/../includes/leads.php';
                logLeadActivity($lead_id, "Shared Properties via $channel");
            }

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Properties shared successfully.']);

        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("Share Property Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'An error occurred during sharing.']);
        }
    } else {
         http_response_code(400);
         echo json_encode(['status' => 'error', 'message' => 'Invalid POST action']);
    }
} elseif ($method === 'GET') {
    if ($action === 'history') {
        $lead_id = !empty($_GET['lead_id']) ? (int)$_GET['lead_id'] : null;
        $customer_id = !empty($_GET['customer_id']) ? (int)$_GET['customer_id'] : null;

        if (!$lead_id && !$customer_id) {
             http_response_code(400);
             echo json_encode(['status' => 'error', 'message' => 'Lead ID or Customer ID is required.']);
             exit();
        }

        $sql = "
            SELECT ps.id, ps.channel, ps.status, ps.created_at, u.name as shared_by,
                   COUNT(psi.property_id) as properties_shared
            FROM property_shares ps
            JOIN users u ON ps.shared_by = u.id
            LEFT JOIN property_share_items psi ON ps.id = psi.share_id
            WHERE ps.tenant_id = ?
        ";

        $params = [$tenant_id];

        if ($lead_id) {
            $sql .= " AND ps.lead_id = ? ";
            $params[] = $lead_id;
        } else {
            $sql .= " AND ps.customer_id = ? ";
            $params[] = $customer_id;
        }

        $sql .= " GROUP BY ps.id ORDER BY ps.created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $shares = $stmt->fetchAll();

        echo json_encode(['status' => 'success', 'data' => $shares]);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid GET action']);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}
