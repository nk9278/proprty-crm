<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';

requireLogin();
requirePermission('tasks.view');

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zopa CRM - Tasks</title>
    <style>
        body { font-family: sans-serif; background-color: #F1EDED; color: #1E1C1C; margin: 0; display: flex; flex-direction: column; height: 100vh; }
        .header { background-color: white; padding: 1rem 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 1.5rem; }
        .container { flex: 1; padding: 2rem; overflow-y: auto; }
        .card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }

        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { text-align: left; padding: 1rem; border-bottom: 1px solid #eee; }
        th { background-color: #f9f9f9; font-weight: 600; color: #666; }

        .badge { display: inline-block; padding: 0.25rem 0.5rem; border-radius: 12px; font-size: 0.8rem; font-weight: bold; }
        .badge.pending { background-color: #fef08a; color: #854d0e; }
        .badge.completed { background-color: #dcfce7; color: #166534; }
        .badge.overdue { background-color: #fee2e2; color: #991b1b; }

        .action-link { color: #CF1F3C; text-decoration: none; font-weight: bold; margin-right: 0.5rem; cursor: pointer;}
        .action-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="header">
    <h1>Task Management</h1>
    <a href="/dashboard.php" style="color:#1E1C1C; text-decoration:none; font-weight:bold;">&larr; Back to Dashboard</a>
</div>
<div class="container">
    <div class="card">
        <select id="filter_status" onchange="fetchTasks()" style="padding: 0.5rem; margin-bottom: 1rem;">
            <option value="">All Tasks</option>
            <option value="Pending">Pending</option>
            <option value="In Progress">In Progress</option>
            <option value="Completed">Completed</option>
            <option value="Overdue">Overdue</option>
        </select>

        <table id="tasks_table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Priority</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Assigned To</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="tasks_body">
                <tr><td colspan="6" style="text-align:center;">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
const csrfToken = "<?php echo htmlspecialchars($csrf_token); ?>";

function escapeHTML(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

document.addEventListener('DOMContentLoaded', fetchTasks);

function fetchTasks() {
    const status = document.getElementById('filter_status').value;
    let url = '/api/tasks.php?action=list';
    if(status) url += '&status=' + encodeURIComponent(status);

    fetch(url)
        .then(response => response.json())
        .then(res => {
            const tbody = document.getElementById('tasks_body');
            tbody.innerHTML = '';
            if (res.status === 'success' && res.data.length > 0) {
                res.data.forEach(t => {
                    const tr = document.createElement('tr');
                    let badgeClass = 'pending';
                    if(t.status === 'Completed') badgeClass = 'completed';
                    if(t.status === 'Overdue') badgeClass = 'overdue';

                    tr.innerHTML = `
                        <td><strong>${escapeHTML(t.title)}</strong></td>
                        <td>${escapeHTML(t.priority)}</td>
                        <td>${escapeHTML(t.due_date)}</td>
                        <td><span class="badge ${badgeClass}">${escapeHTML(t.status)}</span></td>
                        <td>${escapeHTML(t.assigned_to_name) || '-'}</td>
                        <td>
                            ${t.status !== 'Completed' ? `<span class="action-link" onclick="updateStatus(${t.id}, 'Completed')">Mark Done</span>` : ''}
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No tasks found.</td></tr>';
            }
        });
}

function updateStatus(id, newStatus) {
    const fd = new FormData();
    fd.append('csrf_token', csrfToken);
    fd.append('id', id);
    fd.append('status', newStatus);

    fetch('/api/tasks.php?action=update_status', {
        method: 'POST',
        body: fd
    }).then(r => r.json()).then(res => {
        if(res.status === 'success') {
            fetchTasks();
        } else {
            alert(res.message);
        }
    });
}
</script>
</body>
</html>