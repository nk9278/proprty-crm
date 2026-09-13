<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';

requireLogin();
requirePermission('api_keys.manage');

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token); ?>">
    <title>Zopa CRM - API Keys</title>
    <style>
        body { font-family: sans-serif; background-color: #F1EDED; color: #1E1C1C; margin: 0; display: flex; flex-direction: column; height: 100vh; }
        .header { background-color: white; padding: 1rem 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 1.5rem; }
        .container { flex: 1; padding: 2rem; overflow-y: auto; max-width: 1000px; margin: 0 auto; width: 100%; box-sizing: border-box; }
        .card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
        .card-header { font-size: 1.25rem; font-weight: bold; margin-bottom: 1rem; border-bottom: 2px solid #F1EDED; padding-bottom: 0.5rem; }

        .btn { padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.9rem; display: inline-block;}
        .btn-primary { background-color: #CF1F3C; color: white; }
        .btn-danger { background-color: #991b1b; color: white; }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #eee; }
        th { background-color: #f9f9f9; }

        .badge { padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem; background: #e2e8f0; }
        .badge.Active { background: #dcfce7; color: #166534; }
        .badge.Revoked { background: #fee2e2; color: #991b1b; }

        .form-group { margin-bottom: 1rem; }
        input[type="text"] { padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box; }
    </style>
</head>
<body>

<div class="header">
    <h1>API Keys & Integrations</h1>
    <div class="header-nav">
        <a href="/dashboard.php" class="btn btn-primary">Dashboard</a>
    </div>
</div>

<div class="container">
    <div class="card">
        <div class="card-header">Generate New Key</div>
        <form id="createKeyForm">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <div class="form-group">
                <label>Key Name / Integration Label *</label>
                <input type="text" name="name" placeholder="e.g. Website Lead Form" required>
            </div>
            <button type="submit" class="btn btn-primary">Generate API Key</button>
        </form>
        <div id="newKeyDisplay" style="display:none; margin-top:1rem; padding:1rem; background:#dcfce7; border:1px solid #166534; border-radius:4px;">
            <strong>Success! Store this Secret Key securely. It will not be shown again.</strong><br><br>
            <div>API Key: <code id="dispKey" style="background:#fff; padding:0.2rem 0.5rem; font-size:1.1rem;"></code></div>
            <div style="margin-top:0.5rem;">API Secret: <code id="dispSecret" style="background:#fff; padding:0.2rem 0.5rem; font-size:1.1rem;"></code></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Active Keys</div>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>API Key</th>
                    <th>Status</th>
                    <th>Last Used</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="keysList">
                <tr><td colspan="5" style="text-align:center;">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

function escapeHTML(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function loadKeys() {
    fetch('/api/api_keys.php?action=list')
        .then(r => r.json())
        .then(res => {
            const tbody = document.getElementById('keysList');
            tbody.innerHTML = '';
            if (res.status === 'success' && res.data.length > 0) {
                res.data.forEach(k => {
                    let btn = k.status === 'Active' ? `<button class="btn btn-danger" onclick="revokeKey(${k.id})">Revoke</button>` : '';
                    tbody.innerHTML += `
                        <tr>
                            <td><strong>${escapeHTML(k.name)}</strong></td>
                            <td><code>${escapeHTML(k.api_key)}</code></td>
                            <td><span class="badge ${escapeHTML(k.status)}">${escapeHTML(k.status)}</span></td>
                            <td>${escapeHTML(k.last_used_at) || 'Never'}</td>
                            <td>${btn}</td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No API keys found.</td></tr>';
            }
        });
}

function revokeKey(id) {
    if (!confirm('Are you sure you want to revoke this key? Any integration using it will immediately fail.')) return;
    const fd = new FormData();
    fd.append('action', 'revoke');
    fd.append('id', id);
    fd.append('csrf_token', csrfToken);

    fetch('/api/api_keys.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if(res.status === 'success') loadKeys();
            else alert(res.message);
        });
}

document.addEventListener('DOMContentLoaded', () => {
    loadKeys();

    document.getElementById('createKeyForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        fetch('/api/api_keys.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    this.reset();
                    document.getElementById('newKeyDisplay').style.display = 'block';
                    document.getElementById('dispKey').textContent = res.data.api_key;
                    document.getElementById('dispSecret').textContent = res.data.api_secret;
                    loadKeys();
                } else {
                    alert('Error: ' + res.message);
                }
            });
    });
});
</script>

</body>
</html>
