<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';

requireLogin();
requirePermission('leads.view'); // Requires basic lead viewing, sharing may require edit.

$lead_id = (int)($_GET['lead_id'] ?? 0);
$customer_id = (int)($_GET['customer_id'] ?? 0);

if (!$lead_id && !$customer_id) {
    die("Lead ID or Customer ID is required.");
}

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zopa CRM - Property Matching</title>
    <style>
        body { font-family: sans-serif; background-color: #F1EDED; color: #1E1C1C; margin: 0; display: flex; flex-direction: column; height: 100vh; }
        .header { background-color: white; padding: 1rem 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 1.5rem; }
        .header-actions a { color: #666; text-decoration: none; padding: 0.5rem; font-weight: bold; }
        .header-actions a:hover { color: #CF1F3C; }

        .container { flex: 1; padding: 2rem; overflow-y: auto; display: grid; grid-template-columns: 280px 1fr; gap: 2rem; }

        @media (max-width: 900px) {
            .container { grid-template-columns: 1fr; padding: 1rem; }
            .sidebar { order: 2; }
            .main-content { order: 1; }
        }

        .card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
        .card-header { font-size: 1.2rem; font-weight: bold; margin-bottom: 1rem; border-bottom: 1px solid #eee; padding-bottom: 0.5rem;}

        .form-group { margin-bottom: 1rem; display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 0.25rem; font-weight: bold; font-size: 0.85rem; }
        .form-group input, .form-group select { padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; font-family: inherit; }

        .btn { background-color: #CF1F3C; color: white; border: none; padding: 0.5rem 1rem; border-radius: 4px; font-weight: bold; cursor: pointer; display: inline-block; width: 100%; box-sizing: border-box; text-align: center;}
        .btn:hover { background-color: #b01a33; }
        .btn-outline { background-color: transparent; color: #1E1C1C; border: 1px solid #1E1C1C; }
        .btn-outline:hover { background-color: #eee; }

        .property-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; }
        .prop-card { background: white; border: 1px solid #eee; border-radius: 8px; overflow: hidden; position: relative; transition: transform 0.2s;}
        .prop-card:hover { transform: translateY(-2px); box-shadow: 0 6px 12px rgba(0,0,0,0.08); }
        .prop-card-body { padding: 1.5rem; }
        .prop-title { font-weight: bold; font-size: 1.1rem; margin: 0 0 0.5rem 0;}
        .prop-price { color: #CF1F3C; font-weight: bold; font-size: 1.2rem; margin-bottom: 1rem; }
        .prop-meta { font-size: 0.85rem; color: #666; margin-bottom: 0.2rem; }

        .match-badge { position: absolute; top: 1rem; right: 1rem; background: #166534; color: white; font-weight: bold; font-size: 0.85rem; padding: 0.2rem 0.6rem; border-radius: 12px; }
        .match-badge.medium { background: #d97706; }
        .match-badge.low { background: #dc2626; }

        .chk-overlay { position: absolute; top: 1rem; left: 1rem; transform: scale(1.5); cursor: pointer; }

        #loading { text-align: center; padding: 2rem; color: #666; grid-column: 1/-1;}
    </style>
</head>
<body>

<div class="header">
    <h1>Find Matching Properties</h1>
    <div class="header-actions">
        <?php if($lead_id): ?>
        <a href="/leads/view.php?id=<?php echo $lead_id; ?>">&larr; Back to Lead</a>
        <?php else: ?>
        <a href="/customers/view.php?id=<?php echo $customer_id; ?>">&larr; Back to Customer</a>
        <?php endif; ?>
    </div>
</div>

<div class="container">
    <div class="sidebar">
        <div class="card" style="position: sticky; top: 1.5rem;">
            <div class="card-header">Match Criteria</div>
            <form id="matchFilterForm">
                <div class="form-group">
                    <label>City</label>
                    <input type="text" id="filter_city" name="city">
                </div>
                <div class="form-group">
                    <label>Min Budget</label>
                    <input type="number" id="filter_budget_min" name="budget_min" step="1000">
                </div>
                <div class="form-group">
                    <label>Max Budget</label>
                    <input type="number" id="filter_budget_max" name="budget_max" step="1000">
                </div>
                <!-- Categories/Types omitted for brevity in demo, normally populated via DB -->
                <button type="button" class="btn btn-outline" onclick="loadMatches()" style="margin-bottom: 1rem;">Update Matches</button>
            </form>

            <hr style="border:0; border-top:1px solid #eee; margin: 1.5rem 0;">

            <div class="card-header">Share Selected</div>
            <form id="shareForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <?php if($lead_id): ?>
                <input type="hidden" name="lead_id" value="<?php echo $lead_id; ?>">
                <?php else: ?>
                <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label>Channel</label>
                    <select name="channel" required>
                        <option value="WhatsApp">WhatsApp</option>
                        <option value="Email">Email</option>
                        <option value="Link">Shareable Link</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Message (Optional)</label>
                    <textarea name="message" rows="3" style="width:100%; box-sizing:border-box; padding:0.5rem;"></textarea>
                </div>
                <button type="submit" class="btn" id="btn_share">Share Now</button>
            </form>
        </div>
    </div>

    <div class="main-content">
        <div class="property-grid" id="results_grid">
            <div id="loading">Loading initial matches...</div>
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

const leadId = <?php echo json_encode($lead_id); ?>;
const customerId = <?php echo json_encode($customer_id); ?>;

document.addEventListener('DOMContentLoaded', () => {
    // 1. Fetch lead/customer requirements to pre-fill the form
    let apiEndpoint = '';
    if (leadId) apiEndpoint = '/api/leads.php?action=get&id=' + leadId;
    else apiEndpoint = '/api/customers.php?action=get&id=' + customerId;

    fetch(apiEndpoint)
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                const entity = res.data;
                document.getElementById('filter_city').value = entity.city || '';
                // Customers lack direct budget fields without custom schema, handle gracefully
                document.getElementById('filter_budget_min').value = entity.budget_min || '';
                document.getElementById('filter_budget_max').value = entity.budget_max || '';

                // 2. Perform initial match query
                loadMatches();
            }
        });

    // 3. Handle Share Submit
    document.getElementById('shareForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const checkboxes = document.querySelectorAll('.share-chk:checked');
        if (checkboxes.length === 0) {
            alert("Please select at least one property to share.");
            return;
        }

        const btn = document.getElementById('btn_share');
        btn.disabled = true;
        btn.textContent = 'Sharing...';

        const formData = new FormData(this);
        checkboxes.forEach(chk => {
            formData.append('property_ids[]', chk.value);
        });

        fetch('/api/property-sharing.php?action=share', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                alert('Properties successfully shared via selected channel.');
                if (leadId) {
                    window.location.href = '/leads/view.php?id=' + leadId;
                } else {
                    window.location.href = '/customers/view.php?id=' + customerId;
                }
            } else {
                alert(res.message);
                btn.disabled = false;
                btn.textContent = 'Share Now';
            }
        });
    });
});

function loadMatches() {
    const grid = document.getElementById('results_grid');
    grid.innerHTML = '<div id="loading">Calculating matches...</div>';

    const city = document.getElementById('filter_city').value;
    const bMin = document.getElementById('filter_budget_min').value;
    const bMax = document.getElementById('filter_budget_max').value;

    let url = `/api/property-matching.php?action=match`;
    if(city) url += `&city=${encodeURIComponent(city)}`;
    if(bMin) url += `&budget_min=${encodeURIComponent(bMin)}`;
    if(bMax) url += `&budget_max=${encodeURIComponent(bMax)}`;

    fetch(url)
        .then(r => r.json())
        .then(res => {
            grid.innerHTML = '';
            if (res.status === 'success' && res.data.length > 0) {
                res.data.forEach(p => {
                    let badgeClass = 'high';
                    if (p.match_score < 70 && p.match_score >= 40) badgeClass = 'medium';
                    if (p.match_score < 40) badgeClass = 'low';

                    const card = document.createElement('div');
                    card.className = 'prop-card';
                    card.innerHTML = `
                        <input type="checkbox" class="chk-overlay share-chk" value="${escapeHTML(p.id)}">
                        <div class="match-badge ${badgeClass}">${escapeHTML(p.match_score)}% Match</div>
                        <div class="prop-card-body">
                            <h3 class="prop-title">${escapeHTML(p.name)}</h3>
                            <div class="prop-price">${p.base_price ? '$'+escapeHTML(p.base_price) : 'Price on Request'}</div>
                            <div class="prop-meta"><strong>City:</strong> ${escapeHTML(p.city) || 'N/A'}</div>
                            <div class="prop-meta"><strong>Project:</strong> ${escapeHTML(p.project_name) || 'Standalone'}</div>
                            <div class="prop-meta"><strong>Category:</strong> ${escapeHTML(p.category) || '-'}</div>
                            <a href="/properties/view.php?id=${escapeHTML(p.id)}" target="_blank" style="font-size:0.85rem; color:#CF1F3C; text-decoration:none; display:inline-block; margin-top:1rem; font-weight:bold;">View Full Details &nearr;</a>
                        </div>
                    `;
                    grid.appendChild(card);
                });
            } else {
                grid.innerHTML = '<div style="grid-column: 1/-1; text-align:center; padding: 2rem; color:#666;">No available properties match your search criteria.</div>';
            }
        });
}
</script>

</body>
</html>
