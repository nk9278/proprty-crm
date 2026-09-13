<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';

requireLogin();
requirePermission('leads.create');

// Setup CSRF token for the page
$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zopa CRM - Create Lead</title>
    <style>
        body { font-family: sans-serif; background-color: #F1EDED; color: #1E1C1C; margin: 0; }
        .header { background-color: white; padding: 1rem 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 1.5rem; }
        .header-actions a { color: #666; text-decoration: none; padding: 0.5rem; font-weight: bold; }
        .header-actions a:hover { color: #CF1F3C; }

        .container { max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .container { margin: 1rem auto; }
            .card { padding: 1rem; }
        }

        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 0.5rem; font-weight: bold; font-size: 0.9rem; }
        .form-group input, .form-group select, .form-group textarea { padding: 0.75rem; border: 1px solid #ccc; border-radius: 4px; font-family: inherit; }
        .form-group textarea { resize: vertical; min-height: 80px; }

        .full-width { grid-column: 1 / -1; }

        .btn-submit { background-color: #CF1F3C; color: white; border: none; padding: 0.75rem 2rem; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 1rem; margin-top: 1rem; width: 100%;}
        .btn-submit:hover { background-color: #b01a33; }

        #message { margin-top: 1rem; padding: 1rem; border-radius: 4px; display: none; text-align: center; font-weight: bold;}
        .success { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .error { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    </style>
</head>
<body>

<div class="header">
    <h1>Create Lead</h1>
    <div class="header-actions">
        <a href="/leads/index.php">&larr; Back to Leads</a>
    </div>
</div>

<div class="container">
    <div class="card">
        <form id="createLeadForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

            <div class="form-grid">
                <div class="form-group">
                    <label for="name">Full Name *</label>
                    <input type="text" id="name" name="name" required>
                </div>

                <div class="form-group">
                    <label for="mobile">Mobile Number *</label>
                    <input type="text" id="mobile" name="mobile" required pattern="[0-9]{10,15}">
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email">
                </div>

                <div class="form-group">
                    <label for="city">City</label>
                    <input type="text" id="city" name="city">
                </div>

                <div class="form-group">
                    <label for="budget_min">Min Budget</label>
                    <input type="number" id="budget_min" name="budget_min" step="1000">
                </div>

                <div class="form-group">
                    <label for="budget_max">Max Budget</label>
                    <input type="number" id="budget_max" name="budget_max" step="1000">
                </div>

                <div class="form-group full-width">
                    <label for="requirement">Requirement Notes</label>
                    <textarea id="requirement" name="requirement"></textarea>
                </div>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">Save Lead</button>
        </form>
        <div id="message"></div>
    </div>
</div>

<script>
document.getElementById('createLeadForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = this;
    const btn = document.getElementById('submitBtn');
    const msgDiv = document.getElementById('message');

    btn.disabled = true;
    btn.textContent = 'Saving...';
    msgDiv.style.display = 'none';

    const formData = new FormData(form);

    fetch('/api/leads.php?action=create', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        msgDiv.style.display = 'block';
        if (data.status === 'success') {
            msgDiv.className = 'success';
            msgDiv.textContent = data.message;
            form.reset();
            // Redirect after a short delay
            setTimeout(() => {
                window.location.href = '/leads/view.php?id=' + data.data.id;
            }, 1000);
        } else {
            msgDiv.className = 'error';
            msgDiv.textContent = data.message;
            btn.disabled = false;
            btn.textContent = 'Save Lead';
        }
    })
    .catch(err => {
        msgDiv.style.display = 'block';
        msgDiv.className = 'error';
        msgDiv.textContent = 'A network error occurred.';
        btn.disabled = false;
        btn.textContent = 'Save Lead';
    });
});
</script>

</body>
</html>
