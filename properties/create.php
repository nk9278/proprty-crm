<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';

requireLogin();
requirePermission('properties.create');

$csrf_token = generateCsrfToken();

$pdo = getDB();
$tenant_id = current_tenant_id();

// Pre-fetch some dropdown lists to populate the form options
$categories = $pdo->query("SELECT id, name FROM property_categories")->fetchAll();
$types = $pdo->query("SELECT id, category_id, name FROM property_types")->fetchAll();
$projects = $pdo->prepare("SELECT id, name FROM projects WHERE tenant_id = ? AND deleted_at IS NULL");
$projects->execute([$tenant_id]);
$projectsList = $projects->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zopa CRM - Add Property</title>
    <style>
        body { font-family: sans-serif; background-color: #F1EDED; color: #1E1C1C; margin: 0; }
        .header { background-color: white; padding: 1rem 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 1.5rem; }
        .header-actions a { color: #666; text-decoration: none; padding: 0.5rem; font-weight: bold; }
        .header-actions a:hover { color: #CF1F3C; }

        .container { max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .card-header { font-size: 1.2rem; font-weight: bold; margin-bottom: 1.5rem; border-bottom: 1px solid #eee; padding-bottom: 0.5rem;}

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .container { margin: 1rem auto; }
            .card { padding: 1rem; }
        }

        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 0.5rem; font-weight: bold; font-size: 0.9rem; }
        .form-group input, .form-group select { padding: 0.75rem; border: 1px solid #ccc; border-radius: 4px; font-family: inherit; }

        .full-width { grid-column: 1 / -1; }

        .btn-submit { background-color: #CF1F3C; color: white; border: none; padding: 0.75rem 2rem; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 1rem; width: 100%;}
        .btn-submit:hover { background-color: #b01a33; }

        #message { margin-top: 1rem; padding: 1rem; border-radius: 4px; display: none; text-align: center; font-weight: bold;}
        .success { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .error { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    </style>
</head>
<body>

<div class="header">
    <h1>Add Property</h1>
    <div class="header-actions">
        <a href="/properties/index.php">&larr; Back to Inventory</a>
    </div>
</div>

<div class="container">
    <div class="card">
        <form id="createPropertyForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

            <div class="form-grid">
                <div class="form-group full-width">
                    <label for="name">Property Name / Title *</label>
                    <input type="text" id="name" name="name" required placeholder="e.g. 2BHK Ocean View">
                </div>

                <div class="form-group">
                    <label for="project_id">Project (Optional)</label>
                    <select id="project_id" name="project_id">
                        <option value="">-- Standalone / No Project --</option>
                        <?php foreach($projectsList as $prj): ?>
                        <option value="<?php echo htmlspecialchars($prj['id']); ?>"><?php echo htmlspecialchars($prj['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="purpose">Purpose *</label>
                    <select id="purpose" name="purpose" required>
                        <option value="For Sale">For Sale</option>
                        <option value="For Rent">For Rent</option>
                        <option value="For Lease">For Lease</option>
                        <option value="For Resale">For Resale</option>
                        <option value="For Investment">For Investment</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="category_id">Category *</label>
                    <select id="category_id" name="category_id" required>
                        <option value="">Select Category</option>
                        <?php foreach($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['id']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="type_id">Property Type *</label>
                    <select id="type_id" name="type_id" required>
                        <option value="">Select Type</option>
                        <!-- Filtered dynamically by category via JS below -->
                        <?php foreach($types as $type): ?>
                        <option value="<?php echo htmlspecialchars($type['id']); ?>" data-cat="<?php echo htmlspecialchars($type['category_id']); ?>">
                            <?php echo htmlspecialchars($type['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <br>
            <button type="submit" class="btn-submit" id="submitBtn">Save Property</button>
        </form>
        <div id="message"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {

    // Simple category -> type dynamic filter
    const catSelect = document.getElementById('category_id');
    const typeSelect = document.getElementById('type_id');
    const allTypeOptions = Array.from(typeSelect.options);

    catSelect.addEventListener('change', function() {
        const catId = this.value;
        // reset type
        typeSelect.innerHTML = '<option value="">Select Type</option>';
        if (catId) {
            allTypeOptions.forEach(opt => {
                if (opt.getAttribute('data-cat') === catId) {
                    typeSelect.appendChild(opt.cloneNode(true));
                }
            });
        }
    });

    document.getElementById('createPropertyForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = this;
        const btn = document.getElementById('submitBtn');
        const msgDiv = document.getElementById('message');

        btn.disabled = true;
        btn.textContent = 'Saving...';
        msgDiv.style.display = 'none';

        fetch('/api/properties.php?action=create_property', {
            method: 'POST',
            body: new FormData(form)
        })
        .then(r => r.json())
        .then(data => {
            msgDiv.style.display = 'block';
            if (data.status === 'success') {
                msgDiv.className = 'success';
                msgDiv.textContent = data.message;
                form.reset();
                setTimeout(() => {
                    window.location.href = '/properties/view.php?id=' + data.data.id;
                }, 1000);
            } else {
                msgDiv.className = 'error';
                msgDiv.textContent = data.message;
                btn.disabled = false;
                btn.textContent = 'Save Property';
            }
        })
        .catch(err => {
            msgDiv.style.display = 'block';
            msgDiv.className = 'error';
            msgDiv.textContent = 'A network error occurred.';
            btn.disabled = false;
            btn.textContent = 'Save Property';
        });
    });
});
</script>

</body>
</html>
