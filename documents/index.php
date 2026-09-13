<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';

requireLogin();
requirePermission('documents.view');

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token); ?>">
    <title>Zopa CRM - Document Management</title>
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
        .badge.Active { background: #dcfce7; color: #166534; }
        .badge.Archived { background: #fef08a; color: #854d0e; }

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
    <h1>Document Vault</h1>
    <div class="header-nav">
        <a href="/dashboard.php" class="btn btn-primary">Dashboard</a>
    </div>
</div>

<div class="container">
    <div class="card">
        <div class="card-header">All Documents</div>
        <table>
            <thead>
                <tr>
                    <th>Filename</th>
                    <th>Category</th>
                    <th>Related Entity</th>
                    <th>Status</th>
                    <th>Uploaded By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="documentsList">
                <tr><td colspan="6" style="text-align:center;">Loading...</td></tr>
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

function loadDocuments() {
    fetch('/api/documents.php?action=list')
        .then(r => r.json())
        .then(res => {
            const tbody = document.getElementById('documentsList');
            tbody.innerHTML = '';
            if (res.status === 'success' && res.data.length > 0) {
                res.data.forEach(d => {
                    const sizeMB = (d.file_size / (1024*1024)).toFixed(2);
                    tbody.innerHTML += `
                        <tr>
                            <td data-label="Filename"><strong>${escapeHTML(d.original_filename)}</strong><br><small>${sizeMB} MB</small></td>
                            <td data-label="Category">${escapeHTML(d.category)}<br><small>${escapeHTML(d.document_type)}</small></td>
                            <td data-label="Related Entity">${escapeHTML(d.entity_type)} ID: ${escapeHTML(d.entity_id)}</td>
                            <td data-label="Status"><span class="badge ${escapeHTML(d.status)}">${escapeHTML(d.status)}</span></td>
                            <td data-label="Uploaded By">${escapeHTML(d.uploaded_by_name)}<br><small>${escapeHTML(d.created_at)}</small></td>
                            <td data-label="Actions">
                                <a href="/api/documents.php?action=download&id=${d.id}" class="btn btn-primary" target="_blank" style="font-size:0.8rem;">Download</a>
                            </td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No documents found.</td></tr>';
            }
        });
}

document.addEventListener('DOMContentLoaded', loadDocuments);
</script>

</body>
</html>
