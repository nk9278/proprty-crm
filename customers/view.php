<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';

requireLogin();
requirePermission('customers.view');

$customer_id = $_GET['id'] ?? null;
if (!$customer_id) {
    die("Customer ID is required.");
}
$customer_id_safe = htmlspecialchars($customer_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zopa CRM - Customer Profile</title>
    <style>
        body { font-family: sans-serif; background-color: #F1EDED; color: #1E1C1C; margin: 0; }
        .header { background-color: white; padding: 1rem 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 1.5rem; }
        .header-actions a { color: #666; text-decoration: none; padding: 0.5rem; font-weight: bold; }
        .header-actions a:hover { color: #CF1F3C; }

        .container { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; display: grid; grid-template-columns: 1fr 350px; gap: 2rem; }
        @media (max-width: 900px) {
            .container { grid-template-columns: 1fr; margin: 1rem auto; }
        }

        .card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
        .card-header { font-size: 1.2rem; font-weight: bold; margin-bottom: 1rem; border-bottom: 1px solid #eee; padding-bottom: 0.5rem;}

        .profile-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;}
        .profile-avatar { width: 60px; height: 60px; background-color: #1E1C1C; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; }
        .profile-name { font-size: 1.4rem; font-weight: bold; margin: 0;}
        .profile-meta { color: #666; font-size: 0.9rem; margin-top: 0.25rem;}

        .detail-row { display: flex; margin-bottom: 0.75rem; font-size: 0.95rem; }
        .detail-label { width: 120px; font-weight: bold; color: #555; }
        .detail-value { flex: 1; }

        .btn { background-color: #CF1F3C; color: white; border: none; padding: 0.5rem 1rem; border-radius: 4px; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-block;}
        .btn:hover { background-color: #b01a33; }
        .btn-outline { background-color: transparent; color: #1E1C1C; border: 1px solid #1E1C1C; }
        .btn-outline:hover { background-color: #eee; }

        #error_msg { color: #CF1F3C; text-align: center; font-weight: bold; padding: 2rem; display: none; }
        #loading { text-align: center; padding: 2rem; color: #666; }
    </style>
</head>
<body>

<div class="header">
    <h1>Customer Profile</h1>
    <div class="header-actions">
        <a href="/customers/index.php">&larr; Back to Customers</a>
    </div>
</div>

<div id="loading">Loading customer data...</div>
<div id="error_msg"></div>

<div class="container" id="content" style="display: none;">
    <div>
        <div class="card">
            <div class="profile-header">
                <div class="profile-avatar" id="avatar">C</div>
                <div>
                    <h2 class="profile-name" id="cust_name">Name</h2>
                    <div class="profile-meta" id="cust_meta">Mobile | Email</div>
                </div>
            </div>

            <div class="card-header">Details</div>
            <div class="detail-row">
                <div class="detail-label">City</div>
                <div class="detail-value" id="val_city">-</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Company</div>
                <div class="detail-value" id="val_company">-</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Occupation</div>
                <div class="detail-value" id="val_occupation">-</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Source</div>
                <div class="detail-value" id="val_source">-</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Origin Lead ID</div>
                <div class="detail-value" id="val_lead_id">-</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Customer Since</div>
                <div class="detail-value" id="val_created">-</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Tasks</div>
            <ul class="timeline" id="tasksList" style="list-style: none; padding: 0; margin: 0; margin-bottom: 1.5rem;">
                <!-- Tasks will go here -->
            </ul>
        </div>

        <div class="card">
            <div class="card-header">Share History</div>
            <ul class="timeline" id="sharesList" style="list-style: none; padding: 0; margin: 0;">
                <!-- Share history will go here -->
            </ul>
        </div>
    </div>

    <!-- Sidebar -->
    <div>
        <div class="card">
            <div class="card-header">Quick Actions</div>
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <?php if(hasPermission('properties.view')): ?>
                <a href="/leads/match.php?customer_id=<?php echo $customer_id_safe; ?>" class="btn">Find Matching Properties</a>
                <?php endif; ?>
                <a href="#" class="btn btn-outline" id="action_followup">Add Follow-up</a>
                <a href="#" class="btn btn-outline" onclick="alert('Phase 7 Integration placeholder')">Book Property</a>
                <a href="#" class="btn btn-outline" onclick="alert('Phase 18 Integration placeholder')">Upload Document</a>
            </div>
        </div>

        <!-- Followup form -->
        <div class="card" id="followupFormCard" style="display:none;">
            <div class="card-header">Schedule Follow-up</div>
            <form id="addFollowupForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="customer_id" value="<?php echo $customer_id_safe; ?>">

                <div style="margin-bottom: 0.5rem;">
                    <label>Date *</label><br>
                    <input type="date" name="followup_date" required style="width:100%; padding:0.5rem;">
                </div>
                <div style="margin-bottom: 0.5rem;">
                    <label>Time</label><br>
                    <input type="time" name="followup_time" style="width:100%; padding:0.5rem;">
                </div>
                <div style="margin-bottom: 0.5rem;">
                    <label>Priority</label><br>
                    <select name="priority" style="width:100%; padding:0.5rem;">
                        <option value="Low">Low</option>
                        <option value="Medium" selected>Medium</option>
                        <option value="High">High</option>
                    </select>
                </div>
                <div style="margin-bottom: 0.5rem;">
                    <label>Notes</label><br>
                    <textarea name="notes" style="width:100%; padding:0.5rem;"></textarea>
                </div>
                <button type="submit" class="btn" style="width:100%">Save</button>
            </form>
        </div>
    </div>
</div>

<style>
.timeline-item { position: relative; padding-left: 1.5rem; margin-bottom: 1rem; font-size: 0.9rem; border-left: 2px solid #ddd; }
.timeline-item::before { content: ''; position: absolute; left: -6px; top: 4px; width: 10px; height: 10px; background-color: #CF1F3C; border-radius: 50%; }
.timeline-date { color: #888; font-size: 0.8rem; display: block; margin-bottom: 0.2rem;}
</style>

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
    const custId = <?php echo json_encode($customer_id); ?>;

    fetch('/api/customers.php?action=get&id=' + custId)
        .then(response => response.json())
        .then(res => {
            document.getElementById('loading').style.display = 'none';
            if (res.status === 'success') {
                document.getElementById('content').style.display = 'grid';
                const c = res.data;

                document.getElementById('avatar').textContent = escapeHTML(c.name.charAt(0).toUpperCase());
                document.getElementById('cust_name').textContent = escapeHTML(c.name);
                document.getElementById('cust_meta').textContent = `${escapeHTML(c.mobile)} ${c.email ? '| ' + escapeHTML(c.email) : ''}`;

                document.getElementById('val_city').textContent = escapeHTML(c.city) || '-';
                document.getElementById('val_company').textContent = escapeHTML(c.company) || '-';
                document.getElementById('val_occupation').textContent = escapeHTML(c.occupation) || '-';
                document.getElementById('val_source').textContent = escapeHTML(c.source_name) || '-';

                if (c.lead_id) {
                    document.getElementById('val_lead_id').innerHTML = `<a href="/leads/view.php?id=${escapeHTML(c.lead_id)}">Lead #${escapeHTML(c.lead_id)}</a>`;
                }

                document.getElementById('val_created').textContent = new Date(c.created_at).toLocaleDateString();
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

    // Fetch Tasks
    fetch('/api/tasks.php?action=list&status=Pending')
        .then(response => response.json())
        .then(res => {
            if (res.status === 'success') {
                const tasksList = document.getElementById('tasksList');
                tasksList.innerHTML = '';
                const myTasks = res.data.filter(t => t.related_customer == custId);
                if (myTasks.length > 0) {
                    myTasks.forEach(t => {
                        tasksList.innerHTML += `
                            <li class="timeline-item">
                                <span class="timeline-date">Due: ${escapeHTML(t.due_date)}</span>
                                <strong>${escapeHTML(t.title)}</strong> - ${escapeHTML(t.priority)} Priority
                            </li>
                        `;
                    });
                } else {
                    tasksList.innerHTML = '<li class="timeline-item">No pending tasks for this customer.</li>';
                }
            }
        });

    // Fetch Shares
    fetch('/api/property-sharing.php?action=history&customer_id=' + custId)
        .then(response => response.json())
        .then(res => {
            if (res.status === 'success') {
                const sharesList = document.getElementById('sharesList');
                sharesList.innerHTML = '';
                if (res.data.length > 0) {
                    res.data.forEach(s => {
                        sharesList.innerHTML += `
                            <li class="timeline-item">
                                <span class="timeline-date">${new Date(s.created_at).toLocaleString()} | ${escapeHTML(s.shared_by)}</span>
                                Shared <strong>${escapeHTML(s.properties_shared)} property/ies</strong> via <strong>${escapeHTML(s.channel)}</strong>.<br>
                                <span style="font-size: 0.8rem; color: #666;">Status: ${escapeHTML(s.status)}</span>
                            </li>
                        `;
                    });
                } else {
                    sharesList.innerHTML = '<li class="timeline-item">No properties have been shared yet.</li>';
                }
            }
        });

        // Initialize followup events
        const actionFollowup = document.getElementById('action_followup');
        if(actionFollowup) {
            actionFollowup.addEventListener('click', (e) => {
                e.preventDefault();
                document.getElementById('followupFormCard').style.display = 'block';
            });
        }

        const addFollowupForm = document.getElementById('addFollowupForm');
        if (addFollowupForm) {
            addFollowupForm.addEventListener('submit', function(e) {
                e.preventDefault();
                fetch('/api/followups.php?action=create', {
                    method: 'POST',
                    body: new FormData(this)
                }).then(r => r.json()).then(res => {
                    if (res.status === 'success') {
                        this.reset();
                        document.getElementById('followupFormCard').style.display = 'none';
                        alert('Follow-up scheduled.');
                    } else {
                        alert(res.message);
                    }
                });
            });
        }
});
</script>

</body>
</html>
