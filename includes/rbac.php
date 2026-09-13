<?php
require_once __DIR__ . '/session.php';

function current_user_role() {
    requireLogin();
    return $_SESSION['role'] ?? 'Agent';
}

function hasPermission($required_role) {
    $current_role = current_user_role();

    // Super Admin can do everything
    if ($current_role === 'Super Admin') {
        return true;
    }

    // Tenant Owner implies full permissions for their tenant
    if ($current_role === 'Tenant Owner') {
        return true;
    }

    // Exact match
    return $current_role === $required_role;
}

function requirePermission($required_role) {
    if (!hasPermission($required_role)) {
        http_response_code(403);
        die("403 Forbidden: You do not have permission to access this resource.");
    }
}