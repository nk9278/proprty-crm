<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';

requireLogin();
requirePermission('support.view');

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token); ?>">
    <title>Zopa CRM - Support Tickets</title>
    <style>
        body { font-family: sans-serif; background-color: #F1EDED; color: #1E1C1C; margin: 0; display: flex; flex-direction: column; height: 100vh; }
        .header { background-color: white; padding: 1rem 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 1.5rem; }
        .container { flex: 1; padding: 2rem; overflow-y: auto; max-width: 1200px; margin: 0 auto; width: 100%; box-sizing: border-box; }
        .card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
        .card-header { font-size: 1.25rem; font-weight: bold; margin-bottom: 1rem; border-bottom: 2px solid #F1EDED; padding-bottom: 0.5rem; }

        .btn { padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.9rem; display: inline-block;}
        .btn-primary { background-color: #CF1F3C; color: white; }
        .btn-outline { background-color: transparent; border: 1px solid #ccc; }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #eee; }
        th { background-color: #f9f9f9; }

        .badge { padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem; background: #e2e8f0; }
        .badge.Open { background: #fee2e2; color: #991b1b; }
        .badge.Resolved { background: #dcfce7; color: #166534; }

        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
        .form-group { display: flex; flex-direction: column; gap: 0.5rem; }
        input[type="text"], input[type="number"], select, textarea { padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; }

        @media(max-width: 768px) {
            table, thead, tbody, th, td, tr { display: block; }
            thead tr { position: absolute; top: -9999px; left: -9999px; }
            tr { margin-bottom: 1rem; border: 1px solid #ccc; border-radius: 8px; padding: 1rem; background: white; }
            td { border: none; position: relative; padding-left: 45%; text-align: right; }
            td:before { position: absolute; left: 1rem; width: 40%; padding-right: 10px; white-space: nowrap; font-weight: bold; text-align: left; content: attr(data-label); }
        }
    </style>
</head>
<body>

<div class="header">
    <h1>Support Tickets</h1>
    <div class="header-nav">
        <a href="/dashboard.php" class="btn btn-primary">Dashboard</a>
    </div>
</div>

<div class="container">
    <?php if(hasPermission('support.manage')): ?>
    <div class="card">
        <h3 style="margin-top:0">Create Ticket</h3>
        <form id="createTicketForm">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <div class="form-grid">
                <div class="form-group"><label>Customer ID</label><input type="number" name="customer_id"></div>
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category" required>
                        <option value="General">General</option>
                        <option value="Billing">Billing</option>
                        <option value="Technical">Technical</option>
                        <option value="Possession">Possession / Handover</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Priority *</label>
                    <select name="priority" required>
                        <option value="Medium">Medium</option>
                        <option value="Low">Low</option>
                        <option value="High">High</option>
                        <option value="Urgent">Urgent</option>
                    </select>
                </div>
            </div>
            <div class="form-group" style="margin-bottom: 1rem;">
                <label>Subject *</label>
                <input type="text" name="subject" required>
            </div>
            <div class="form-group" style="margin-bottom: 1rem;">
                <label>Description *</label>
                <textarea name="description" rows="3" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Create Ticket</button>
            <div id="addMsg" style="margin-top:0.5rem;"></div>
        </form>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">All Tickets</div>
        <table>
            <thead>
                <tr>
                    <th>Ticket</th>
                    <th>Customer</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="ticketsList">
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

function loadTickets() {
    fetch('/api/support.php?action=list')
        .then(r => r.json())
        .then(res => {
            const tbody = document.getElementById('ticketsList');
            tbody.innerHTML = '';
            if (res.status === 'success' && res.data.length > 0) {
                res.data.forEach(t => {
                    tbody.innerHTML += `
                        <tr>
                            <td data-label="Ticket"><strong>${escapeHTML(t.subject)}</strong><br><small>${escapeHTML(t.category)}</small></td>
                            <td data-label="Customer">${escapeHTML(t.customer_name) || 'None'}</td>
                            <td data-label="Priority">${escapeHTML(t.priority)}</td>
                            <td data-label="Status"><span class="badge ${escapeHTML(t.status)}">${escapeHTML(t.status)}</span></td>
                            <td data-label="Action">
                                <select onchange="updateStatus(${t.id}, this.value)">
                                    <option value="">Set Status...</option>
                                    <option value="Open">Open</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Resolved">Resolved</option>
                                </select>
                            </td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No tickets found.</td></tr>';
            }
        });
}

function updateStatus(id, newStatus) {
    if (!newStatus) return;
    const fd = new FormData();
    fd.append('action', 'update_status');
    fd.append('ticket_id', id);
    fd.append('status', newStatus);
    fd.append('csrf_token', csrfToken);

    fetch('/api/support.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if(res.status === 'success') loadTickets();
            else alert(res.message);
        });
}

document.addEventListener('DOMContentLoaded', () => {
    loadTickets();

    const form = document.getElementById('createTicketForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            const msg = document.getElementById('addMsg');

            fetch('/api/support.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success') {
                        msg.style.color = 'green'; msg.textContent = res.message;
                        this.reset(); loadTickets();
                    } else {
                        msg.style.color = 'red'; msg.textContent = res.message;
                    }
                });
        });
    }
});
</script>

</body>
</html>
