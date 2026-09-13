<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$tenant_id = current_tenant_id();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Both roles that can view leads or customers can perform matching. We check broadly.
requirePermission('properties.view');

$pdo = getDB();

if ($method === 'GET') {
    if ($action === 'match') {
        $budget_min = !empty($_GET['budget_min']) ? (float)$_GET['budget_min'] : null;
        $budget_max = !empty($_GET['budget_max']) ? (float)$_GET['budget_max'] : null;
        $city = !empty($_GET['city']) ? trim($_GET['city']) : null;
        $category_id = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;
        $type_id = !empty($_GET['type_id']) ? (int)$_GET['type_id'] : null;
        $bhk_id = !empty($_GET['bhk_id']) ? (int)$_GET['bhk_id'] : null;

        $sql = "
            SELECT p.id, p.name, p.city, p.base_price, p.status, c.name as category, t.name as type, pr.name as project_name
            FROM properties p
            LEFT JOIN property_categories c ON p.category_id = c.id
            LEFT JOIN property_types t ON p.type_id = t.id
            LEFT JOIN projects pr ON p.project_id = pr.id
            WHERE p.tenant_id = ? AND p.deleted_at IS NULL AND p.status = 'Available'
        ";

        $params = [$tenant_id];

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $properties = $stmt->fetchAll();

        // Calculate Dynamic Match Score natively in PHP to allow dynamic weighting without massive DB complexity
        $results = [];
        foreach ($properties as $prop) {
            $score = 0;
            $max_score = 0;

            if ($budget_min || $budget_max) {
                $max_score += 40;
                $prop_price = (float)$prop['base_price'];
                if ($prop_price > 0) {
                    if ($budget_min && $budget_max) {
                        if ($prop_price >= $budget_min && $prop_price <= $budget_max) $score += 40;
                    } elseif ($budget_min && $prop_price >= $budget_min) {
                        $score += 40;
                    } elseif ($budget_max && $prop_price <= $budget_max) {
                        $score += 40;
                    }
                }
            }

            if ($city) {
                $max_score += 30;
                if (strtolower($prop['city']) === strtolower($city)) $score += 30;
            }

            if ($category_id) {
                $max_score += 20;
                if ($prop['category_id'] == $category_id) $score += 20;
            }

            if ($type_id) {
                $max_score += 20;
                if ($prop['type_id'] == $type_id) $score += 20;
            }

            if ($bhk_id) {
                $max_score += 10;
                if ($prop['bhk_id'] == $bhk_id) $score += 10;
            }

            // Normalize score percentage
            $final_score = $max_score > 0 ? round(($score / $max_score) * 100) : 0;

            // Allow all available properties if no strict criteria were passed
            if ($max_score == 0) {
                $final_score = 100;
            }

            $prop['match_score'] = $final_score;
            $results[] = $prop;
        }

        // Sort descending by score
        usort($results, function($a, $b) {
            return $b['match_score'] <=> $a['match_score'];
        });

        echo json_encode(['status' => 'success', 'data' => $results]);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid action for GET method']);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}
