<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';

requireLogin();
requirePermission('properties.view');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zopa CRM - Properties & Inventory</title>
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
        .badge.available { background-color: #dcfce7; color: #166534; }
        .badge.hold { background-color: #fef08a; color: #854d0e; }
        .badge.sold { background-color: #fee2e2; color: #991b1b; }

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
    <h1>Property & Inventory Dashboard</h1>
    <div class="header-actions">
        <?php if (hasPermission('properties.create')): ?>
        <a href="/properties/create.php">+ Add Property</a>
        <?php endif; ?>
    </div>
</div>

<div class="container">
    <div class="card">
        <div class="filters">
            <input type="text" id="search" placeholder="Search properties...">
            <select id="status_filter">
                <option value="">All Statuses</option>
                <option value="Available">Available</option>
                <option value="Hold">Hold</option>
                <option value="Sold">Sold</option>
            </select>
        </div>

        <div style="overflow-x: auto;">
            <table id="properties_table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th class="hide-mobile">Project</th>
                        <th>Category</th>
                        <th class="hide-mobile">Type</th>
                        <th class="hide-mobile">Base Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="properties_body">
                    <tr><td colspan="7" style="text-align:center;">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function escapeHTML(str) {
    if (!str && str !== 0) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

document.addEventListener('DOMContentLoaded', () => {
    fetchProperties();
    document.getElementById('search').addEventListener('input', fetchProperties);
    document.getElementById('status_filter').addEventListener('change', fetchProperties);
});

function fetchProperties() {
    fetch('/api/properties.php?action=list_properties')
        .then(response => response.json())
        .then(res => {
            const tbody = document.getElementById('properties_body');
            tbody.innerHTML = '';

            if (res.status === 'success' && res.data.length > 0) {
                const search = document.getElementById('search').value.toLowerCase();
                const statusFilter = document.getElementById('status_filter').value;

                const filtered = res.data.filter(p => {
                    if (search && !p.name.toLowerCase().includes(search) && !(p.project_name || '').toLowerCase().includes(search)) return false;
                    if (statusFilter && p.status !== statusFilter) return false;
                    return true;
                });

                if (filtered.length === 0) {
                     tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">No matching properties found.</td></tr>';
                     return;
                }

                filtered.forEach(p => {
                    const tr = document.createElement('tr');

                    let badgeClass = '';
                    if (p.status === 'Available') badgeClass = 'available';
                    else if (p.status === 'Hold') badgeClass = 'hold';
                    else if (p.status === 'Sold') badgeClass = 'sold';

                    tr.innerHTML = `
                        <td><strong>${escapeHTML(p.name)}</strong></td>
                        <td class="hide-mobile">${escapeHTML(p.project_name) || '-'}</td>
                        <td>${escapeHTML(p.category)}</td>
                        <td class="hide-mobile">${escapeHTML(p.type)}</td>
                        <td class="hide-mobile">${p.base_price ? escapeHTML(p.base_price) : '-'}</td>
                        <td><span class="badge ${badgeClass}">${escapeHTML(p.status)}</span></td>
                        <td>
                            <a href="/properties/view.php?id=${escapeHTML(p.id)}" class="action-link">View</a>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">No properties found.</td></tr>';
            }
        })
        .catch(err => {
            document.getElementById('properties_body').innerHTML = '<tr><td colspan="7" style="text-align:center; color:red;">Error loading properties.</td></tr>';
        });
}
</script>

</body>
</html>
