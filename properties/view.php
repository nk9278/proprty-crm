<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';

requireLogin();
requirePermission('properties.view');

$prop_id = $_GET['id'] ?? null;
if (!$prop_id) {
    die("Property ID is required.");
}

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zopa CRM - Property Details</title>
    <style>
        body { font-family: sans-serif; background-color: #F1EDED; color: #1E1C1C; margin: 0; }
        .header { background-color: white; padding: 1rem 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 1.5rem; }
        .header-actions a { color: #666; text-decoration: none; padding: 0.5rem; font-weight: bold; }
        .header-actions a:hover { color: #CF1F3C; }

        .container { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; display: grid; grid-template-columns: 1fr 400px; gap: 2rem; }
        @media (max-width: 900px) {
            .container { grid-template-columns: 1fr; margin: 1rem auto; }
        }

        .card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
        .card-header { font-size: 1.2rem; font-weight: bold; margin-bottom: 1rem; border-bottom: 1px solid #eee; padding-bottom: 0.5rem;}

        .detail-row { display: flex; margin-bottom: 0.75rem; font-size: 0.95rem; }
        .detail-label { width: 150px; font-weight: bold; color: #555; }
        .detail-value { flex: 1; }

        .btn { background-color: #CF1F3C; color: white; border: none; padding: 0.4rem 0.8rem; border-radius: 4px; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-block; font-size: 0.85rem;}
        .btn:hover { background-color: #b01a33; }

        .btn-outline { background-color: transparent; color: #1E1C1C; border: 1px solid #1E1C1C; }
        .btn-outline:hover { background-color: #eee; }

        table { width: 100%; border-collapse: collapse; margin-top: 0.5rem; }
        th, td { text-align: left; padding: 0.75rem; border-bottom: 1px solid #eee; font-size: 0.9rem;}
        th { background-color: #f9f9f9; color: #666; }

        .badge { display: inline-block; padding: 0.2rem 0.4rem; border-radius: 12px; font-size: 0.75rem; font-weight: bold; }
        .badge.available { background-color: #dcfce7; color: #166534; }
        .badge.hold { background-color: #fef08a; color: #854d0e; }
        .badge.sold { background-color: #fee2e2; color: #991b1b; }

        #error_msg { color: #CF1F3C; text-align: center; font-weight: bold; padding: 2rem; display: none; }
        #loading { text-align: center; padding: 2rem; color: #666; }
    </style>
</head>
<body>

<div class="header">
    <h1 id="top_name">Property Details</h1>
    <div class="header-actions">
        <a href="/properties/index.php">&larr; Back to Inventory</a>
    </div>
</div>

<div id="loading">Loading property data...</div>
<div id="error_msg"></div>

<div class="container" id="content" style="display: none;">
    <div>
        <div class="card">
            <div class="card-header">Property Details</div>
            <div class="detail-row">
                <div class="detail-label">Name</div>
                <div class="detail-value" id="val_name">-</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Project</div>
                <div class="detail-value" id="val_project">-</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Category</div>
                <div class="detail-value" id="val_category">-</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Type</div>
                <div class="detail-value" id="val_type">-</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Purpose</div>
                <div class="detail-value" id="val_purpose">-</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Inventory Units (Hierarchy)</div>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Unit No.</th>
                            <th>Tower</th>
                            <th>Floor</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="units_body">
                        <!-- Populated via JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-header">Quick Actions</div>
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <a href="#" class="btn btn-outline" onclick="alert('Phase 18 Integration')">Upload Media</a>
                <a href="#" class="btn btn-outline" onclick="alert('Phase 8 Sharing Integration')">Share Property</a>
            </div>
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

const propId = <?php echo json_encode($prop_id); ?>;
const csrfToken = <?php echo json_encode($csrf_token); ?>;

function loadProperty() {
    fetch('/api/properties.php?action=get_property&id=' + propId)
        .then(response => response.json())
        .then(res => {
            document.getElementById('loading').style.display = 'none';
            if (res.status === 'success') {
                document.getElementById('content').style.display = 'grid';
                const p = res.data;

                document.getElementById('top_name').textContent = escapeHTML(p.name);
                document.getElementById('val_name').textContent = escapeHTML(p.name);
                document.getElementById('val_project').textContent = escapeHTML(p.project_name) || 'Standalone';
                document.getElementById('val_category').textContent = escapeHTML(p.category_name) || '-';
                document.getElementById('val_type').textContent = escapeHTML(p.type_name) || '-';
                document.getElementById('val_purpose').textContent = escapeHTML(p.purpose) || '-';

                const unitsBody = document.getElementById('units_body');
                unitsBody.innerHTML = '';

                if (p.units && p.units.length > 0) {
                    p.units.forEach(u => {
                        const tr = document.createElement('tr');

                        let badgeClass = '';
                        if (u.status === 'Available') badgeClass = 'available';
                        else if (u.status === 'Hold') badgeClass = 'hold';
                        else if (u.status === 'Sold') badgeClass = 'sold';

                        let actionBtn = '';
                        if (u.status === 'Available') {
                            actionBtn = `<button class="btn btn-hold" data-id="${escapeHTML(u.id)}">Hold (2h)</button>`;
                        } else {
                            actionBtn = `<button class="btn btn-outline" disabled>Unavailable</button>`;
                        }

                        tr.innerHTML = `
                            <td><strong>${escapeHTML(u.unit_number)}</strong></td>
                            <td>${escapeHTML(u.tower_name) || '-'}</td>
                            <td>${escapeHTML(u.floor_number) || '-'}</td>
                            <td><span class="badge ${badgeClass}">${escapeHTML(u.status)}</span></td>
                            <td>${actionBtn}</td>
                        `;
                        unitsBody.appendChild(tr);
                    });

                    // Attach hold handlers
                    document.querySelectorAll('.btn-hold').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const unitId = this.getAttribute('data-id');
                            holdUnit(unitId);
                        });
                    });

                } else {
                    unitsBody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No inventory units defined for this property.</td></tr>';
                }

            } else {
                const err = document.getElementById('error_msg');
                err.style.display = 'block';
                err.textContent = res.message;
            }
        })
        .catch(err => {
            document.getElementById('loading').style.display = 'none';
            const errMsg = document.getElementById('error_msg');
            errMsg.style.display = 'block';
            errMsg.textContent = 'A network error occurred.';
        });
}

function holdUnit(unitId) {
    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('unit_id', unitId);

    fetch('/api/properties.php?action=hold_inventory', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            loadProperty(); // refresh inventory list
        } else {
            alert(res.message);
        }
    });
}

document.addEventListener('DOMContentLoaded', loadProperty);
</script>

</body>
</html>
