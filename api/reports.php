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
    if ($action === 'dashboard_kpis') {
        // High-level KPI aggregations for Dashboard/Reports overview

        $kpis = [
            'total_leads' => 0,
            'total_bookings' => 0,
            'total_revenue' => 0,
            'total_collected' => 0
        ];

        // Leads Count
        $stmtL = $pdo->prepare("SELECT COUNT(id) as c FROM leads WHERE tenant_id = ? AND deleted_at IS NULL");
        $stmtL->execute([$tenant_id]);
        $kpis['total_leads'] = $stmtL->fetchColumn() ?: 0;

        // Bookings Count (excluding cancelled/drafts)
        $stmtB = $pdo->prepare("SELECT COUNT(id) as c FROM bookings WHERE tenant_id = ? AND status NOT IN ('Draft', 'Cancelled')");
        $stmtB->execute([$tenant_id]);
        $kpis['total_bookings'] = $stmtB->fetchColumn() ?: 0;

        // Revenue (sum of final amounts) & Collected
        $stmtRev = $pdo->prepare("
            SELECT SUM(cs.final_amount) as rev, SUM(cs.amount_received) as col
            FROM booking_cost_sheets cs
            JOIN bookings b ON cs.booking_id = b.id
            WHERE b.tenant_id = ? AND b.status NOT IN ('Draft', 'Cancelled')
        ");
        $stmtRev->execute([$tenant_id]);
        $revRow = $stmtRev->fetch();
        if ($revRow) {
            $kpis['total_revenue'] = $revRow['rev'] ?: 0;
            $kpis['total_collected'] = $revRow['col'] ?: 0;
        }

        echo json_encode(['status' => 'success', 'data' => $kpis]);

    } elseif ($action === 'sales_performance') {
        // Report aggregating sales rep performance metrics
        requirePermission('reports.view');

        $sql = "SELECT sp.name as salesperson,
                COUNT(DISTINCT l.id) as assigned_leads,
                COUNT(DISTINCT b.id) as total_bookings,
                SUM(cs.final_amount) as total_revenue
                FROM users sp
                LEFT JOIN leads l ON l.assigned_to = sp.id AND l.tenant_id = sp.tenant_id
                LEFT JOIN bookings b ON b.salesperson_id = sp.id AND b.tenant_id = sp.tenant_id AND b.status NOT IN ('Draft', 'Cancelled')
                LEFT JOIN booking_cost_sheets cs ON cs.booking_id = b.id
                WHERE sp.tenant_id = ? AND sp.role_id IS NOT NULL
                GROUP BY sp.id
                ORDER BY total_revenue DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tenant_id]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
    }
}
