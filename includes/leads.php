<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/tenant.php';
require_once __DIR__ . '/../config/database.php';

function logLeadActivity($lead_id, $action, $old_value = null, $new_value = null) {
    $pdo = getDB();
    $user_id = $_SESSION['user_id'] ?? null;

    $stmt = $pdo->prepare("INSERT INTO lead_activities (lead_id, user_id, action, old_value, new_value) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $lead_id,
        $user_id,
        $action,
        $old_value ? json_encode($old_value) : null,
        $new_value ? json_encode($new_value) : null
    ]);

    // SLA Met hook: If an activity represents a response, clear pending SLA
    $response_actions = ['Added Follow-up', 'Logged Call', 'Sent WhatsApp', 'Updated Lead Details'];
    if (in_array($action, $response_actions) && $user_id) {
        // Mark pending SLAs as Met
        $stmt = $pdo->prepare("
            UPDATE lead_sla
            SET status = 'Met', first_response_time = CURRENT_TIMESTAMP
            WHERE lead_id = ? AND status = 'Pending'
        ");
        $stmt->execute([$lead_id]);
    }
}

function checkDuplicateLead($mobile, $email = null) {
    $tenant_id = current_tenant_id();
    $pdo = getDB();

    $query = "SELECT id, name FROM leads WHERE tenant_id = ? AND mobile = ?";
    $params = [$tenant_id, $mobile];

    if (!empty($email)) {
        $query .= " OR (tenant_id = ? AND email = ?)";
        $params[] = $tenant_id;
        $params[] = $email;
    }

    $query .= " LIMIT 1";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    return $stmt->fetch(); // Returns the duplicate lead record or false
}

function assignLead($lead_id, $new_user_id, $assignment_method = 'Manual') {
    $tenant_id = current_tenant_id();
    $pdo = getDB();

    try {
        // Validate lead belongs to tenant and lock it
        $stmt = $pdo->prepare("SELECT id, assigned_to FROM leads WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$lead_id, $tenant_id]);
        $lead = $stmt->fetch();

        if (!$lead) {
            throw new Exception("Lead not found or unauthorized.");
        }

        $previous_user_id = $lead['assigned_to'];
        $current_user = $_SESSION['user_id'] ?? null;

        if ($previous_user_id == $new_user_id) {
            return true; // Already assigned
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT id, assigned_to FROM leads WHERE id = ? AND tenant_id = ? FOR UPDATE");
        $stmt->execute([$lead_id, $tenant_id]);

        // Update Lead record
        $stmt = $pdo->prepare("UPDATE leads SET assigned_to = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$new_user_id, $lead_id]);

        // Add to assignment history
        $stmt = $pdo->prepare("INSERT INTO lead_assignment_history (lead_id, previous_user_id, new_user_id, reassigned_by, assignment_method) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$lead_id, $previous_user_id, $new_user_id, $current_user, $assignment_method]);

        // SLA initialization
        // Fetch Tenant SLA configuration
        $stmt = $pdo->prepare("SELECT setting_value FROM tenant_settings WHERE tenant_id = ? AND setting_key = 'sla_minutes'");
        $stmt->execute([$tenant_id]);
        $sla_setting = $stmt->fetchColumn();
        $sla_minutes = $sla_setting ? (int)$sla_setting : 0;

        if ($sla_minutes > 0) {
            // Cancel any pending SLAs for this lead
            $stmt = $pdo->prepare("UPDATE lead_sla SET status = 'Breached' WHERE lead_id = ? AND status = 'Pending'");
            $stmt->execute([$lead_id]);

            // Insert new SLA timer
            $stmt = $pdo->prepare("
                INSERT INTO lead_sla (lead_id, user_id, assigned_time, sla_deadline)
                VALUES (?, ?, CURRENT_TIMESTAMP, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL ? MINUTE))
            ");
            $stmt->execute([$lead_id, $new_user_id, $sla_minutes]);
        }

        // Log Activity
        logLeadActivity($lead_id, 'Assigned Lead', ['assigned_to' => $previous_user_id], ['assigned_to' => $new_user_id]);

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Assign Lead Error: " . $e->getMessage());
        return false;
    }
}
