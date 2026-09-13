<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';

requireLogin();
requirePermission('reviews.view');

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token); ?>">
    <title>Zopa CRM - Reviews</title>
    <style>
        body { font-family: sans-serif; background-color: #F1EDED; color: #1E1C1C; margin: 0; display: flex; flex-direction: column; height: 100vh; }
        .header { background-color: white; padding: 1rem 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 1.5rem; }
        .container { flex: 1; padding: 2rem; overflow-y: auto; max-width: 1200px; margin: 0 auto; width: 100%; box-sizing: border-box; }
        .card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
        .card-header { font-size: 1.25rem; font-weight: bold; margin-bottom: 1rem; border-bottom: 2px solid #F1EDED; padding-bottom: 0.5rem; }

        .btn { padding: 0.25rem 0.5rem; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.8rem; display: inline-block;}
        .btn-success { background-color: #166534; color: white; }
        .btn-danger { background-color: #991b1b; color: white; }
        .btn-primary { background-color: #CF1F3C; color: white; padding: 0.5rem 1rem; font-size: 0.9rem;}

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #eee; vertical-align: top; }
        th { background-color: #f9f9f9; }

        .badge { padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem; background: #e2e8f0; }
        .badge.Pending { background: #fef08a; color: #854d0e; }
        .badge.Approved { background: #dcfce7; color: #166534; }
        .badge.Rejected { background: #fee2e2; color: #991b1b; }

        .star { color: #f59e0b; font-size: 1.2rem; }

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
    <h1>Customer Reviews</h1>
    <div class="header-nav">
        <a href="/dashboard.php" class="btn btn-primary">Dashboard</a>
    </div>
</div>

<div class="container">
    <div class="card">
        <div class="card-header">All Reviews</div>
        <table>
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Target</th>
                    <th>Rating / Review</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="reviewsList">
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

function renderStars(rating) {
    let html = '';
    for(let i=0; i<rating; i++) html += '<span class="star">★</span>';
    return html;
}

function loadReviews() {
    fetch('/api/reviews.php?action=list')
        .then(r => r.json())
        .then(res => {
            const tbody = document.getElementById('reviewsList');
            tbody.innerHTML = '';
            if (res.status === 'success' && res.data.length > 0) {
                res.data.forEach(r => {
                    let actions = '';
                    <?php if(hasPermission('reviews.moderate')): ?>
                    if (r.status === 'Pending') {
                        actions = `
                            <button class="btn btn-success" onclick="moderateReview(${r.id}, 'Approved')">Approve</button>
                            <button class="btn btn-danger" onclick="moderateReview(${r.id}, 'Rejected')">Reject</button>
                        `;
                    }
                    <?php endif; ?>

                    tbody.innerHTML += `
                        <tr>
                            <td data-label="Customer"><strong>${escapeHTML(r.customer_name)}</strong><br><small>${escapeHTML(r.created_at)}</small></td>
                            <td data-label="Target">${escapeHTML(r.target_type)} ID: ${escapeHTML(r.target_id)}</td>
                            <td data-label="Rating / Review">
                                <div>${renderStars(r.rating)}</div>
                                <div style="margin-top:0.5rem; font-style:italic;">"${escapeHTML(r.review_text)}"</div>
                            </td>
                            <td data-label="Status"><span class="badge ${escapeHTML(r.status)}">${escapeHTML(r.status)}</span></td>
                            <td data-label="Actions">${actions}</td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No reviews found.</td></tr>';
            }
        });
}

function moderateReview(id, status) {
    if (!confirm('Mark review as ' + status + '?')) return;

    const fd = new FormData();
    fd.append('action', 'moderate');
    fd.append('id', id);
    fd.append('status', status);
    fd.append('csrf_token', csrfToken);

    fetch('/api/reviews.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if(res.status === 'success') loadReviews();
            else alert(res.message);
        });
}

document.addEventListener('DOMContentLoaded', loadReviews);
</script>

</body>
</html>
