<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../config/database.php';

// Ensure user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$tenant_id = current_tenant_id();
$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Check general customers access
requirePermission('customers.view');

$pdo = getDB();

if ($method === 'GET') {
    if ($action === 'list') {
        $stmt = $pdo->prepare("
            SELECT c.id, c.name, c.mobile, c.email, c.city, u.name as assigned_to, c.created_at
            FROM customers c
            LEFT JOIN users u ON c.assigned_to = u.id
            WHERE c.tenant_id = ? AND c.deleted_at IS NULL
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$tenant_id]);
        $customers = $stmt->fetchAll();
        echo json_encode(['status' => 'success', 'data' => $customers]);

    } elseif ($action === 'get' && isset($_GET['id'])) {
        $customer_id = (int)$_GET['id'];
        $stmt = $pdo->prepare("
            SELECT c.*, u.name as assigned_to_name, ls.name as source_name
            FROM customers c
            LEFT JOIN users u ON c.assigned_to = u.id
            LEFT JOIN lead_sources ls ON c.source_id = ls.id
            WHERE c.id = ? AND c.tenant_id = ? AND c.deleted_at IS NULL
        ");
        $stmt->execute([$customer_id, $tenant_id]);
        $customer = $stmt->fetch();

        if ($customer) {
            echo json_encode(['status' => 'success', 'data' => $customer]);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Customer not found.']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid action for GET method']);
    }
} elseif ($method === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    if ($action === 'create') {
        requirePermission('customers.create');

        $name = trim($_POST['name'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $alternate_mobile = trim($_POST['alternate_mobile'] ?? '');
        $whatsapp = trim($_POST['whatsapp'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $occupation = trim($_POST['occupation'] ?? '');
        $company = trim($_POST['company'] ?? '');
        $source_id = !empty($_POST['source_id']) ? (int)$_POST['source_id'] : null;
        $assigned_to = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;

        if (empty($name) || empty($mobile)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Name and Mobile are required.']);
            exit();
        }

        // Advanced Duplicate Check inside the tenant
        $force = isset($_POST['force']) && $_POST['force'] === '1';
        if (!$force) {
            $stmt = $pdo->prepare("SELECT id FROM customers WHERE tenant_id = ? AND (mobile = ? OR email = ?) AND deleted_at IS NULL LIMIT 1");
            $stmt->execute([$tenant_id, $mobile, $email ?: 'never_match']);
            $duplicate = $stmt->fetch();
            if ($duplicate) {
                 http_response_code(409); // Conflict
                 echo json_encode([
                     'status' => 'duplicate',
                     'message' => 'A customer with this mobile number or email already exists.',
                     'duplicate_id' => $duplicate['id']
                 ]);
                 exit();
            }
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO customers (tenant_id, name, mobile, alternate_mobile, whatsapp, email, city, state, country, address, occupation, company, source_id, assigned_to, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $tenant_id, $name, $mobile, $alternate_mobile, $whatsapp, $email, $city, $state, $country, $address, $occupation, $company, $source_id, $assigned_to, $user_id
            ]);
            $customer_id = $pdo->lastInsertId();

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Customer created successfully.', 'data' => ['id' => $customer_id]]);
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("Customer Create Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'An error occurred while creating the customer.']);
        }

    } elseif ($action === 'update' && isset($_POST['id'])) {
        requirePermission('customers.edit');
        $customer_id = (int)$_POST['id'];

        // Verify tenant ownership
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE id = ? AND tenant_id = ? LIMIT 1");
        $stmt->execute([$customer_id, $tenant_id]);
        if (!$stmt->fetch()) {
             http_response_code(404);
             echo json_encode(['status' => 'error', 'message' => 'Customer not found.']);
             exit();
        }

        $name = trim($_POST['name'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $alternate_mobile = trim($_POST['alternate_mobile'] ?? '');
        $whatsapp = trim($_POST['whatsapp'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $occupation = trim($_POST['occupation'] ?? '');
        $company = trim($_POST['company'] ?? '');
        $source_id = !empty($_POST['source_id']) ? (int)$_POST['source_id'] : null;
        $assigned_to = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;

        if (empty($name) || empty($mobile)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Name and Mobile are required.']);
            exit();
        }

        $stmt = $pdo->prepare("
            UPDATE customers SET
                name = ?, mobile = ?, alternate_mobile = ?, whatsapp = ?, email = ?,
                city = ?, state = ?, country = ?, address = ?, occupation = ?,
                company = ?, source_id = ?, assigned_to = ?
            WHERE id = ? AND tenant_id = ?
        ");

        $stmt->execute([
            $name, $mobile, $alternate_mobile, $whatsapp, $email,
            $city, $state, $country, $address, $occupation,
            $company, $source_id, $assigned_to,
            $customer_id, $tenant_id
        ]);

        echo json_encode(['status' => 'success', 'message' => 'Customer updated successfully.']);

    } elseif ($action === 'delete' && isset($_POST['id'])) {
        requirePermission('customers.delete');
        $customer_id = (int)$_POST['id'];

        $stmt = $pdo->prepare("UPDATE customers SET deleted_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$customer_id, $tenant_id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Customer deleted successfully.']);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Customer not found or already deleted.']);
        }
    } else {
         http_response_code(400);
         echo json_encode(['status' => 'error', 'message' => 'Invalid action for POST method']);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}
