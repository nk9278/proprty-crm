<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';

requireLogin();
requirePermission('leads.view');

$lead_id = $_GET['id'] ?? null;
if (!$lead_id) {
    die("Lead ID is required.");
}
$lead_id_safe = htmlspecialchars($lead_id);

// Ensure the page itself initializes the token safely
$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zopa CRM - Lead Profile</title>
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
        .profile-avatar { width: 60px; height: 60px; background-color: #CF1F3C; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; }
        .profile-name { font-size: 1.4rem; font-weight: bold; margin: 0;}
        .profile-meta { color: #666; font-size: 0.9rem; margin-top: 0.25rem;}

        .detail-row { display: flex; margin-bottom: 0.75rem; font-size: 0.95rem; }
        .detail-label { width: 120px; font-weight: bold; color: #555; }
        .detail-value { flex: 1; }

        .timeline { list-style: none; padding: 0; margin: 0; }
        .timeline-item { position: relative; padding-left: 1.5rem; margin-bottom: 1rem; font-size: 0.9rem; border-left: 2px solid #ddd; }
        .timeline-item::before { content: ''; position: absolute; left: -6px; top: 4px; width: 10px; height: 10px; background-color: #CF1F3C; border-radius: 50%; }
        .timeline-date { color: #888; font-size: 0.8rem; display: block; margin-bottom: 0.2rem;}

        .btn { background-color: #CF1F3C; color: white; border: none; padding: 0.5rem 1rem; border-radius: 4px; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-block;}
        .btn:hover { background-color: #b01a33; }
        .btn-outline { background-color: transparent; color: #CF1F3C; border: 1px solid #CF1F3C; }
        .btn-outline:hover { background-color: #fef2f2; }

        #error_msg { color: #CF1F3C; text-align: center; font-weight: bold; padding: 2rem; display: none; }
        #loading { text-align: center; padding: 2rem; color: #666; }
    </style>
</head>
<body>

<div class="header">
    <h1>Lead Profile</h1>
    <div class="header-actions">
        <a href="/leads/index.php">&larr; Back to Leads</a>
    </div>
</div>

<div id="loading">Loading lead data...</div>
<div id="error_msg"></div>

<div class="container" id="content" style="display: none;">
    <!-- Main Column -->
    <div>
        <div class="card">
            <div class="profile-header">
                <div class="profile-avatar" id="avatar">L</div>
                <div>
                    <h2 class="profile-name" id="lead_name">Name</h2>
                    <div class="profile-meta" id="lead_meta">Mobile | Email</div>
                </div>
            </div>

            <div class="card-header">Details</div>
            <div class="detail-row">
                <div class="detail-label">Status</div>
                <div class="detail-value" id="val_status">-</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Source</div>
                <div class="detail-value" id="val_source">-</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">City</div>
                <div class="detail-value" id="val_city">-</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Budget</div>
                <div class="detail-value" id="val_budget">-</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Requirement</div>
                <div class="detail-value" id="val_requirement">-</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Follow-ups</div>
            <ul class="timeline" id="followupsList">
                <!-- Followups will go here -->
            </ul>
        </div>

        <div class="card">
            <div class="card-header">Site Visits</div>
            <div style="margin-bottom: 1rem;">
                <form id="scheduleVisitForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="lead_id" value="<?php echo $lead_id_safe; ?>">
                    <input type="hidden" name="action" value="create">

                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <div><label>Date *</label><br><input type="date" name="scheduled_date" required></div>
                        <div><label>Time *</label><br><input type="time" name="scheduled_time" required></div>
                        <div><label>Visitors</label><br><input type="number" name="visitor_count" value="1" min="1" style="width: 60px;"></div>
                    </div>
                    <div style="margin-top: 0.5rem;">
                        <label>Notes</label><br>
                        <input type="text" name="visitor_names" placeholder="Visitor details..." style="width: 100%;">
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top: 0.5rem;">Schedule Visit</button>
                    <div id="visitMsg" style="margin-top: 0.5rem; font-size: 0.9rem;"></div>
                </form>
            </div>

            <ul class="timeline" id="siteVisitsList" style="list-style: none; padding: 0; margin: 0; margin-bottom: 1.5rem;">
                <!-- Site Visits will go here -->
            </ul>
        </div>

        <div class="card">
            <div class="card-header">Tasks</div>
            <ul class="timeline" id="tasksList">
                <!-- Tasks will go here -->
            </ul>
        </div>

        <div class="card">
            <div class="card-header">Share History</div>
            <ul class="timeline" id="sharesList">
                <!-- Share history will go here -->
            </ul>
        </div>
    </div>

    <!-- Sidebar -->
    <div>
        <div class="card">
            <div class="card-header">Tags</div>
            <div id="tagsList" style="margin-bottom: 1rem;"></div>
            <?php if(hasPermission('leads.edit')): ?>
            <form id="addTagForm" style="display: flex; gap: 0.5rem;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="lead_id" value="<?php echo $lead_id_safe; ?>">
                <input type="text" name="tag_name" placeholder="New Tag" required style="flex:1; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px;">
                <button type="submit" class="btn">Add</button>
            </form>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header">Quick Actions</div>
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <?php if(hasPermission('properties.view')): ?>
                <a href="/leads/match.php?lead_id=<?php echo $lead_id_safe; ?>" class="btn">Find Matching Properties</a>
                <?php endif; ?>
                <a href="#" class="btn btn-outline" id="action_followup">Add Follow-up</a>
                <?php if(hasPermission('whatsapp.send')): ?>
                <a href="#" class="btn btn-outline" onclick="sendWhatsApp()">Send WhatsApp</a>
                <?php endif; ?>
                <?php if(hasPermission('leads.edit')): ?>
                <a href="/leads/edit.php?id=<?php echo $lead_id_safe; ?>" class="btn btn-outline" id="action_edit">Edit Lead</a>
                <?php endif; ?>
                <?php if(hasPermission('leads.assign')): ?>
                <a href="#" class="btn btn-outline" id="action_assign">Assign Lead</a>
                <?php endif; ?>
                <?php if(hasPermission('customers.convert')): ?>
                <form id="convertLeadForm" style="margin: 0; padding: 0;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="lead_id" value="<?php echo $lead_id_safe; ?>">
                    <button type="submit" class="btn btn-outline" style="width: 100%; border-color: green; color: green;" id="btn_convert">Convert to Customer</button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Assign form -->
        <div class="card" id="assignFormCard" style="display:none;">
            <div class="card-header">Assign Lead</div>
            <form id="assignLeadForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="lead_id" value="<?php echo $lead_id_safe; ?>">

                <div style="margin-bottom: 0.5rem;">
                    <label>User ID</label><br>
                    <!-- In a real app this would be a user dropdown list -->
                    <input type="number" name="user_id" required style="width:100%; padding:0.5rem;" placeholder="User ID">
                </div>
                <button type="submit" class="btn" style="width:100%">Assign</button>
            </form>
        </div>

        <!-- Followup form -->
        <div class="card" id="followupFormCard" style="display:none;">
            <div class="card-header">Schedule Follow-up</div>
            <form id="addFollowupForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="lead_id" value="<?php echo $lead_id_safe; ?>">

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

function loadLead() {
    const leadId = <?php echo json_encode($lead_id); ?>;

    fetch('/api/leads.php?action=get&id=' + leadId)
        .then(response => response.json())
        .then(res => {
            document.getElementById('loading').style.display = 'none';
            if (res.status === 'success') {
                document.getElementById('content').style.display = 'grid';
                const lead = res.data;

                document.getElementById('avatar').textContent = escapeHTML(lead.name.charAt(0).toUpperCase());
                document.getElementById('lead_name').textContent = escapeHTML(lead.name);
                document.getElementById('lead_meta').textContent = `${escapeHTML(lead.mobile)} ${lead.email ? '| ' + escapeHTML(lead.email) : ''}`;

                document.getElementById('val_status').textContent = escapeHTML(lead.status_name) || 'Unassigned';
                document.getElementById('val_source').textContent = escapeHTML(lead.source_name) || '-';
                document.getElementById('val_city').textContent = escapeHTML(lead.city) || '-';

                let budget = '';
                if(lead.budget_min) budget += escapeHTML(lead.budget_min);
                if(lead.budget_min && lead.budget_max) budget += ' - ';
                if(lead.budget_max) budget += escapeHTML(lead.budget_max);
                document.getElementById('val_budget').textContent = budget || '-';

                document.getElementById('val_requirement').textContent = escapeHTML(lead.requirement) || '-';

                // Tags
                const tagsList = document.getElementById('tagsList');
                tagsList.innerHTML = '';
                if (lead.tags && lead.tags.length > 0) {
                    lead.tags.forEach(tag => {
                        tagsList.innerHTML += `<span style="display:inline-block; background:${escapeHTML(tag.color)}; color:#333; padding:2px 8px; border-radius:12px; font-size:0.8rem; margin-right:4px; margin-bottom:4px;">${escapeHTML(tag.name)}</span>`;
                    });
                } else {
                    tagsList.innerHTML = '<span style="color:#999; font-size:0.9rem;">No tags</span>';
                }

                // Followups
                const followupsList = document.getElementById('followupsList');
                followupsList.innerHTML = '';
                if (lead.followups && lead.followups.length > 0) {
                    lead.followups.forEach(f => {
                        followupsList.innerHTML += `
                            <li class="timeline-item">
                                <span class="timeline-date">${escapeHTML(f.followup_date)} ${escapeHTML(f.followup_time || '')} | ${escapeHTML(f.status)}</span>
                                <strong>${escapeHTML(f.type_name || 'Follow-up')}</strong> - ${escapeHTML(f.notes)}
                            </li>
                        `;
                    });
                } else {
                    followupsList.innerHTML = '<li class="timeline-item">No follow-ups scheduled.</li>';
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
            errMsg.textContent = 'A network error occurred while loading the lead profile.';
        });

    // Fetch Tasks
    fetch('/api/tasks.php?action=list&status=Pending')
        .then(response => response.json())
        .then(res => {
            if (res.status === 'success') {
                const tasksList = document.getElementById('tasksList');
                tasksList.innerHTML = '';
                const myTasks = res.data.filter(t => t.related_lead == leadId);
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
                    tasksList.innerHTML = '<li class="timeline-item">No pending tasks for this lead.</li>';
                }
            }
        });

    // Fetch Site Visits
    function loadSiteVisits() {
        fetch('/api/site-visits.php?action=list&lead_id=' + leadId)
            .then(response => response.json())
            .then(res => {
                if (res.status === 'success') {
                    const svList = document.getElementById('siteVisitsList');
                    svList.innerHTML = '';
                    if (res.data.length > 0) {
                        res.data.forEach(v => {
                            svList.innerHTML += `
                                <li class="timeline-item">
                                    <span class="timeline-date">${escapeHTML(v.scheduled_date)} ${escapeHTML(v.scheduled_time)}</span>
                                    <strong>Status: ${escapeHTML(v.status)}</strong> - Visitors: ${escapeHTML(v.visitor_count)}<br>
                                    <small>${escapeHTML(v.visitor_names)}</small>
                                </li>
                            `;
                        });
                    } else {
                        svList.innerHTML = '<li class="timeline-item">No site visits scheduled.</li>';
                    }
                }
            });
    }
    loadSiteVisits();

    document.getElementById('scheduleVisitForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        const msg = document.getElementById('visitMsg');

        fetch('/api/site-visits.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if(res.status === 'success') {
                    msg.style.color = 'green';
                    msg.textContent = 'Site visit scheduled!';
                    this.reset();
                    loadSiteVisits();
                } else {
                    msg.style.color = 'red';
                    msg.textContent = res.message;
                }
            });
    });

    function sendWhatsApp() {
        const msg = prompt("Enter your WhatsApp message:");
        if (!msg) return;

        const fd = new FormData();
        fd.append('action', 'send');
        fd.append('lead_id', leadId);
        fd.append('content', msg);
        fd.append('csrf_token', csrfToken);

        fetch('/api/whatsapp.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                alert(res.message); // Should alert mock failure
            });
    }

    // Fetch Shares
    fetch('/api/property-sharing.php?action=history&lead_id=' + leadId)
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
}

document.addEventListener('DOMContentLoaded', () => {
    loadLead();

    document.getElementById('action_followup').addEventListener('click', (e) => {
        e.preventDefault();
        document.getElementById('followupFormCard').style.display = 'block';
        document.getElementById('assignFormCard').style.display = 'none';
    });

    const actionAssignBtn = document.getElementById('action_assign');
    if (actionAssignBtn) {
        actionAssignBtn.addEventListener('click', (e) => {
            e.preventDefault();
            document.getElementById('assignFormCard').style.display = 'block';
            document.getElementById('followupFormCard').style.display = 'none';
        });
    }

    const assignLeadForm = document.getElementById('assignLeadForm');
    if (assignLeadForm) {
        assignLeadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            fetch('/api/leads.php?action=assign', {
                method: 'POST',
                body: new FormData(this)
            }).then(r => r.json()).then(res => {
                if (res.status === 'success') {
                    this.reset();
                    document.getElementById('assignFormCard').style.display = 'none';
                    loadLead();
                } else {
                    alert(res.message);
                }
            });
        });
    }

    const convertLeadForm = document.getElementById('convertLeadForm');
    if (convertLeadForm) {
        convertLeadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            if (!confirm('Are you sure you want to convert this lead to a customer?')) return;

            const btn = document.getElementById('btn_convert');
            btn.disabled = true;
            btn.textContent = 'Converting...';

            fetch('/api/leads.php?action=convert', {
                method: 'POST',
                body: new FormData(this)
            }).then(r => r.json()).then(res => {
                if (res.status === 'success') {
                    window.location.href = '/customers/view.php?id=' + res.customer_id;
                } else {
                    alert(res.message);
                    btn.disabled = false;
                    btn.textContent = 'Convert to Customer';
                }
            });
        });
    }

    const addTagForm = document.getElementById('addTagForm');
    if (addTagForm) {
        addTagForm.addEventListener('submit', function(e) {
            e.preventDefault();
            fetch('/api/leads.php?action=add_tag', {
                method: 'POST',
                body: new FormData(this)
            }).then(r => r.json()).then(res => {
                if (res.status === 'success') {
                    this.reset();
                    loadLead();
                } else {
                    alert(res.message);
                }
            });
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
                    loadLead();
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
