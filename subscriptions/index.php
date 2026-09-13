<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';

requireLogin();
// Note: This UI handles Subscription management, so we require higher privileges logically.
// We fallback to checking if they have the specific role internally in the API, but UI can be restrictive too.
if (!hasPermission('Tenant Owner') && !hasPermission('subscriptions.manage')) {
    die("Access Denied: Tenant Owner privileges required.");
}

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token); ?>">
    <title>Zopa CRM - Billing & Subscriptions</title>
    <style>
        body { font-family: sans-serif; background-color: #F1EDED; color: #1E1C1C; margin: 0; display: flex; flex-direction: column; height: 100vh; }
        .header { background-color: white; padding: 1rem 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 1.5rem; }
        .container { flex: 1; padding: 2rem; overflow-y: auto; max-width: 1200px; margin: 0 auto; width: 100%; box-sizing: border-box; }
        .card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
        .card-header { font-size: 1.25rem; font-weight: bold; margin-bottom: 1rem; border-bottom: 2px solid #F1EDED; padding-bottom: 0.5rem; }

        .btn { padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.9rem; display: inline-block;}
        .btn-primary { background-color: #CF1F3C; color: white; width: 100%; font-size: 1rem; padding: 0.75rem;}
        .btn-outline { background-color: transparent; border: 1px solid #ccc; color: #1E1C1C; padding: 0.5rem 1rem; font-size: 0.9rem;}

        .plans-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; }
        .plan-card { background: white; border: 2px solid #e2e8f0; border-radius: 8px; padding: 1.5rem; text-align: center; display: flex; flex-direction: column; }
        .plan-card.current { border-color: #CF1F3C; box-shadow: 0 4px 12px rgba(207,31,60,0.15); }
        .plan-name { font-size: 1.5rem; font-weight: bold; margin-bottom: 0.5rem; }
        .plan-price { font-size: 2rem; font-weight: bold; color: #1E1C1C; margin-bottom: 1rem; }
        .plan-price span { font-size: 1rem; color: #64748b; font-weight: normal; }
        .plan-desc { font-size: 0.9rem; color: #64748b; margin-bottom: 1.5rem; min-height: 40px;}

        .plan-features { list-style: none; padding: 0; margin: 0 0 1.5rem 0; text-align: left; flex: 1; }
        .plan-features li { padding: 0.5rem 0; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; }
        .plan-features li:last-child { border-bottom: none; }

        .badge { padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem; background: #e2e8f0; }
        .badge.Active { background: #dcfce7; color: #166534; }

        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #eee; }
        th { background-color: #f9f9f9; }
    </style>
</head>
<body>

<div class="header">
    <h1>Billing & Subscription</h1>
    <div class="header-nav">
        <a href="/dashboard.php" class="btn btn-outline" style="width: auto;">Dashboard</a>
    </div>
</div>

<div class="container">
    <div class="card" id="currentSubContainer" style="display:none;">
        <div class="card-header">Current Subscription</div>
        <p>You are currently on the <strong id="curPlanName">...</strong> plan (<span id="curPlanStatus" class="badge">...</span>).</p>
        <p>Billing Interval: <strong id="curBillingInt">...</strong> | Renews: <strong id="curRenewDate">...</strong></p>
    </div>

    <div class="card">
        <div class="card-header" style="border: none;">Available Plans</div>
        <div class="plans-grid" id="plansContainer">
            <div>Loading plans...</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Billing History / Invoices</div>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Invoice #</th>
                    <th>Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="invoicesList">
                <tr><td colspan="4" style="text-align:center;">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
let currentPlanId = null;

function escapeHTML(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function loadSubscription() {
    fetch('/api/subscriptions.php?action=my_subscription')
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success' && res.data.id) {
                const sub = res.data;
                currentPlanId = sub.plan_id;

                document.getElementById('currentSubContainer').style.display = 'block';
                document.getElementById('curPlanName').textContent = sub.plan_name;

                const sBadge = document.getElementById('curPlanStatus');
                sBadge.textContent = sub.status;
                sBadge.className = 'badge ' + sub.status;

                document.getElementById('curBillingInt').textContent = sub.billing_interval;
                document.getElementById('curRenewDate').textContent = sub.end_date;

                const tbody = document.getElementById('invoicesList');
                tbody.innerHTML = '';
                if (sub.recent_invoices && sub.recent_invoices.length > 0) {
                    sub.recent_invoices.forEach(inv => {
                        tbody.innerHTML += `
                            <tr>
                                <td>${escapeHTML(inv.issue_date)}</td>
                                <td><strong>${escapeHTML(inv.invoice_number)}</strong></td>
                                <td>${escapeHTML(inv.currency)} ${escapeHTML(inv.final_amount)}</td>
                                <td><span class="badge ${escapeHTML(inv.status)}">${escapeHTML(inv.status)}</span></td>
                            </tr>
                        `;
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">No invoices found.</td></tr>';
                }
            } else {
                document.getElementById('invoicesList').innerHTML = '<tr><td colspan="4" style="text-align:center;">No invoices found.</td></tr>';
            }
            // Load plans after knowing current state
            loadPlans();
        });
}

function loadPlans() {
    fetch('/api/subscriptions.php?action=plans')
        .then(r => r.json())
        .then(res => {
            const container = document.getElementById('plansContainer');
            container.innerHTML = '';
            if (res.status === 'success') {
                res.data.forEach(p => {
                    const isCurrent = (p.id == currentPlanId);
                    const currentClass = isCurrent ? 'current' : '';

                    let btnHtml = '';
                    if (isCurrent) {
                        btnHtml = `<button class="btn" style="background:#e2e8f0; color:#64748b; cursor:not-allowed;" disabled>Current Plan</button>`;
                    } else {
                        btnHtml = `<button class="btn btn-primary" onclick="subscribe(${p.id}, '${escapeHTML(p.name)}')">Select Plan</button>`;
                    }

                    let featuresHtml = '';
                    if (p.features) {
                        for (const [code, val] of Object.entries(p.features)) {
                            featuresHtml += `<li><strong>${escapeHTML(val)}</strong> ${escapeHTML(code.replace('_', ' '))}</li>`;
                        }
                    }

                    container.innerHTML += `
                        <div class="plan-card ${currentClass}">
                            <div class="plan-name">${escapeHTML(p.name)}</div>
                            <div class="plan-desc">${escapeHTML(p.description)}</div>
                            <div class="plan-price">${escapeHTML(p.currency)} ${escapeHTML(p.price)} <span>/ ${escapeHTML(p.billing_interval)}</span></div>
                            <ul class="plan-features">
                                ${featuresHtml}
                            </ul>
                            ${btnHtml}
                        </div>
                    `;
                });
            }
        });
}

function subscribe(planId, planName) {
    if (!confirm('Are you sure you want to subscribe to the ' + planName + ' plan?')) return;

    const fd = new FormData();
    fd.append('action', 'subscribe');
    fd.append('plan_id', planId);
    fd.append('csrf_token', csrfToken);

    fetch('/api/subscriptions.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                alert(res.message);
                loadSubscription();
            } else {
                alert('Error: ' + res.message);
            }
        });
}

document.addEventListener('DOMContentLoaded', loadSubscription);
</script>

</body>
</html>
