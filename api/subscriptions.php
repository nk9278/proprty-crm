<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn()) {
    http_response_code(401);
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

$tenant_id = current_tenant_id();
$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$pdo = getDB();

if ($method === 'GET') {
    if ($action === 'plans') {
        $stmt = $pdo->prepare("SELECT * FROM plans WHERE status = 'Active' ORDER BY display_order ASC, price ASC");
        $stmt->execute();
        $plans = $stmt->fetchAll();

        // Append features
        foreach ($plans as &$p) {
            $fStmt = $pdo->prepare("SELECT feature_code, feature_value FROM plan_features WHERE plan_id = ?");
            $fStmt->execute([$p['id']]);
            $p['features'] = $fStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        }

        echo json_encode(['status' => 'success', 'data' => $plans]);
    } elseif ($action === 'my_subscription') {
        $stmt = $pdo->prepare("SELECT s.*, p.name as plan_name, p.price as plan_price
                               FROM subscriptions s
                               JOIN plans p ON s.plan_id = p.id
                               WHERE s.tenant_id = ?");
        $stmt->execute([$tenant_id]);
        $sub = $stmt->fetch();

        if (!$sub) {
            die(json_encode(['status' => 'success', 'data' => ['status' => 'Unconfigured']]));
        }

        $stmtInv = $pdo->prepare("SELECT * FROM invoices WHERE subscription_id = ? AND tenant_id = ? ORDER BY issue_date DESC LIMIT 5");
        $stmtInv->execute([$sub['id'], $tenant_id]);
        $sub['recent_invoices'] = $stmtInv->fetchAll();

        echo json_encode(['status' => 'success', 'data' => $sub]);
    }
} elseif ($method === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    if ($action === 'subscribe') {
        $stmtRole = $pdo->prepare("SELECT r.name FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = ?");
        $stmtRole->execute([$user_id]);
        $roles = $stmtRole->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('Tenant Owner', $roles) && !hasPermission('subscriptions.manage')) {
             http_response_code(403);
             die(json_encode(['status' => 'error', 'message' => 'Only Tenant Owners can modify subscription plans.']));
        }

        $plan_id = $_POST['plan_id'] ?? '';

        if (!$plan_id) {
             http_response_code(400);
             die(json_encode(['status' => 'error', 'message' => 'Plan ID required.']));
        }

        try {
            $pdo->beginTransaction();

            $stmtP = $pdo->prepare("SELECT * FROM plans WHERE id = ? AND status = 'Active'");
            $stmtP->execute([$plan_id]);
            $plan = $stmtP->fetch();

            if (!$plan) throw new \Exception("Invalid or inactive plan selected.");

            $stmtSub = $pdo->prepare("SELECT * FROM subscriptions WHERE tenant_id = ? FOR UPDATE");
            $stmtSub->execute([$tenant_id]);
            $existing = $stmtSub->fetch();

            $start = date('Y-m-d');
            $end = date('Y-m-d', strtotime('+1 month'));

            if ($existing) {
                if ($existing['plan_id'] == $plan_id && $existing['status'] == 'Active') {
                     throw new \Exception("You are already subscribed to this plan.");
                }
                $stmtU = $pdo->prepare("UPDATE subscriptions SET plan_id = ?, status = 'Active', start_date = ?, end_date = ? WHERE tenant_id = ?");
                $stmtU->execute([$plan_id, $start, $end, $tenant_id]);
                $sub_id = $existing['id'];
            } else {
                $stmtI = $pdo->prepare("INSERT INTO subscriptions (tenant_id, plan_id, status, start_date, end_date, billing_interval) VALUES (?, ?, 'Active', ?, ?, ?)");
                $stmtI->execute([$tenant_id, $plan_id, $start, $end, $plan['billing_interval']]);
                $sub_id = $pdo->lastInsertId();
            }

            if ($plan['price'] > 0) {
                $inv_no = "INV-" . date('ym') . "-" . rand(1000,9999);
                $stmtInv = $pdo->prepare("INSERT INTO invoices (tenant_id, subscription_id, invoice_number, amount, final_amount, status, issue_date, due_date) VALUES (?, ?, ?, ?, ?, 'Open', ?, ?)");
                $stmtInv->execute([$tenant_id, $sub_id, $inv_no, $plan['price'], $plan['price'], $start, $start]);
            }

            $stmtAudit = $pdo->prepare("INSERT INTO audit_logs (tenant_id, user_id, action, entity, entity_id, ip_address) VALUES (?, ?, 'subscribe', 'subscriptions', ?, ?)");
            $stmtAudit->execute([$tenant_id, $user_id, $sub_id, $_SERVER['REMOTE_ADDR'] ?? '']);

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Subscription updated successfully. Please pay the generated invoice if applicable.']);

        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}
