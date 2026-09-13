<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';

requireLogin();
requirePermission('site_visits.view');

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token); ?>">
    <title>Zopa CRM - Site Visits</title>
    <style>
        body { font-family: sans-serif; background-color: #F1EDED; color: #1E1C1C; margin: 0; display: flex; flex-direction: column; height: 100vh; }
        .header { background-color: white; padding: 1rem 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 1.5rem; }
        .container { flex: 1; padding: 2rem; overflow-y: auto; }
        .card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 1rem; }

        .controls { display: flex; gap: 1rem; margin-bottom: 1rem; align-items: center; flex-wrap: wrap; }
        select, input[type="text"] { padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; }
        .btn { padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.9rem; }
        .btn-primary { background-color: #CF1F3C; color: white; }
        .btn-primary:hover { background-color: #b01a33; }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #eee; }
        th { background-color: #f9f9f9; }

        .badge { padding: 0.2rem 0.5rem; border-radius: 12px; font-size: 0.8rem; background: #eee; }
        .badge.Completed { background: #d4edda; color: #155724; }
        .badge.Scheduled { background: #cce5ff; color: #004085; }
        .badge.Confirmed { background: #d1ecf1; color: #0c5460; }
        .badge.No-Show { background: #f8d7da; color: #721c24; }

        @media (max-width: 768px) {
            .hide-mobile { display: none; }
            table, thead, tbody, th, td, tr { display: block; }
            thead tr { position: absolute; top: -9999px; left: -9999px; }
            tr { margin-bottom: 1rem; border: 1px solid #ccc; border-radius: 8px; padding: 1rem; background: white; }
            td { border: none; position: relative; padding-left: 50%; text-align: right; }
            td:before { position: absolute; left: 1rem; width: 45%; padding-right: 10px; white-space: nowrap; font-weight: bold; text-align: left; content: attr(data-label); }
        }
    </style>
</head>
<body>

<div class="header">
    <h1>Site Visits</h1>
    <div class="header-nav">
        <a href="/dashboard.php" class="btn btn-primary" style="margin-right: 1rem;">Back to Dashboard</a>
    </div>
</div>

<div class="container">
    <div class="card">
        <div class="controls">
            <select id="statusFilter" onchange="loadVisits()">
                <option value="">All Statuses</option>
                <option value="Scheduled">Scheduled</option>
                <option value="Confirmed">Confirmed</option>
                <option value="Completed">Completed</option>
                <option value="No-Show">No-Show</option>
                <option value="Cancelled">Cancelled</option>
            </select>
        </div>

        <table id="visitsTable">
            <thead>
                <tr>
                    <th>Date / Time</th>
                    <th>Lead / Customer</th>
                    <th>Property</th>
                    <th>Salesperson</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr><td colspan="6">Loading...</td></tr>
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

function loadVisits() {
    const status = document.getElementById('statusFilter').value;
    let url = '/api/site-visits.php?action=list';
    if(status) url += '&status=' + encodeURIComponent(status);

    fetch(url)
        .then(response => response.json())
        .then(res => {
            const tbody = document.querySelector('#visitsTable tbody');
            tbody.innerHTML = '';

            if (res.status === 'success' && res.data.length > 0) {
                res.data.forEach(v => {
                    const name = escapeHTML(v.lead_name || v.customer_name || 'N/A');
                    const prop = escapeHTML(v.property_title || v.project_name || 'N/A');

                    let actions = `
                        <button onclick="updateStatus(${v.id}, 'Confirmed')">Confirm</button>
                        <button onclick="updateStatus(${v.id}, 'Completed')">Complete</button>
                        <button onclick="updateStatus(${v.id}, 'No-Show')">No-Show</button>
                    `;

                    if (v.status === 'Completed' || v.status === 'Cancelled') actions = '';

                    tbody.innerHTML += `
                        <tr>
                            <td data-label="Date / Time">${escapeHTML(v.scheduled_date)} ${escapeHTML(v.scheduled_time)}</td>
                            <td data-label="Lead / Customer">${name}</td>
                            <td data-label="Property">${prop}</td>
                            <td data-label="Salesperson">${escapeHTML(v.salesperson_name)}</td>
                            <td data-label="Status"><span class="badge ${escapeHTML(v.status)}">${escapeHTML(v.status)}</span></td>
                            <td data-label="Actions">${actions}</td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="6">No site visits found.</td></tr>';
            }
        });
}

function updateStatus(id, newStatus) {
    if (!confirm('Change status to ' + newStatus + '?')) return;

    const fd = new FormData();
    fd.append('action', 'update_status');
    fd.append('id', id);
    fd.append('status', newStatus);
    fd.append('csrf_token', csrfToken);

    fetch('/api/site-visits.php', {
        method: 'POST',
        body: fd
    }).then(r => r.json()).then(res => {
        if(res.status === 'success') {
            loadVisits();
        } else {
            alert(res.message);
        }
    });
}

document.addEventListener('DOMContentLoaded', loadVisits);
</script>

</body>
</html>
