<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';

requireLogin();
requirePermission('leads.view');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zopa CRM - Leads</title>
    <style>
        body { font-family: sans-serif; background-color: #F1EDED; color: #1E1C1C; margin: 0; display: flex; flex-direction: column; height: 100vh; }
        .header { background-color: white; padding: 1rem 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 1.5rem; }
        .header-actions a { background-color: #CF1F3C; color: white; text-decoration: none; padding: 0.5rem 1rem; border-radius: 4px; font-weight: bold; }
        .header-actions a:hover { background-color: #b01a33; }

        .container { flex: 1; padding: 2rem; overflow-y: auto; }
        .card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }

        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { text-align: left; padding: 1rem; border-bottom: 1px solid #eee; }
        th { background-color: #f9f9f9; font-weight: 600; color: #666; }

        .filters { display: flex; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap;}
        .filters input, .filters select { padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; }

        .badge { display: inline-block; padding: 0.25rem 0.5rem; border-radius: 12px; font-size: 0.8rem; font-weight: bold; }
        .badge.new { background-color: #e0f2fe; color: #0284c7; }
        .badge.hot { background-color: #fee2e2; color: #ef4444; }

        .action-link { color: #CF1F3C; text-decoration: none; font-weight: bold; margin-right: 0.5rem;}
        .action-link:hover { text-decoration: underline; }

        @media (max-width: 768px) {
            .container { padding: 1rem; }
            th, td { padding: 0.5rem; font-size: 0.9rem; }
            .hide-mobile { display: none; }
        }
    </style>
</head>
<body>

<div class="header">
    <h1>Leads Dashboard</h1>
    <div class="header-actions">
        <?php if (hasPermission('leads.create')): ?>
        <a href="/leads/create.php">+ New Lead</a>
        <?php endif; ?>
    </div>
</div>

<div class="container">
    <div class="card">
        <div class="filters" style="justify-content: space-between;">
            <div style="display:flex; gap: 1rem;">
                <input type="text" id="search" placeholder="Search leads...">
                <select id="status_filter">
                    <option value="">All Statuses</option>
                    <option value="New">New</option>
                    <option value="Contacted">Contacted</option>
                </select>
            </div>

            <?php if(hasPermission('leads.assign')): ?>
            <form id="bulkAssignForm" style="display:flex; gap: 0.5rem; align-items:center;">
                <input type="hidden" name="csrf_token" id="bulk_csrf" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                <input type="number" id="bulk_user_id" placeholder="User ID" required style="width:100px;">
                <button type="button" class="header-actions a" style="border:none; cursor:pointer;" onclick="submitBulkAssign()">Bulk Assign</button>
            </form>
            <?php endif; ?>
        </div>

        <div style="overflow-x: auto;">
            <table id="leads_table">
                <thead>
                    <tr>
                        <th style="width:30px;"><input type="checkbox" id="selectAll" onclick="toggleAll(this)"></th>
                        <th>Name</th>
                        <th>Mobile</th>
                        <th class="hide-mobile">Email</th>
                        <th>Status</th>
                        <th class="hide-mobile">Assigned To</th>
                        <th class="hide-mobile">Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="leads_body">
                    <tr><td colspan="7" style="text-align:center;">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    fetchLeads();

    // Basic frontend filter setup
    document.getElementById('search').addEventListener('input', fetchLeads);
    document.getElementById('status_filter').addEventListener('change', fetchLeads);
});

function escapeHTML(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function fetchLeads() {
    fetch('/api/leads.php?action=list')
        .then(response => response.json())
        .then(res => {
            const tbody = document.getElementById('leads_body');
            tbody.innerHTML = '';

            if (res.status === 'success' && res.data.length > 0) {
                // Filter logic
                const search = document.getElementById('search').value.toLowerCase();
                const statusFilter = document.getElementById('status_filter').value;

                const filtered = res.data.filter(lead => {
                    if (search && !lead.name.toLowerCase().includes(search) && !lead.mobile.includes(search)) return false;
                    if (statusFilter && lead.status !== statusFilter) return false;
                    return true;
                });

                if (filtered.length === 0) {
                     tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">No matching leads found.</td></tr>';
                     return;
                }

                filtered.forEach(lead => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td><input type="checkbox" class="lead-chk" value="${escapeHTML(lead.id)}"></td>
                        <td><strong>${escapeHTML(lead.name)}</strong></td>
                        <td>${escapeHTML(lead.mobile)}</td>
                        <td class="hide-mobile">${escapeHTML(lead.email) || '-'}</td>
                        <td><span class="badge ${lead.status === 'New' ? 'new' : ''}">${escapeHTML(lead.status) || 'Unassigned'}</span></td>
                        <td class="hide-mobile">${escapeHTML(lead.assigned_to) || '-'}</td>
                        <td class="hide-mobile">${escapeHTML(new Date(lead.created_at).toLocaleDateString())}</td>
                        <td>
                            <a href="/leads/view.php?id=${escapeHTML(lead.id)}" class="action-link">View</a>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;">No leads found.</td></tr>';
            }
        })
        .catch(err => {
            document.getElementById('leads_body').innerHTML = '<tr><td colspan="8" style="text-align:center; color:red;">Error loading leads.</td></tr>';
        });
}

function toggleAll(source) {
    const checkboxes = document.querySelectorAll('.lead-chk');
    for(let i=0; i<checkboxes.length; i++) {
        checkboxes[i].checked = source.checked;
    }
}

function submitBulkAssign() {
    const userId = document.getElementById('bulk_user_id').value;
    const csrf = document.getElementById('bulk_csrf').value;
    if(!userId) {
        alert("Enter a User ID");
        return;
    }

    const checkboxes = document.querySelectorAll('.lead-chk:checked');
    if(checkboxes.length === 0) {
        alert("Select at least one lead");
        return;
    }

    let promises = [];

    for(let i=0; i<checkboxes.length; i++) {
        const formData = new FormData();
        formData.append('csrf_token', csrf);
        formData.append('lead_id', checkboxes[i].value);
        formData.append('user_id', userId);
        formData.append('assignment_method', 'Manual (Bulk)');

        promises.push(
            fetch('/api/leads.php?action=assign', {
                method: 'POST',
                body: formData
            }).then(r => r.json())
        );
    }

    Promise.all(promises).then(results => {
        alert("Bulk assignment complete.");
        fetchLeads();
    });
}
</script>

</body>
</html>
