<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';

requireLogin();
requirePermission('whatsapp.manage');

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token); ?>">
    <title>Zopa CRM - WhatsApp Settings</title>
    <style>
        body { font-family: sans-serif; background-color: #F1EDED; color: #1E1C1C; margin: 0; display: flex; flex-direction: column; height: 100vh; }
        .header { background-color: white; padding: 1rem 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 1.5rem; }
        .container { flex: 1; padding: 2rem; overflow-y: auto; max-width: 800px; margin: 0 auto; width: 100%; box-sizing: border-box; }
        .card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
        .card-header { font-size: 1.25rem; font-weight: bold; margin-bottom: 1rem; border-bottom: 2px solid #F1EDED; padding-bottom: 0.5rem; }

        .btn { padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.9rem; display: inline-block;}
        .btn-primary { background-color: #CF1F3C; color: white; }
    </style>
</head>
<body>

<div class="header">
    <h1>WhatsApp Integration</h1>
    <div class="header-nav">
        <a href="/dashboard.php" class="btn btn-primary">Dashboard</a>
    </div>
</div>

<div class="container">
    <div class="card">
        <div class="card-header">Connection Status</div>
        <p style="color: #64748b;">No active WhatsApp Provider is configured. Please provide your API credentials to activate outbound messaging.</p>
        <button class="btn btn-primary" onclick="alert('Provider connection flows are disabled in this environment.')">Connect Provider</button>
    </div>

    <div class="card">
        <div class="card-header">Message Templates</div>
        <ul id="templateList" style="list-style:none; padding:0; margin:0;">
            <li style="padding: 1rem; border: 1px solid #e2e8f0; border-radius:4px; margin-bottom: 0.5rem; background: #f8fafc;">
                <strong>Greeting</strong><br>
                <small>Hello {{customer_name}}, thank you for your interest in {{project_name}}.</small>
            </li>
            <li style="padding: 1rem; border: 1px solid #e2e8f0; border-radius:4px; margin-bottom: 0.5rem; background: #f8fafc;">
                <strong>Site Visit Confirmation</strong><br>
                <small>Your site visit is confirmed for {{visit_date}} at {{visit_time}}.</small>
            </li>
        </ul>
    </div>
</div>

</body>
</html>
