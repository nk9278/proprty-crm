<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';

requireLogin();
requirePermission('customers.view');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zopa CRM - Customers</title>
    <style>
        body { font-family: sans-serif; background-color: #F1EDED; color: #1E1C1C; margin: 0; display: flex; flex-direction: column; height: 100vh; }
        .header { background-color: white; padding: 1rem 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 1.5rem; }

        .container { flex: 1; padding: 2rem; overflow-y: auto; }
        .card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }

        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { text-align: left; padding: 1rem; border-bottom: 1px solid #eee; }
        th { background-color: #f9f9f9; font-weight: 600; color: #666; }

        .filters { display: flex; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap;}
        .filters input { padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; }

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
    <h1>Customers</h1>
</div>

<div class="container">
    <div class="card">
        <div class="filters">
            <input type="text" id="search" placeholder="Search customers by name, mobile...">
        </div>

        <div style="overflow-x: auto;">
            <table id="customers_table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Mobile</th>
                        <th class="hide-mobile">Email</th>
                        <th class="hide-mobile">City</th>
                        <th class="hide-mobile">Assigned To</th>
                        <th class="hide-mobile">Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="customers_body">
                    <tr><td colspan="8" style="text-align:center;">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function escapeHTML(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

document.addEventListener('DOMContentLoaded', () => {
    fetchCustomers();
    document.getElementById('search').addEventListener('input', fetchCustomers);
});

function fetchCustomers() {
    fetch('/api/customers.php?action=list')
        .then(response => response.json())
        .then(res => {
            const tbody = document.getElementById('customers_body');
            tbody.innerHTML = '';

            if (res.status === 'success' && res.data.length > 0) {
                const search = document.getElementById('search').value.toLowerCase();

                const filtered = res.data.filter(c => {
                    if (search && !c.name.toLowerCase().includes(search) && !c.mobile.includes(search)) return false;
                    return true;
                });

                if (filtered.length === 0) {
                     tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;">No matching customers found.</td></tr>';
                     return;
                }

                filtered.forEach(c => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${escapeHTML(c.id)}</td>
                        <td><strong>${escapeHTML(c.name)}</strong></td>
                        <td>${escapeHTML(c.mobile)}</td>
                        <td class="hide-mobile">${escapeHTML(c.email) || '-'}</td>
                        <td class="hide-mobile">${escapeHTML(c.city) || '-'}</td>
                        <td class="hide-mobile">${escapeHTML(c.assigned_to) || '-'}</td>
                        <td class="hide-mobile">${escapeHTML(new Date(c.created_at).toLocaleDateString())}</td>
                        <td>
                            <a href="/customers/view.php?id=${escapeHTML(c.id)}" class="action-link">View</a>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;">No customers found.</td></tr>';
            }
        })
        .catch(err => {
            document.getElementById('customers_body').innerHTML = '<tr><td colspan="8" style="text-align:center; color:red;">Error loading customers.</td></tr>';
        });
}
</script>

</body>
</html>
