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

requirePermission('properties.view');

$pdo = getDB();

if ($method === 'GET') {
    if ($action === 'list_projects') {
        $stmt = $pdo->prepare("SELECT * FROM projects WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY created_at DESC");
        $stmt->execute([$tenant_id]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);

    } elseif ($action === 'list_properties') {
        // Clean up expired holds automatically on list fetch to ensure fresh UI representation
        $pdo->exec("UPDATE property_units SET status = 'Available', held_by = NULL, hold_expires_at = NULL WHERE status = 'Hold' AND hold_expires_at < CURRENT_TIMESTAMP");

        $stmt = $pdo->prepare("
            SELECT p.id, p.name, p.city, p.base_price, p.status, c.name as category, t.name as type, pr.name as project_name
            FROM properties p
            LEFT JOIN property_categories c ON p.category_id = c.id
            LEFT JOIN property_types t ON p.type_id = t.id
            LEFT JOIN projects pr ON p.project_id = pr.id
            WHERE p.tenant_id = ? AND p.deleted_at IS NULL
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$tenant_id]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);

    } elseif ($action === 'get_property' && isset($_GET['id'])) {
        $prop_id = (int)$_GET['id'];

        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name, t.name as type_name, pr.name as project_name
            FROM properties p
            LEFT JOIN property_categories c ON p.category_id = c.id
            LEFT JOIN property_types t ON p.type_id = t.id
            LEFT JOIN projects pr ON p.project_id = pr.id
            WHERE p.id = ? AND p.tenant_id = ? AND p.deleted_at IS NULL
        ");
        $stmt->execute([$prop_id, $tenant_id]);
        $prop = $stmt->fetch();

        if ($prop) {
            // Also fetch units
            $stmt = $pdo->prepare("
                SELECT u.*, f.floor_number, t.name as tower_name
                FROM property_units u
                LEFT JOIN project_floors f ON u.floor_id = f.id
                LEFT JOIN project_towers t ON f.tower_id = t.id
                WHERE u.property_id = ?
            ");
            $stmt->execute([$prop_id]);
            $prop['units'] = $stmt->fetchAll();

            echo json_encode(['status' => 'success', 'data' => $prop]);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Property not found.']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid action for GET method']);
    }
} elseif ($method === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    if ($action === 'create_project') {
        requirePermission('properties.create');
        $name = trim($_POST['name'] ?? '');
        $developer = trim($_POST['developer'] ?? '');

        if (empty($name)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Project Name is required.']);
            exit();
        }

        $stmt = $pdo->prepare("INSERT INTO projects (tenant_id, name, developer) VALUES (?, ?, ?)");
        if ($stmt->execute([$tenant_id, $name, $developer])) {
            echo json_encode(['status' => 'success', 'message' => 'Project created.', 'data' => ['id' => $pdo->lastInsertId()]]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Database error.']);
        }

    } elseif ($action === 'create_property') {
        requirePermission('properties.create');

        $name = trim($_POST['name'] ?? '');
        $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $type_id = !empty($_POST['type_id']) ? (int)$_POST['type_id'] : null;
        $purpose = !empty($_POST['purpose']) ? $_POST['purpose'] : null;
        $project_id = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;

        if (empty($name) || !$category_id || !$type_id || empty($purpose)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Name, Category, Type, and Purpose are required.']);
            exit();
        }

        if ($project_id) {
            $stmt = $pdo->prepare("SELECT id FROM projects WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$project_id, $tenant_id]);
            if (!$stmt->fetch()) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Invalid Project ID or unauthorized.']);
                exit();
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO properties (tenant_id, project_id, category_id, type_id, name, purpose, status)
            VALUES (?, ?, ?, ?, ?, ?, 'Available')
        ");

        if ($stmt->execute([$tenant_id, $project_id, $category_id, $type_id, $name, $purpose])) {
            echo json_encode(['status' => 'success', 'message' => 'Property created.', 'data' => ['id' => $pdo->lastInsertId()]]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Database error.']);
        }

    } elseif ($action === 'update_property' && isset($_POST['id'])) {
        requirePermission('properties.edit');
        $prop_id = (int)$_POST['id'];

        // IDOR check
        $stmt = $pdo->prepare("SELECT id FROM properties WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$prop_id, $tenant_id]);
        if (!$stmt->fetch()) {
             http_response_code(404);
             echo json_encode(['status' => 'error', 'message' => 'Property not found.']);
             exit();
        }

        $base_price = !empty($_POST['base_price']) ? (float)$_POST['base_price'] : null;
        $status = !empty($_POST['status']) ? $_POST['status'] : 'Available';

        $stmt = $pdo->prepare("UPDATE properties SET base_price = ?, status = ? WHERE id = ?");
        if ($stmt->execute([$base_price, $status, $prop_id])) {
            echo json_encode(['status' => 'success', 'message' => 'Property updated.']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Database error.']);
        }

    } elseif ($action === 'hold_inventory' && isset($_POST['unit_id'])) {
        requirePermission('properties.edit');
        $unit_id = (int)$_POST['unit_id'];

        try {
            $pdo->beginTransaction();

            // Ensure tenant owns unit through property relation and lock it
            $stmt = $pdo->prepare("
                SELECT pu.id, pu.status, pu.hold_expires_at
                FROM property_units pu
                JOIN properties p ON pu.property_id = p.id
                WHERE pu.id = ? AND p.tenant_id = ?
                FOR UPDATE
            ");
            $stmt->execute([$unit_id, $tenant_id]);
            $unit = $stmt->fetch();

            if (!$unit) {
                throw new Exception("Unit not found or unauthorized.");
            }

            if ($unit['status'] !== 'Available' && ($unit['status'] !== 'Hold' || strtotime($unit['hold_expires_at']) > time())) {
                throw new Exception("Unit is not available for holding.");
            }

            // Hold for 2 hours
            $stmt = $pdo->prepare("UPDATE property_units SET status = 'Hold', held_by = ?, hold_expires_at = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 2 HOUR) WHERE id = ?");
            $stmt->execute([$user_id, $unit_id]);

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Unit placed on hold for 2 hours.']);

        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }

    } elseif ($action === 'delete_property' && isset($_POST['id'])) {
        requirePermission('properties.delete');
        $prop_id = (int)$_POST['id'];

        $stmt = $pdo->prepare("UPDATE properties SET deleted_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$prop_id, $tenant_id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Property deleted.']);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Property not found.']);
        }
    } else {
         http_response_code(400);
         echo json_encode(['status' => 'error', 'message' => 'Invalid action for POST method']);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}
