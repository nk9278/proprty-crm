<?php
// NOTE: This endpoint accepts external POST data without standard CRM auth sessions.
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Method Not Allowed']));
}

$provider = $_GET['provider'] ?? 'Unknown';
$tenant_uuid_or_key = $_GET['tenant_key'] ?? ''; // Mock logic for routing Webhooks to exact Tenants safely.

// Get raw JSON payload
$raw_payload = file_get_contents('php://input');
$payload = json_decode($raw_payload, true);

if (!$payload) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid JSON payload']));
}

$pdo = getDB();

// Idempotency / Deduplication Check
$external_id = $payload['event_id'] ?? $payload['id'] ?? null;

if ($external_id) {
    $stmtIdem = $pdo->prepare("SELECT id FROM webhook_logs WHERE external_event_id = ? AND provider = ? LIMIT 1");
    $stmtIdem->execute([$external_id, $provider]);
    if ($stmtIdem->fetch()) {
        die(json_encode(['status' => 'ignored', 'message' => 'Duplicate webhook event.']));
    }
}

// Log the event initially as Pending
$stmtLog = $pdo->prepare("INSERT INTO webhook_logs (provider, external_event_id, payload, processing_status) VALUES (?, ?, ?, 'Pending')");
$stmtLog->execute([$provider, $external_id, $raw_payload]);
$log_id = $pdo->lastInsertId();

// Note: For Zopa CRM, real tenant matching would lookup $tenant_uuid_or_key mapped in database.
// For now, we mock processing failures safely without inventing fake leads.
$stmtUpdate = $pdo->prepare("UPDATE webhook_logs SET processing_status = 'Failed', error_message = 'Unconfigured Provider Routing' WHERE id = ?");
$stmtUpdate->execute([$log_id]);

echo json_encode(['status' => 'success', 'message' => 'Webhook received and logged securely.']);
