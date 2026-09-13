<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/rbac.php';

requireLogin();

// This dashboard aggregates basic info. We'll use JS to fetch components.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
    <title>Zopa CRM - Dashboard</title>
    <style>
        body { font-family: sans-serif; background-color: #F1EDED; color: #1E1C1C; margin: 0; }
        .header { background-color: white; padding: 1rem 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 1.5rem; }
        .header-nav a { margin-left: 1rem; color: #1E1C1C; text-decoration: none; font-weight: bold; }
        .header-nav a:hover { color: #CF1F3C; }

        .container { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        @media (max-width: 768px) {
            .container { grid-template-columns: 1fr; }
        }

        .card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .card-header { font-size: 1.2rem; font-weight: bold; margin-bottom: 1rem; border-bottom: 1px solid #eee; padding-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center;}

        ul.list { list-style: none; padding: 0; margin: 0; }
        ul.list li { padding: 0.75rem 0; border-bottom: 1px solid #eee; font-size: 0.95rem; }
        ul.list li:last-child { border-bottom: none; }

        .badge { display: inline-block; padding: 0.2rem 0.5rem; border-radius: 12px; font-size: 0.8rem; font-weight: bold; }
        .badge.urgent { background-color: #fee2e2; color: #991b1b; }
        .badge.normal { background-color: #f1f5f9; color: #334155; }

        .link { color: #CF1F3C; text-decoration: none; font-weight: bold; }
        .link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="header">
    <h1>Zopa CRM Dashboard</h1>
    <div class="header-nav">
        <a href="/leads/index.php">Leads</a>
        <a href="/customers/index.php">Customers</a>
        <a href="/pipeline/index.php">Pipeline</a>
        <a href="/properties/index.php">Inventory</a>
        <a href="/site_visits/index.php">Site Visits</a>
        <a href="/channel_partners/index.php">Partners</a>
        <a href="/marketing/index.php">Marketing</a>
        <a href="/reports/index.php">Reports</a>
        <a href="/documents/index.php">Documents</a>
        <a href="/support/index.php">Support</a>
        <a href="/tasks/index.php">Tasks</a>
        <?php if(hasPermission('Tenant Owner')): ?>
        <a href="/settings/index.php">Settings</a>
        <?php endif; ?>
    </div>
</div>

<div class="container">
    <div class="card">
        <div class="card-header">
            <span>My Tasks (Pending)</span>
            <a href="/tasks/index.php" style="font-size:0.9rem; font-weight:normal;">View All</a>
        </div>
        <ul class="list" id="tasksList">
            <li>Loading tasks...</li>
        </ul>
    </div>

    <div class="card">
        <div class="card-header">
            <span>Unread Notifications</span>
            <button onclick="markAllRead()" style="font-size:0.8rem; border:1px solid #ccc; background:#fff; padding:0.2rem 0.5rem; cursor:pointer;">Mark All Read</button>
        </div>
        <ul class="list" id="notifList">
            <li>Loading notifications...</li>
        </ul>
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
    loadTasks();
    loadNotifications();
});

function loadTasks() {
    fetch('/api/tasks.php?action=list&status=Pending')
        .then(r => r.json())
        .then(res => {
            const list = document.getElementById('tasksList');
            list.innerHTML = '';
            if (res.status === 'success' && res.data.length > 0) {
                res.data.slice(0, 5).forEach(t => {
                    const badge = t.priority === 'Urgent' || t.priority === 'High' ? 'urgent' : 'normal';
                    list.innerHTML += `
                        <li>
                            <span class="badge ${badge}">${escapeHTML(t.priority)}</span>
                            <strong>${escapeHTML(t.title)}</strong><br>
                            <span style="font-size:0.85rem; color:#666;">Due: ${escapeHTML(t.due_date)}</span>
                        </li>
                    `;
                });
            } else {
                list.innerHTML = '<li style="color:#666;">No pending tasks.</li>';
            }
        });
}

function loadNotifications() {
    fetch('/api/notifications.php?action=list_unread')
        .then(r => r.json())
        .then(res => {
            const list = document.getElementById('notifList');
            list.innerHTML = '';
            if (res.status === 'success' && res.data.length > 0) {
                res.data.slice(0, 5).forEach(n => {
                    list.innerHTML += `
                        <li>
                            <strong>${escapeHTML(n.title)}</strong><br>
                            <span style="font-size:0.9rem;">${escapeHTML(n.message)}</span><br>
                            <span style="font-size:0.75rem; color:#888;">${new Date(n.created_at).toLocaleString()}</span>
                        </li>
                    `;
                });
            } else {
                list.innerHTML = '<li style="color:#666;">No unread notifications.</li>';
            }
        });
}

function markAllRead() {
    const formData = new FormData();
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    formData.append('csrf_token', csrfToken);
    fetch('/api/notifications.php?action=mark_all_read', {
        method: 'POST',
        body: formData
    }).then(r => r.json()).then(res => {
        if(res.status === 'success') loadNotifications();
    });
}
</script>

</body>
</html>
