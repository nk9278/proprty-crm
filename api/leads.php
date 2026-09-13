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

// Check general leads access
requirePermission('leads.view');

$pdo = getDB();

if ($method === 'GET') {
    if ($action === 'list') {
        $stmt = $pdo->prepare("
            SELECT l.id, l.name, l.mobile, l.email, ls.name as status, lsrc.name as source, u.name as assigned_to, l.created_at
            FROM leads l
            LEFT JOIN lead_statuses ls ON l.status_id = ls.id
            LEFT JOIN lead_sources lsrc ON l.source_id = lsrc.id
            LEFT JOIN users u ON l.assigned_to = u.id
            WHERE l.tenant_id = ? AND l.deleted_at IS NULL
            ORDER BY l.created_at DESC
        ");
        $stmt->execute([$tenant_id]);
        $leads = $stmt->fetchAll();
        echo json_encode(['status' => 'success', 'data' => $leads]);
    } elseif ($action === 'get' && isset($_GET['id'])) {
        $lead_id = (int)$_GET['id'];
        $stmt = $pdo->prepare("
            SELECT l.*, ls.name as status_name, lsrc.name as source_name, u.name as assigned_to_name
            FROM leads l
            LEFT JOIN lead_statuses ls ON l.status_id = ls.id
            LEFT JOIN lead_sources lsrc ON l.source_id = lsrc.id
            LEFT JOIN users u ON l.assigned_to = u.id
            WHERE l.id = ? AND l.tenant_id = ? AND l.deleted_at IS NULL
        ");
        $stmt->execute([$lead_id, $tenant_id]);
        $lead = $stmt->fetch();

        if ($lead) {
            // Also fetch tags
            $stmt = $pdo->prepare("
                SELECT t.id, t.name, t.color
                FROM lead_tags t
                JOIN lead_tag_map m ON t.id = m.tag_id
                WHERE m.lead_id = ?
            ");
            $stmt->execute([$lead_id]);
            $lead['tags'] = $stmt->fetchAll();

            // Also fetch follow-ups
            $stmt = $pdo->prepare("
                SELECT f.*, ft.name as type_name, u.name as user_name
                FROM lead_followups f
                LEFT JOIN followup_types ft ON f.followup_type_id = ft.id
                LEFT JOIN users u ON f.user_id = u.id
                WHERE f.lead_id = ?
                ORDER BY f.followup_date DESC, f.followup_time DESC
            ");
            $stmt->execute([$lead_id]);
            $lead['followups'] = $stmt->fetchAll();

            echo json_encode(['status' => 'success', 'data' => $lead]);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Lead not found.']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid action for GET method']);
    }
} elseif ($method === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    if ($action === 'create') {
        requirePermission('leads.create');

        $name = trim($_POST['name'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $budget_min = !empty($_POST['budget_min']) ? (float)$_POST['budget_min'] : null;
        $budget_max = !empty($_POST['budget_max']) ? (float)$_POST['budget_max'] : null;
        $requirement = trim($_POST['requirement'] ?? '');
        $source_id = !empty($_POST['source_id']) ? (int)$_POST['source_id'] : null;
        $status_id = !empty($_POST['status_id']) ? (int)$_POST['status_id'] : null;

        // Basic validation
        if (empty($name) || empty($mobile)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Name and Mobile are required.']);
            exit();
        }

        $force = isset($_POST['force']) && $_POST['force'] === '1';

        // Advanced Duplicate Check inside the tenant
        if (!$force) {
            $stmt = $pdo->prepare("SELECT id FROM leads WHERE tenant_id = ? AND (mobile = ? OR email = ?) AND deleted_at IS NULL LIMIT 1");
            $stmt->execute([$tenant_id, $mobile, $email ?: 'never_match']);
            $duplicate = $stmt->fetch();
            if ($duplicate) {
                 http_response_code(409); // Conflict
                 echo json_encode([
                     'status' => 'duplicate',
                     'message' => 'A lead with this mobile number or email already exists in your workspace.',
                     'duplicate_id' => $duplicate['id']
                 ]);
                 exit();
            }
        }

        try {
            $pdo->beginTransaction();

            // Insert Lead
            $stmt = $pdo->prepare("
                INSERT INTO leads (tenant_id, name, mobile, email, city, budget_min, budget_max, requirement, source_id, status_id, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $tenant_id,
                $name,
                $mobile,
                $email,
                $city,
                $budget_min,
                $budget_max,
                $requirement,
                $source_id,
                $status_id,
                $user_id
            ]);
            $lead_id = $pdo->lastInsertId();

            // Audit Log
            $stmt = $pdo->prepare("INSERT INTO lead_activities (lead_id, user_id, action, new_value) VALUES (?, ?, 'Created Lead', ?)");
            $stmt->execute([$lead_id, $user_id, json_encode(['name' => $name, 'mobile' => $mobile])]);

            $pdo->commit();

            echo json_encode(['status' => 'success', 'message' => 'Lead created successfully.', 'data' => ['id' => $lead_id]]);
        } catch (\Exception $e) {
            $pdo->rollBack();
            error_log("Lead Create Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'An error occurred while creating the lead.']);
        }

    } elseif ($action === 'update' && isset($_POST['id'])) {
        requirePermission('leads.edit');
        $lead_id = (int)$_POST['id'];

        // Verify tenant ownership
        $stmt = $pdo->prepare("SELECT id FROM leads WHERE id = ? AND tenant_id = ? LIMIT 1");
        $stmt->execute([$lead_id, $tenant_id]);
        if (!$stmt->fetch()) {
             http_response_code(404);
             echo json_encode(['status' => 'error', 'message' => 'Lead not found.']);
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
        $budget_min = !empty($_POST['budget_min']) ? (float)$_POST['budget_min'] : null;
        $budget_max = !empty($_POST['budget_max']) ? (float)$_POST['budget_max'] : null;
        $requirement = trim($_POST['requirement'] ?? '');
        $source_id = !empty($_POST['source_id']) ? (int)$_POST['source_id'] : null;
        $status_id = !empty($_POST['status_id']) ? (int)$_POST['status_id'] : null;
        $property_category_id = !empty($_POST['property_category_id']) ? (int)$_POST['property_category_id'] : null;
        $property_type_id = !empty($_POST['property_type_id']) ? (int)$_POST['property_type_id'] : null;
        $bhk_id = !empty($_POST['bhk_id']) ? (int)$_POST['bhk_id'] : null;
        $preferred_location = trim($_POST['preferred_location'] ?? '');
        $purpose = !empty($_POST['purpose']) ? $_POST['purpose'] : null;
        $financing = trim($_POST['financing'] ?? '');
        $lead_temperature = !empty($_POST['lead_temperature']) ? $_POST['lead_temperature'] : 'Cold';

        if (empty($name) || empty($mobile)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Name and Mobile are required.']);
            exit();
        }

        $stmt = $pdo->prepare("
            UPDATE leads SET
                name = ?, mobile = ?, alternate_mobile = ?, whatsapp = ?, email = ?,
                city = ?, state = ?, country = ?, address = ?, budget_min = ?,
                budget_max = ?, requirement = ?, source_id = ?, status_id = ?,
                property_category_id = ?, property_type_id = ?, bhk_id = ?,
                preferred_location = ?, purpose = ?, financing = ?, lead_temperature = ?
            WHERE id = ? AND tenant_id = ?
        ");

        $stmt->execute([
            $name, $mobile, $alternate_mobile, $whatsapp, $email,
            $city, $state, $country, $address, $budget_min,
            $budget_max, $requirement, $source_id, $status_id,
            $property_category_id, $property_type_id, $bhk_id,
            $preferred_location, $purpose, $financing, $lead_temperature,
            $lead_id, $tenant_id
        ]);

        // Audit log
        $stmt = $pdo->prepare("INSERT INTO lead_activities (lead_id, user_id, action) VALUES (?, ?, 'Updated Lead Details')");
        $stmt->execute([$lead_id, $user_id]);

        echo json_encode(['status' => 'success', 'message' => 'Lead updated successfully.']);

    } elseif ($action === 'delete' && isset($_POST['id'])) {
        requirePermission('leads.delete');
        $lead_id = (int)$_POST['id'];

        // Soft delete implementation verifying tenant isolation
        $stmt = $pdo->prepare("UPDATE leads SET deleted_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$lead_id, $tenant_id]);

        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->prepare("INSERT INTO lead_activities (lead_id, user_id, action) VALUES (?, ?, 'Deleted Lead')");
            $stmt->execute([$lead_id, $user_id]);
            echo json_encode(['status' => 'success', 'message' => 'Lead deleted successfully.']);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Lead not found or already deleted.']);
        }
    } elseif ($action === 'add_tag' && isset($_POST['lead_id']) && isset($_POST['tag_name'])) {
        requirePermission('leads.edit');
        $lead_id = (int)$_POST['lead_id'];
        $tag_name = trim($_POST['tag_name']);

        // Verify tenant isolation
        $stmt = $pdo->prepare("SELECT id FROM leads WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$lead_id, $tenant_id]);
        if (!$stmt->fetch()) {
             http_response_code(404);
             echo json_encode(['status' => 'error', 'message' => 'Lead not found.']);
             exit();
        }

        // Get or Create Tag
        $stmt = $pdo->prepare("SELECT id FROM lead_tags WHERE tenant_id = ? AND name = ? LIMIT 1");
        $stmt->execute([$tenant_id, $tag_name]);
        $tag = $stmt->fetch();
        if ($tag) {
            $tag_id = $tag['id'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO lead_tags (tenant_id, name) VALUES (?, ?)");
            $stmt->execute([$tenant_id, $tag_name]);
            $tag_id = $pdo->lastInsertId();
        }

        // Assign Tag
        $stmt = $pdo->prepare("INSERT IGNORE INTO lead_tag_map (lead_id, tag_id) VALUES (?, ?)");
        $stmt->execute([$lead_id, $tag_id]);

        echo json_encode(['status' => 'success', 'message' => 'Tag added successfully.']);

    } elseif ($action === 'assign' && isset($_POST['lead_id']) && isset($_POST['user_id'])) {
        requirePermission('leads.assign');
        $lead_id = (int)$_POST['lead_id'];
        $new_user_id = (int)$_POST['user_id'];
        $method = $_POST['assignment_method'] ?? 'Manual';

        require_once __DIR__ . '/../includes/leads.php';

        if (assignLead($lead_id, $new_user_id, $method)) {
            echo json_encode(['status' => 'success', 'message' => 'Lead assigned successfully.']);
        } else {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Failed to assign lead. It may not exist or you lack permission.']);
        }

    } elseif ($action === 'add_followup' && isset($_POST['lead_id'])) {
        requirePermission('leads.edit');
        $lead_id = (int)$_POST['lead_id'];
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

        // Verify tenant isolation
        $stmt = $pdo->prepare("SELECT id FROM leads WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$lead_id, $tenant_id]);
        if (!$stmt->fetch()) {
             http_response_code(404);
             echo json_encode(['status' => 'error', 'message' => 'Lead not found.']);
             exit();
        }

        $stmt = $pdo->prepare("
            INSERT INTO lead_followups (lead_id, user_id, followup_type_id, followup_date, followup_time, priority, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$lead_id, $user_id, $followup_type_id, $followup_date, $followup_time, $priority, $notes]);

        // Log Activity
        $stmt = $pdo->prepare("INSERT INTO lead_activities (lead_id, user_id, action) VALUES (?, ?, 'Added Follow-up')");
        $stmt->execute([$lead_id, $user_id]);

        echo json_encode(['status' => 'success', 'message' => 'Follow-up scheduled.']);

    } else {
         http_response_code(400);
         echo json_encode(['status' => 'error', 'message' => 'Invalid action for POST method']);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}
