<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';

requireLogin();
requirePermission('leads.edit');

$lead_id = (int)($_GET['id'] ?? 0);
if (!$lead_id) {
    die("Lead ID is required.");
}

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zopa CRM - Edit Lead</title>
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
        #loading { text-align: center; padding: 2rem; color: #666; }
    </style>
</head>
<body>

<div class="header">
    <h1>Edit Lead</h1>
    <div class="header-actions">
        <a href="/leads/view.php?id=<?php echo $lead_id; ?>" id="backLink">&larr; Back to Profile</a>
    </div>
</div>

<div class="container">
    <div id="loading">Loading lead data...</div>
    <div class="card" id="formCard" style="display:none;">
        <form id="editLeadForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="id" value="<?php echo $lead_id; ?>">

            <div class="form-grid">
                <div class="form-group">
                    <label for="name">Full Name *</label>
                    <input type="text" id="name" name="name" required>
                </div>

                <div class="form-group">
                    <label for="mobile">Mobile Number</label>
                    <input type="text" id="mobile" name="mobile" pattern="[0-9]{10,15}">
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

            <button type="submit" class="btn-submit" id="submitBtn">Update Lead</button>
        </form>
        <div id="message"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const leadId = <?php echo json_encode($lead_id); ?>;

    // Fetch initial data
    fetch('/api/leads.php?action=get&id=' + leadId)
        .then(response => response.json())
        .then(res => {
            document.getElementById('loading').style.display = 'none';
            if (res.status === 'success') {
                document.getElementById('formCard').style.display = 'block';
                const lead = res.data;

                // Populate form
                document.getElementById('name').value = lead.name || '';
                document.getElementById('mobile').value = lead.mobile || '';
                document.getElementById('email').value = lead.email || '';
                document.getElementById('city').value = lead.city || '';
                document.getElementById('budget_min').value = lead.budget_min || '';
                document.getElementById('budget_max').value = lead.budget_max || '';
                document.getElementById('requirement').value = lead.requirement || '';

            } else {
                alert(res.message);
            }
        });

    // Handle submission
    document.getElementById('editLeadForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = this;
        const btn = document.getElementById('submitBtn');
        const msgDiv = document.getElementById('message');

        btn.disabled = true;
        btn.textContent = 'Updating...';
        msgDiv.style.display = 'none';

        const formData = new FormData(form);

        fetch('/api/leads.php?action=update', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            msgDiv.style.display = 'block';
            if (data.status === 'success') {
                msgDiv.className = 'success';
                msgDiv.textContent = data.message;
                setTimeout(() => {
                    window.location.href = '/leads/view.php?id=' + leadId;
                }, 1000);
            } else {
                msgDiv.className = 'error';
                msgDiv.textContent = data.message;
                btn.disabled = false;
                btn.textContent = 'Update Lead';
            }
        })
        .catch(err => {
            msgDiv.style.display = 'block';
            msgDiv.className = 'error';
            msgDiv.textContent = 'A network error occurred.';
            btn.disabled = false;
            btn.textContent = 'Update Lead';
        });
    });
});
</script>

</body>
</html>