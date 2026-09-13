<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';

requireLogin();
requirePermission('Tenant Owner');

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zopa CRM - Tenant Settings</title>
    <style>
        body { font-family: sans-serif; background-color: #F1EDED; color: #1E1C1C; margin: 0; }
        .header { background-color: white; padding: 1rem 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 1.5rem; }
        .header-actions a { color: #666; text-decoration: none; padding: 0.5rem; font-weight: bold; }
        .header-actions a:hover { color: #CF1F3C; }

        .container { max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 2rem; }
        .card-header { font-size: 1.2rem; font-weight: bold; margin-bottom: 1.5rem; border-bottom: 1px solid #eee; padding-bottom: 0.5rem;}

        .form-group { margin-bottom: 1.5rem; display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 0.5rem; font-weight: bold; font-size: 0.9rem; }
        .form-group select, .form-group input { padding: 0.75rem; border: 1px solid #ccc; border-radius: 4px; font-family: inherit; }

        .btn-submit { background-color: #CF1F3C; color: white; border: none; padding: 0.75rem 2rem; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 1rem; width: 100%;}
        .btn-submit:hover { background-color: #b01a33; }

        #message { margin-bottom: 1rem; padding: 1rem; border-radius: 4px; display: none; text-align: center; font-weight: bold;}
        .success { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .error { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        #loading { text-align: center; padding: 2rem; color: #666; }
    </style>
</head>
<body>

<div class="header">
    <h1>Tenant Settings</h1>
    <div class="header-actions">
        <a href="/dashboard.php">&larr; Back to Dashboard</a>
    </div>
</div>

<div class="container">
    <div id="message"></div>
    <div id="loading">Loading settings...</div>

    <div id="settingsContainer" style="display:none;">
        <div class="card">
            <div class="card-header">Lead Assignment Rules</div>
            <form id="assignmentForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="setting_key" value="lead_assignment_rule">

                <div class="form-group">
                    <label for="assignment_rule">Default Assignment Method</label>
                    <select id="assignment_rule" name="setting_value">
                        <option value="Manual">Manual</option>
                        <option value="Round Robin">Round Robin</option>
                        <option value="Least Loaded">Least Loaded</option>
                        <option value="Random">Random</option>
                    </select>
                </div>
                <button type="submit" class="btn-submit">Save Assignment Rule</button>
            </form>
        </div>

        <div class="card">
            <div class="card-header">SLA Configuration (First Response)</div>
            <form id="slaForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="setting_key" value="sla_minutes">

                <div class="form-group">
                    <label for="sla_minutes">SLA Deadline (Minutes)</label>
                    <input type="number" id="sla_minutes" name="setting_value" min="0" placeholder="e.g. 15 (0 to disable)">
                </div>
                <button type="submit" class="btn-submit">Save SLA Config</button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    fetch('/api/settings.php?action=get_all')
        .then(r => r.json())
        .then(res => {
            document.getElementById('loading').style.display = 'none';
            document.getElementById('settingsContainer').style.display = 'block';

            if (res.status === 'success') {
                const settings = res.data;
                if (settings['lead_assignment_rule']) {
                    document.getElementById('assignment_rule').value = settings['lead_assignment_rule'];
                }
                if (settings['sla_minutes']) {
                    document.getElementById('sla_minutes').value = settings['sla_minutes'];
                }
            }
        });

    function saveSetting(e) {
        e.preventDefault();
        const form = e.target;
        const btn = form.querySelector('.btn-submit');
        const originalText = btn.textContent;
        const msg = document.getElementById('message');

        btn.textContent = 'Saving...';
        btn.disabled = true;
        msg.style.display = 'none';

        fetch('/api/settings.php?action=save', {
            method: 'POST',
            body: new FormData(form)
        }).then(r => r.json()).then(res => {
            msg.style.display = 'block';
            msg.className = res.status === 'success' ? 'success' : 'error';
            msg.textContent = res.message;
            btn.textContent = originalText;
            btn.disabled = false;
        }).catch(() => {
            msg.style.display = 'block';
            msg.className = 'error';
            msg.textContent = 'Network error.';
            btn.textContent = originalText;
            btn.disabled = false;
        });
    }

    document.getElementById('assignmentForm').addEventListener('submit', saveSetting);
    document.getElementById('slaForm').addEventListener('submit', saveSetting);
});
</script>
</body>
</html>