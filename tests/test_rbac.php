<?php
// We have to mock the session
$_SESSION = [];

// Temporarily redefine requireLogin for the CLI context
require_once __DIR__ . '/../includes/session.php';
function requireLogin() {
    // Override the redirect for CLI tests
    return true;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/rbac.php';

echo "Running RBAC Tests...\n\n";

$pdo = getDB();

// Setup test users
try {
    $pdo->beginTransaction();

    // 1. Get Roles
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'Super Admin'");
    $stmt->execute();
    $superAdminRoleId = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'Tenant Owner'");
    $stmt->execute();
    $tenantOwnerRoleId = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'Salesman'");
    $stmt->execute();
    $salesmanRoleId = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'Customer'");
    $stmt->execute();
    $customerRoleId = $stmt->fetchColumn();

    // 2. Create Dummy Tenant
    $stmt = $pdo->prepare("INSERT INTO tenants (name) VALUES ('Test Tenant')");
    $stmt->execute();
    $tenantId = $pdo->lastInsertId();

    // 3. Create Dummy Users
    $stmt = $pdo->prepare("INSERT INTO users (tenant_id, name, mobile, pin_hash, role_id) VALUES (?, 'Test Super', '1111111111', 'hash', ?)");
    $stmt->execute([$tenantId, $superAdminRoleId]);
    $superAdminUserId = $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO users (tenant_id, name, mobile, pin_hash, role_id) VALUES (?, 'Test Owner', '2222222222', 'hash', ?)");
    $stmt->execute([$tenantId, $tenantOwnerRoleId]);
    $tenantOwnerUserId = $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO users (tenant_id, name, mobile, pin_hash, role_id) VALUES (?, 'Test Sales', '3333333333', 'hash', ?)");
    $stmt->execute([$tenantId, $salesmanRoleId]);
    $salesmanUserId = $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO users (tenant_id, name, mobile, pin_hash, role_id) VALUES (?, 'Test Cust', '4444444444', 'hash', ?)");
    $stmt->execute([$tenantId, $customerRoleId]);
    $customerUserId = $pdo->lastInsertId();

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    die("Setup failed: " . $e->getMessage() . "\n");
}

function runTest($testName, $userId, $permissionName, $expectedResult) {
    // Reset session for the user
    $_SESSION['user_id'] = $userId;
    unset($_SESSION['role_id']);
    unset($_SESSION['role_name']);

    $result = hasPermission($permissionName);
    if ($result === $expectedResult) {
        echo "✅ PASS: $testName\n";
    } else {
        echo "❌ FAIL: $testName - Expected " . ($expectedResult ? 'true' : 'false') . " got " . ($result ? 'true' : 'false') . "\n";
    }
}

// Tests
runTest("Super Admin can view reports (Global bypass)", $superAdminUserId, "reports.view", true);
runTest("Super Admin can delete properties (Global bypass)", $superAdminUserId, "properties.delete", true);

runTest("Tenant Owner can view reports (Tenant bypass)", $tenantOwnerUserId, "reports.view", true);
runTest("Tenant Owner can assign leads (Tenant bypass)", $tenantOwnerUserId, "leads.assign", true);

// As seeded in seed.sql: Salesman has 'leads.view', 'leads.create', 'leads.edit', 'properties.view', 'bookings.view', 'payments.view'
runTest("Salesman can view leads", $salesmanUserId, "leads.view", true);
runTest("Salesman can create properties", $salesmanUserId, "properties.create", false); // Not granted
runTest("Salesman can delete properties", $salesmanUserId, "properties.delete", false);
runTest("Salesman can view bookings", $salesmanUserId, "bookings.view", true);
runTest("Salesman can pay commission", $salesmanUserId, "commission.pay", false);

runTest("Customer cannot view leads", $customerUserId, "leads.view", false);
runTest("Customer cannot view reports", $customerUserId, "reports.view", false);

// Cleanup
$pdo->exec("DELETE FROM tenants WHERE name = 'Test Tenant'");
echo "\nRBAC Tests Finished.\n";
