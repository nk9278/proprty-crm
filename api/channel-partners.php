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
    if ($action === 'list') {
        requirePermission('partners.view');

        $stmt = $pdo->prepare("SELECT * FROM channel_partners WHERE tenant_id = ? ORDER BY created_at DESC");
        $stmt->execute([$tenant_id]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
    }
} elseif ($method === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    if ($action === 'create') {
        requirePermission('partners.create');

        $company_name = trim($_POST['company_name'] ?? '');
        $contact_person = trim($_POST['contact_person'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $whatsapp = trim($_POST['whatsapp'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $gst = trim($_POST['gst_number'] ?? '');
        $pan = trim($_POST['pan_number'] ?? '');
        $rera = trim($_POST['rera_number'] ?? '');
        $status = $_POST['status'] ?? 'Active';

        if (!$company_name || !$contact_person || !$mobile) {
             http_response_code(400);
             echo json_encode(['status' => 'error', 'message' => 'Company Name, Contact Person, and Mobile are required.']);
             die();
        }

        $stmt = $pdo->prepare("
            INSERT INTO channel_partners
            (tenant_id, company_name, contact_person, mobile, whatsapp, email, address, gst_number, pan_number, rera_number, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        try {
            $stmt->execute([$tenant_id, $company_name, $contact_person, $mobile, $whatsapp, $email, $address, $gst, $pan, $rera, $status]);
            echo json_encode(['status' => 'success', 'message' => 'Partner added successfully.', 'data' => ['id' => $pdo->lastInsertId()]]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
        }
    }
}
