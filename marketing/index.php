<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';

requireLogin();
requirePermission('marketing.view');

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token); ?>">
    <title>Zopa CRM - Marketing & Campaigns</title>
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
        .badge.Unconfigured { background: #fef08a; color: #854d0e; }
        .badge.Active { background: #dcfce7; color: #166534; }
        .badge.Draft { background: #f1f5f9; color: #475569; }

        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }

        .form-group { display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem; }
        input[type="text"], input[type="number"], select { padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; }

        @media(max-width: 768px) {
            .grid-2 { grid-template-columns: 1fr; }
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
    <h1>Marketing & Campaigns</h1>
    <div class="header-nav">
        <a href="/dashboard.php" class="btn btn-primary">Dashboard</a>
    </div>
</div>

<div class="container">
    <div class="grid-2">
        <div class="card">
            <div class="card-header">Connect Ad Account</div>
            <form id="addAccountForm">
                <input type="hidden" name="action" value="create_account">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="form-group">
                    <label>Platform *</label>
                    <select name="platform_id" required>
                        <option value="1">Meta (Facebook/Instagram)</option>
                        <option value="2">Google Ads</option>
                        <option value="3">Website</option>
                        <option value="4">Property Portal</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Account Name *</label>
                    <input type="text" name="account_name" required>
                </div>
                <button type="submit" class="btn btn-primary">Add Account</button>
                <div id="accMsg" style="margin-top:0.5rem;"></div>
            </form>

            <h4 style="margin-top:2rem; margin-bottom:1rem;">Configured Accounts</h4>
            <table style="font-size:0.9rem;">
                <thead><tr><th>Platform</th><th>Name</th><th>Status</th></tr></thead>
                <tbody id="accountsList"><tr><td colspan="3">Loading...</td></tr></tbody>
            </table>
        </div>

        <div class="card">
            <div class="card-header">Create Campaign</div>
            <form id="addCampaignForm">
                <input type="hidden" name="action" value="create_campaign">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="form-group">
                    <label>Ad Account *</label>
                    <select name="ad_account_id" id="campaignAccountSelect" required></select>
                </div>
                <div class="form-group">
                    <label>Campaign Name *</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Budget (Total)</label>
                    <input type="number" step="0.01" name="budget" value="0">
                </div>
                <button type="submit" class="btn btn-primary">Create Campaign</button>
                <div id="campMsg" style="margin-top:0.5rem;"></div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Active Campaigns</div>
        <table>
            <thead>
                <tr>
                    <th>Campaign</th>
                    <th>Account</th>
                    <th>Spend / Budget</th>
                    <th>Leads</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="campaignsList">
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

function loadAccounts() {
    fetch('/api/marketing.php?action=accounts')
        .then(r => r.json())
        .then(res => {
            const tbody = document.getElementById('accountsList');
            const select = document.getElementById('campaignAccountSelect');
            tbody.innerHTML = ''; select.innerHTML = '<option value="">Select Account</option>';

            if (res.status === 'success' && res.data.length > 0) {
                res.data.forEach(a => {
                    tbody.innerHTML += `
                        <tr>
                            <td data-label="Platform">${escapeHTML(a.platform_name)}</td>
                            <td data-label="Name"><strong>${escapeHTML(a.account_name)}</strong></td>
                            <td data-label="Status"><span class="badge ${escapeHTML(a.status)}">${escapeHTML(a.status)}</span></td>
                        </tr>
                    `;
                    select.innerHTML += `<option value="${a.id}">${escapeHTML(a.platform_name)} - ${escapeHTML(a.account_name)}</option>`;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;">No accounts connected.</td></tr>';
            }
        });
}

function loadCampaigns() {
    fetch('/api/marketing.php?action=campaigns')
        .then(r => r.json())
        .then(res => {
            const tbody = document.getElementById('campaignsList');
            tbody.innerHTML = '';

            if (res.status === 'success' && res.data.length > 0) {
                res.data.forEach(c => {
                    tbody.innerHTML += `
                        <tr>
                            <td data-label="Campaign"><strong>${escapeHTML(c.name)}</strong></td>
                            <td data-label="Account">${escapeHTML(c.platform_name)} - ${escapeHTML(c.account_name)}</td>
                            <td data-label="Spend/Budget">${escapeHTML(c.spend)} / ${escapeHTML(c.budget)}</td>
                            <td data-label="Leads">${escapeHTML(c.leads)}</td>
                            <td data-label="Status"><span class="badge ${escapeHTML(c.status)}">${escapeHTML(c.status)}</span></td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No campaigns created yet.</td></tr>';
            }
        });
}

document.addEventListener('DOMContentLoaded', () => {
    loadAccounts();
    loadCampaigns();

    document.getElementById('addAccountForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        fetch('/api/marketing.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                const msg = document.getElementById('accMsg');
                if (res.status === 'success') {
                    msg.style.color = 'green'; msg.textContent = res.message;
                    this.reset(); loadAccounts();
                } else {
                    msg.style.color = 'red'; msg.textContent = res.message;
                }
            });
    });

    document.getElementById('addCampaignForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        fetch('/api/marketing.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                const msg = document.getElementById('campMsg');
                if (res.status === 'success') {
                    msg.style.color = 'green'; msg.textContent = res.message;
                    this.reset(); loadCampaigns();
                } else {
                    msg.style.color = 'red'; msg.textContent = res.message;
                }
            });
    });
});
</script>

</body>
</html>
