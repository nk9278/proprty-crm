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

requirePermission('tasks.view');

$pdo = getDB();

if ($method === 'GET') {
    if ($action === 'list') {
        $filter_status = $_GET['status'] ?? null;
        $sql = "
            SELECT t.*, u.name as assigned_to_name, l.name as lead_name, c.name as customer_name
            FROM tasks t
            LEFT JOIN users u ON t.assigned_user = u.id
            LEFT JOIN leads l ON t.related_lead = l.id
            LEFT JOIN customers c ON t.related_customer = c.id
            WHERE t.tenant_id = ?
        ";
        $params = [$tenant_id];

        if ($filter_status) {
            $sql .= " AND t.status = ?";
            $params[] = $filter_status;
        }

        $sql .= " ORDER BY t.due_date ASC, t.due_time ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll();

        echo json_encode(['status' => 'success', 'data' => $tasks]);

    } elseif ($action === 'get' && isset($_GET['id'])) {
        $task_id = (int)$_GET['id'];
        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? AND tenant_id = ? LIMIT 1");
        $stmt->execute([$task_id, $tenant_id]);
        $task = $stmt->fetch();

        if ($task) {
            echo json_encode(['status' => 'success', 'data' => $task]);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Task not found.']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid action for GET method']);
    }
} elseif ($method === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    if ($action === 'create') {
        requirePermission('tasks.create');

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = !empty($_POST['priority']) ? $_POST['priority'] : 'Medium';
        $due_date = $_POST['due_date'] ?? '';
        $due_time = !empty($_POST['due_time']) ? $_POST['due_time'] : null;

        $assigned_user = !empty($_POST['assigned_user']) ? (int)$_POST['assigned_user'] : $user_id;
        $related_lead = !empty($_POST['related_lead']) ? (int)$_POST['related_lead'] : null;
        $related_customer = !empty($_POST['related_customer']) ? (int)$_POST['related_customer'] : null;

        if (empty($title) || empty($due_date)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Title and Due Date are required.']);
            exit();
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO tasks (tenant_id, title, description, priority, due_date, due_time, assigned_user, related_lead, related_customer, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $tenant_id, $title, $description, $priority, $due_date, $due_time, $assigned_user, $related_lead, $related_customer, $user_id
            ]);
            $task_id = $pdo->lastInsertId();

            // Generate notification for assignee if different from creator
            if ($assigned_user != $user_id) {
                $msg = "You have been assigned a new task: " . substr($title, 0, 50);
                $stmtNotify = $pdo->prepare("INSERT INTO notifications (tenant_id, user_id, title, message) VALUES (?, ?, 'New Task Assigned', ?)");
                $stmtNotify->execute([$tenant_id, $assigned_user, $msg]);
            }

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Task created successfully.', 'data' => ['id' => $task_id]]);
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("Task Create Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'An error occurred while creating the task.']);
        }

    } elseif ($action === 'update_status' && isset($_POST['id'])) {
        requirePermission('tasks.edit');
        $task_id = (int)$_POST['id'];
        $new_status = $_POST['status'] ?? '';

        if (!in_array($new_status, ['Pending', 'In Progress', 'Completed', 'Cancelled', 'Overdue'])) {
             http_response_code(400);
             echo json_encode(['status' => 'error', 'message' => 'Invalid status.']);
             exit();
        }

        try {
            $pdo->beginTransaction();

            // Concurrency safe read
            $stmt = $pdo->prepare("SELECT id, status FROM tasks WHERE id = ? AND tenant_id = ? FOR UPDATE");
            $stmt->execute([$task_id, $tenant_id]);
            $task = $stmt->fetch();

            if (!$task) {
                 $pdo->rollBack();
                 http_response_code(404);
                 echo json_encode(['status' => 'error', 'message' => 'Task not found.']);
                 exit();
            }

            if ($task['status'] === $new_status) {
                 $pdo->rollBack();
                 echo json_encode(['status' => 'success', 'message' => 'Task status unchanged.']);
                 exit();
            }

            $completion_date = ($new_status === 'Completed') ? date('Y-m-d H:i:s') : null;

            $stmt = $pdo->prepare("UPDATE tasks SET status = ?, completion_date = ? WHERE id = ?");
            $stmt->execute([$new_status, $completion_date, $task_id]);

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Task status updated.']);

        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Concurrency conflict or server error.']);
        }

    } elseif ($action === 'delete' && isset($_POST['id'])) {
        requirePermission('tasks.delete');
        $task_id = (int)$_POST['id'];

        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$task_id, $tenant_id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Task deleted.']);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Task not found.']);
        }
    } else {
         http_response_code(400);
         echo json_encode(['status' => 'error', 'message' => 'Invalid action for POST method']);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}
