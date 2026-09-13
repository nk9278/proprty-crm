<?php
require_once __DIR__ . '/session.php';

// Tenant resolution from secure server-side context
function current_tenant_id() {
    requireLogin();

    if (!isset($_SESSION['tenant_id']) || empty($_SESSION['tenant_id'])) {
        // If logged in but no tenant, something is fundamentally wrong
        die("Unauthorized access: Tenant context lost.");
    }

    return (int)$_SESSION['tenant_id'];
}