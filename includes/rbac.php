<?php
require_once __DIR__ . '/session.php';

function current_user_role_id() {
    requireLogin();
    // Assuming role_id is stored in session during login instead of role name.
    // If not, we will query it here based on user_id, but it's better to store it.
    // Let's query it if it's missing for safety.
    if (!isset($_SESSION['role_id'])) {
        require_once __DIR__ . '/../config/database.php';
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT r.id, r.name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $role = $stmt->fetch();
        if ($role) {
            $_SESSION['role_id'] = $role['id'];
            $_SESSION['role_name'] = $role['name'];
        } else {
            die("User role not found.");
        }
    }
    return $_SESSION['role_id'];
}

function current_user_role_name() {
    // Calling current_user_role_id() ensures role_name is populated
    current_user_role_id();
    return $_SESSION['role_name'] ?? 'Agent';
}

function hasPermission($permission_name) {
    $role_name = current_user_role_name();

    // Super Admin can do everything globally
    if ($role_name === 'Super Admin') {
        return true;
    }

    // Tenant Owner implies full permissions within their tenant
    if ($role_name === 'Tenant Owner') {
        return true;
    }

    $role_id = current_user_role_id();

    // Dynamically check against the database
    require_once __DIR__ . '/../config/database.php';
    $pdo = getDB();

    $stmt = $pdo->prepare("
        SELECT 1
        FROM role_permissions rp
        JOIN permissions p ON rp.permission_id = p.id
        WHERE rp.role_id = ? AND p.name = ?
        LIMIT 1
    ");
    $stmt->execute([$role_id, $permission_name]);

    return (bool) $stmt->fetch();
}

function requirePermission($permission_name) {
    if (!hasPermission($permission_name)) {
        http_response_code(403);
        die("403 Forbidden: You do not have permission ('$permission_name') to access this resource.");
    }
}