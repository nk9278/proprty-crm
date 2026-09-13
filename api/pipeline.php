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
    requirePermission('pipeline.view');

    if ($action === 'stages') {
        $stmt = $pdo->prepare("SELECT id, name, sort_order, is_system FROM pipeline_stages WHERE tenant_id = ? ORDER BY sort_order ASC");
        $stmt->execute([$tenant_id]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
    } elseif ($action === 'board') {
        $stmt = $pdo->prepare("SELECT id, name, sort_order FROM pipeline_stages WHERE tenant_id = ? ORDER BY sort_order ASC");
        $stmt->execute([$tenant_id]);
        $stages = $stmt->fetchAll();

        $sqlLeads = "SELECT l.id, l.name, l.mobile, l.pipeline_stage_id, l.lead_temperature, l.lead_score,
                     u.name as assigned_to_name
                     FROM leads l
                     LEFT JOIN users u ON l.assigned_to = u.id
                     WHERE l.tenant_id = ? AND l.deleted_at IS NULL
                     ORDER BY l.updated_at DESC";
        $stmtLeads = $pdo->prepare($sqlLeads);
        $stmtLeads->execute([$tenant_id]);
        $leads = $stmtLeads->fetchAll();

        $board = [];
        foreach ($stages as $s) {
            $board[$s['id']] = [
                'id' => $s['id'],
                'name' => $s['name'],
                'sort_order' => $s['sort_order'],
                'leads' => []
            ];
        }

        $firstStageId = $stages[0]['id'] ?? null;

        foreach ($leads as $l) {
            $stage_id = $l['pipeline_stage_id'];
            if (!$stage_id && $firstStageId) {
                $stage_id = $firstStageId;
            }
            if (isset($board[$stage_id])) {
                $board[$stage_id]['leads'][] = $l;
            }
        }

        echo json_encode(['status' => 'success', 'data' => array_values($board)]);
    }
} elseif ($method === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    if ($action === 'move_lead') {
        requirePermission('leads.edit'); // Use leads.edit since it manipulates leads data

        $lead_id = $_POST['lead_id'] ?? '';
        $stage_id = $_POST['stage_id'] ?? '';

        if (!$lead_id || !$stage_id) {
             http_response_code(400);
             echo json_encode(['status' => 'error', 'message' => 'Missing lead_id or stage_id.']);
             die();
        }

        $stmtLead = $pdo->prepare("SELECT id FROM leads WHERE id = ? AND tenant_id = ?");
        $stmtLead->execute([$lead_id, $tenant_id]);
        if (!$stmtLead->fetch()) {
             http_response_code(404);
             echo json_encode(['status' => 'error', 'message' => 'Lead not found.']);
             die();
        }

        $stmtStage = $pdo->prepare("SELECT id FROM pipeline_stages WHERE id = ? AND tenant_id = ?");
        $stmtStage->execute([$stage_id, $tenant_id]);
        if (!$stmtStage->fetch()) {
             http_response_code(404);
             echo json_encode(['status' => 'error', 'message' => 'Invalid Pipeline Stage.']);
             die();
        }

        $stmtUpdate = $pdo->prepare("UPDATE leads SET pipeline_stage_id = ? WHERE id = ? AND tenant_id = ?");
        $stmtUpdate->execute([$stage_id, $lead_id, $tenant_id]);

        // Audit log
        $stmtAudit = $pdo->prepare("INSERT INTO audit_logs (tenant_id, user_id, action, entity, entity_id, ip_address) VALUES (?, ?, 'move_stage', 'leads', ?, ?)");
        $stmtAudit->execute([$tenant_id, $user_id, $lead_id, $_SERVER['REMOTE_ADDR'] ?? '']);

        echo json_encode(['status' => 'success', 'message' => 'Lead moved successfully.']);
    }
}
