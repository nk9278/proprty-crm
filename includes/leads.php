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
        $pdo->beginTransaction();

        // Validate lead belongs to tenant and lock it
        $stmt = $pdo->prepare("SELECT id, assigned_to FROM leads WHERE id = ? AND tenant_id = ? FOR UPDATE");
        $stmt->execute([$lead_id, $tenant_id]);
        $lead = $stmt->fetch();

        if (!$lead) {
            $pdo->rollBack();
            throw new Exception("Lead not found or unauthorized.");
        }

        $previous_user_id = $lead['assigned_to'];
        $current_user = $_SESSION['user_id'] ?? null;

        if ($previous_user_id == $new_user_id) {
            $pdo->rollBack();
            return true; // Already assigned
        }

        // Update Lead record
        $stmt = $pdo->prepare("UPDATE leads SET assigned_to = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$new_user_id, $lead_id]);

        // Add to assignment history
        $stmt = $pdo->prepare("INSERT INTO lead_assignment_history (lead_id, previous_user_id, new_user_id, reassigned_by, assignment_method) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$lead_id, $previous_user_id, $new_user_id, $current_user, $assignment_method]);

        // Log Activity
        logLeadActivity($lead_id, 'Assigned Lead', ['assigned_to' => $previous_user_id], ['assigned_to' => $new_user_id]);

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Assign Lead Error: " . $e->getMessage());
        return false;
    }
}
