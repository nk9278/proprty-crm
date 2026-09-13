<?php
require_once __DIR__ . '/../includes/session.php';
requireLogin();

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zopa CRM - Notifications</title>
    <style>
        body { font-family: sans-serif; background-color: #F1EDED; color: #1E1C1C; margin: 0; display: flex; flex-direction: column; height: 100vh; }
        .header { background-color: white; padding: 1rem 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 1.5rem; }
        .container { max-width: 800px; margin: 2rem auto; padding: 0 1rem; flex: 1; }
        .card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }

        ul.list { list-style: none; padding: 0; margin: 0; }
        ul.list li { padding: 1rem 0; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;}
        ul.list li:last-child { border-bottom: none; }

        .btn-outline { background-color: transparent; color: #1E1C1C; border: 1px solid #1E1C1C; padding: 0.3rem 0.6rem; border-radius: 4px; cursor: pointer;}
        .btn-outline:hover { background-color: #eee; }
    </style>
</head>
<body>
<div class="header">
    <h1>Notifications</h1>
    <a href="/dashboard.php" style="color:#1E1C1C; text-decoration:none; font-weight:bold;">&larr; Back to Dashboard</a>
</div>
<div class="container">
    <div class="card">
        <div style="display:flex; justify-content:space-between; margin-bottom: 1rem; align-items: center;">
            <h2 style="margin:0;">Unread Notifications</h2>
            <button class="btn-outline" onclick="markAllRead()">Mark All Read</button>
        </div>
        <ul class="list" id="notifList">
            <li>Loading...</li>
        </ul>
    </div>
</div>

<script>
const csrfToken = "<?php echo htmlspecialchars($csrf_token); ?>";

function escapeHTML(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

document.addEventListener('DOMContentLoaded', fetchNotifs);

function fetchNotifs() {
    fetch('/api/notifications.php?action=list_unread')
        .then(response => response.json())
        .then(res => {
            const list = document.getElementById('notifList');
            list.innerHTML = '';
            if (res.status === 'success' && res.data.length > 0) {
                res.data.forEach(n => {
                    list.innerHTML += `
                        <li>
                            <div>
                                <strong>${escapeHTML(n.title)}</strong><br>
                                <span style="font-size:0.95rem;">${escapeHTML(n.message)}</span><br>
                                <span style="font-size:0.8rem; color:#888;">${new Date(n.created_at).toLocaleString()}</span>
                            </div>
                            <button class="btn-outline" onclick="markRead(${n.id})">Mark Read</button>
                        </li>
                    `;
                });
            } else {
                list.innerHTML = '<li style="text-align:center; color:#666; width:100%; display:block;">No unread notifications.</li>';
            }
        });
}

function markRead(id) {
    const fd = new FormData();
    fd.append('csrf_token', csrfToken);
    fd.append('id', id);
    fetch('/api/notifications.php?action=mark_read', {
        method: 'POST', body: fd
    }).then(() => fetchNotifs());
}

function markAllRead() {
    const fd = new FormData();
    fd.append('csrf_token', csrfToken);
    fetch('/api/notifications.php?action=mark_all_read', {
        method: 'POST', body: fd
    }).then(() => fetchNotifs());
}
</script>
</body>
</html>
