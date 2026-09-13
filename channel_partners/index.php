<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';

requireLogin();
requirePermission('partners.view');

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token); ?>">
    <title>Zopa CRM - Channel Partners</title>
    <style>
        body { font-family: sans-serif; background-color: #F1EDED; color: #1E1C1C; margin: 0; display: flex; flex-direction: column; height: 100vh; }
        .header { background-color: white; padding: 1rem 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 1.5rem; }
        .container { flex: 1; padding: 2rem; overflow-y: auto; }
        .card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 1rem; }

        .btn { padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.9rem; display: inline-block;}
        .btn-primary { background-color: #CF1F3C; color: white; }
        .btn-outline { background-color: transparent; border: 1px solid #ccc; }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #eee; }
        th { background-color: #f9f9f9; }

        .badge { padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem; background: #e2e8f0; }

        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
        .form-group { display: flex; flex-direction: column; gap: 0.5rem; }
        input[type="text"], input[type="email"] { padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; }

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
    <h1>Channel Partners</h1>
    <div class="header-nav">
        <a href="/dashboard.php" class="btn btn-primary">Dashboard</a>
    </div>
</div>

<div class="container">
    <?php if(hasPermission('partners.create')): ?>
    <div class="card">
        <h3 style="margin-top:0">Add New Partner</h3>
        <form id="addPartnerForm">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <div class="form-grid">
                <div class="form-group"><label>Company/Agency *</label><input type="text" name="company_name" required></div>
                <div class="form-group"><label>Contact Person *</label><input type="text" name="contact_person" required></div>
                <div class="form-group"><label>Mobile *</label><input type="text" name="mobile" required></div>
                <div class="form-group"><label>Email</label><input type="email" name="email"></div>
                <div class="form-group"><label>RERA Number</label><input type="text" name="rera_number"></div>
            </div>
            <button type="submit" class="btn btn-primary">Add Partner</button>
            <div id="addMsg" style="margin-top:0.5rem;"></div>
        </form>
    </div>
    <?php endif; ?>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Company Name</th>
                    <th>Contact</th>
                    <th>RERA</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="partnersList">
                <tr><td colspan="4" style="text-align:center;">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
function escapeHTML(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function loadPartners() {
    fetch('/api/channel-partners.php?action=list')
        .then(r => r.json())
        .then(res => {
            const tbody = document.getElementById('partnersList');
            tbody.innerHTML = '';
            if (res.status === 'success' && res.data.length > 0) {
                res.data.forEach(p => {
                    tbody.innerHTML += `
                        <tr>
                            <td data-label="Company"><strong>${escapeHTML(p.company_name)}</strong></td>
                            <td data-label="Contact">${escapeHTML(p.contact_person)}<br><small>${escapeHTML(p.mobile)}</small></td>
                            <td data-label="RERA">${escapeHTML(p.rera_number) || 'N/A'}</td>
                            <td data-label="Status"><span class="badge">${escapeHTML(p.status)}</span></td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">No partners found.</td></tr>';
            }
        });
}

document.addEventListener('DOMContentLoaded', () => {
    loadPartners();

    const form = document.getElementById('addPartnerForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            const msg = document.getElementById('addMsg');

            fetch('/api/channel-partners.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success') {
                        msg.style.color = 'green';
                        msg.textContent = res.message;
                        this.reset();
                        loadPartners();
                    } else {
                        msg.style.color = 'red';
                        msg.textContent = res.message;
                    }
                });
        });
    }
});
</script>

</body>
</html>
