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
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

$pdo = getDB();

// Configurable constants
$ALLOWED_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'text/csv', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
$ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'csv', 'doc', 'docx'];
$MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
$STORAGE_DIR = __DIR__ . '/../uploads/private/'; // Outside direct web accessibility theoretically

if ($method === 'GET') {
    if ($action === 'list') {
        requirePermission('documents.view');

        $entity_type = $_GET['entity_type'] ?? '';
        $entity_id = $_GET['entity_id'] ?? '';

        $sql = "SELECT d.id, d.category, d.document_type, d.original_filename, d.file_size, d.entity_type, d.entity_id, d.status, d.created_at, u.name as uploaded_by_name
                FROM documents d
                LEFT JOIN users u ON d.uploaded_by = u.id
                WHERE d.tenant_id = ? AND d.status != 'Deleted'";
        $params = [$tenant_id];

        if ($entity_type && $entity_id) {
            $sql .= " AND d.entity_type = ? AND d.entity_id = ?";
            $params[] = $entity_type;
            $params[] = $entity_id;
        }

        $sql .= " ORDER BY d.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);

    } elseif ($action === 'download' || $action === 'preview') {
        requirePermission('documents.view');
        $doc_id = $_GET['id'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ? AND tenant_id = ? AND status != 'Deleted'");
        $stmt->execute([$doc_id, $tenant_id]);
        $doc = $stmt->fetch();

        if (!$doc) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(404);
            die(json_encode(['status' => 'error', 'message' => 'Document not found or access denied.']));
        }

        $file_path = $STORAGE_DIR . $doc['storage_path'];
        if (!file_exists($file_path)) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(404);
            die(json_encode(['status' => 'error', 'message' => 'File missing from storage disk.']));
        }

        $actionLog = ($action === 'download') ? 'download' : 'view';
        $stmtAudit = $pdo->prepare("INSERT INTO audit_logs (tenant_id, user_id, action, entity, entity_id, ip_address) VALUES (?, ?, ?, 'documents', ?, ?)");
        $stmtAudit->execute([$tenant_id, $user_id, $actionLog, $doc_id, $_SERVER['REMOTE_ADDR'] ?? '']);

        header('Content-Type: ' . $doc['mime_type']);
        header('Content-Length: ' . filesize($file_path));

        if ($action === 'download') {
            header('Content-Disposition: attachment; filename="' . basename($doc['original_filename']) . '"');
        } else {
            header('Content-Disposition: inline; filename="' . basename($doc['original_filename']) . '"');
        }

        readfile($file_path);
        die();
    }
} elseif ($method === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    if ($action === 'upload') {
        requirePermission('documents.upload');

        $entity_type = $_POST['entity_type'] ?? '';
        $entity_id = $_POST['entity_id'] ?? '';
        $category = $_POST['category'] ?? 'General';
        $doc_type = trim($_POST['document_type'] ?? 'Other');
        $notes = trim($_POST['notes'] ?? '');

        if (!$entity_type || !$entity_id) {
            http_response_code(400);
            die(json_encode(['status' => 'error', 'message' => 'Entity context required.']));
        }

        if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            die(json_encode(['status' => 'error', 'message' => 'File upload failed or empty.']));
        }

        $file = $_FILES['document'];
        $original_name = basename($file['name']);
        $file_size = $file['size'];
        $tmp_path = $file['tmp_name'];

        if ($file_size > $MAX_FILE_SIZE) {
            http_response_code(400);
            die(json_encode(['status' => 'error', 'message' => 'File exceeds 10MB limit.']));
        }

        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        if (!in_array($ext, $ALLOWED_EXTENSIONS)) {
            http_response_code(400);
            die(json_encode(['status' => 'error', 'message' => 'Invalid file extension.']));
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmp_path);
        finfo_close($finfo);

        if (!in_array($mime, $ALLOWED_MIME_TYPES)) {
            http_response_code(400);
            die(json_encode(['status' => 'error', 'message' => 'Invalid file content format (MIME validation failed).']));
        }

        $safe_internal_name = "t" . $tenant_id . "_" . uniqid() . bin2hex(random_bytes(4)) . "." . $ext;
        $destination = $STORAGE_DIR . $safe_internal_name;

        if (!move_uploaded_file($tmp_path, $destination)) {
            http_response_code(500);
            die(json_encode(['status' => 'error', 'message' => 'Failed to store file on disk.']));
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO documents
                (tenant_id, category, document_type, original_filename, storage_path, mime_type, file_size, entity_type, entity_id, notes, uploaded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $tenant_id, $category, $doc_type, $original_name, $safe_internal_name, $mime, $file_size, $entity_type, $entity_id, $notes, $user_id
            ]);
            $doc_id = $pdo->lastInsertId();

            $stmtAudit = $pdo->prepare("INSERT INTO audit_logs (tenant_id, user_id, action, entity, entity_id, ip_address) VALUES (?, ?, 'upload', 'documents', ?, ?)");
            $stmtAudit->execute([$tenant_id, $user_id, $doc_id, $_SERVER['REMOTE_ADDR'] ?? '']);

            echo json_encode(['status' => 'success', 'message' => 'Document uploaded securely.', 'data' => ['id' => $doc_id]]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Database failure mapping document.']);
        }

    } elseif ($action === 'archive') {
        requirePermission('documents.manage');
        $id = $_POST['id'] ?? '';

        $stmt = $pdo->prepare("UPDATE documents SET status = 'Archived' WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenant_id]);

        echo json_encode(['status' => 'success', 'message' => 'Document archived.']);
    }
}
