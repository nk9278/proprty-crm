<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';

requireLogin();
requirePermission('bookings.create');
requirePermission('financials.edit');

$csrf_token = generateCsrfToken();
$customer_id = $_GET['customer_id'] ?? '';
$unit_id = $_GET['unit_id'] ?? '';

// Pass safely to UI
$customer_id_safe = htmlspecialchars($customer_id);
$unit_id_safe = htmlspecialchars($unit_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token); ?>">
    <title>Zopa CRM - Create Booking</title>
    <style>
        body { font-family: sans-serif; background-color: #F1EDED; color: #1E1C1C; margin: 0; }
        .header { background-color: white; padding: 1rem 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 1.5rem; }
        .container { max-width: 900px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
        .card-header { font-size: 1.25rem; font-weight: bold; margin-bottom: 1rem; border-bottom: 2px solid #F1EDED; padding-bottom: 0.5rem; }

        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
        .form-group { display: flex; flex-direction: column; gap: 0.5rem; }
        label { font-weight: bold; font-size: 0.9rem; }
        input[type="text"], input[type="number"], input[type="date"], select, textarea { padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box; }

        .btn { padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 1rem; }
        .btn-primary { background-color: #CF1F3C; color: white; }
        .btn-primary:hover { background-color: #b01a33; }
        .btn-outline { background-color: white; color: #1E1C1C; border: 1px solid #ccc; }

        .cost-sheet-preview { background: #f8fafc; padding: 1rem; border-radius: 4px; border: 1px solid #cbd5e1; margin-top: 1rem; }
        .cs-row { display: flex; justify-content: space-between; padding: 0.25rem 0; border-bottom: 1px solid #e2e8f0; font-size: 0.9rem;}
        .cs-row.total { font-weight: bold; font-size: 1.1rem; border-top: 2px solid #cbd5e1; padding-top: 0.5rem; border-bottom: none; }

        .error { color: #CF1F3C; font-weight: bold; margin-bottom: 1rem; }
    </style>
</head>
<body>

<div class="header">
    <h1>Create Booking & Cost Sheet</h1>
    <div class="header-nav">
        <a href="/customers/index.php" class="btn btn-outline">Back to Customers</a>
    </div>
</div>

<div class="container">
    <div id="errorBox" class="error" style="display:none;"></div>

    <form id="bookingForm">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <input type="hidden" name="action" value="create">

        <div class="card">
            <div class="card-header">Booking Details</div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Customer ID *</label>
                    <input type="number" name="customer_id" value="<?php echo $customer_id_safe; ?>" required>
                </div>
                <div class="form-group">
                    <label>Unit ID *</label>
                    <input type="number" name="unit_id" value="<?php echo $unit_id_safe; ?>" required>
                </div>
                <div class="form-group">
                    <label>Booking Date *</label>
                    <input type="date" name="booking_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="Token Pending">Token Pending</option>
                        <option value="Token Received">Token Received</option>
                        <option value="Booked">Booked</option>
                        <option value="Draft">Draft</option>
                    </select>
                </div>
            </div>
            <div class="form-group" style="margin-top: 1rem;">
                <label>Notes</label>
                <textarea name="notes" rows="3"></textarea>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Cost Sheet (Numbers Only)</div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Base Price *</label>
                    <input type="number" step="0.01" name="base_price" class="cs-input" value="0" required>
                </div>
                <div class="form-group">
                    <label>PLC</label>
                    <input type="number" step="0.01" name="plc" class="cs-input" value="0">
                </div>
                <div class="form-group">
                    <label>Floor Rise</label>
                    <input type="number" step="0.01" name="floor_rise" class="cs-input" value="0">
                </div>
                <div class="form-group">
                    <label>Parking</label>
                    <input type="number" step="0.01" name="parking" class="cs-input" value="0">
                </div>
                <div class="form-group">
                    <label>Club Charges</label>
                    <input type="number" step="0.01" name="club_charges" class="cs-input" value="0">
                </div>
                <div class="form-group">
                    <label>Maintenance</label>
                    <input type="number" step="0.01" name="maintenance" class="cs-input" value="0">
                </div>
                <div class="form-group">
                    <label>EDC / IDC</label>
                    <input type="number" step="0.01" name="edc" class="cs-input" value="0">
                </div>
                <div class="form-group">
                    <label>GST</label>
                    <input type="number" step="0.01" name="gst" class="cs-input" value="0">
                </div>
                <div class="form-group">
                    <label>Other Charges</label>
                    <input type="number" step="0.01" name="other_charges" class="cs-input" value="0">
                </div>
                <div class="form-group">
                    <label>Discount</label>
                    <input type="number" step="0.01" name="discount" class="cs-input" value="0">
                </div>
            </div>

            <div class="form-grid" style="margin-top: 1rem;">
                <div class="form-group">
                    <label>Token Amount Received</label>
                    <input type="number" step="0.01" name="token_amount" value="0">
                </div>
            </div>

            <div class="cost-sheet-preview" id="csPreview">
                <div class="cs-row"><span>Base Price:</span> <span id="p_base">0.00</span></div>
                <div class="cs-row"><span>Total Additions:</span> <span id="p_adds">0.00</span></div>
                <div class="cs-row"><span>Discount:</span> <span id="p_disc">0.00</span></div>
                <div class="cs-row total"><span>Final Payable Amount:</span> <span id="p_final">0.00</span></div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; font-size: 1.1rem; padding: 1rem;">Confirm & Create Booking</button>
    </form>
</div>

<script>
// Dynamic Cost Sheet calculation preview
const csInputs = document.querySelectorAll('.cs-input');
function updatePreview() {
    let base = parseFloat(document.querySelector('input[name="base_price"]').value) || 0;
    let disc = parseFloat(document.querySelector('input[name="discount"]').value) || 0;

    let additions = 0;
    ['plc','floor_rise','parking','club_charges','maintenance','edc','gst','other_charges'].forEach(n => {
        additions += parseFloat(document.querySelector(`input[name="${n}"]`).value) || 0;
    });

    document.getElementById('p_base').textContent = base.toFixed(2);
    document.getElementById('p_adds').textContent = additions.toFixed(2);
    document.getElementById('p_disc').textContent = disc.toFixed(2);

    let final = (base + additions) - disc;
    if (final < 0) final = 0;

    document.getElementById('p_final').textContent = final.toFixed(2);
}

csInputs.forEach(i => i.addEventListener('input', updatePreview));

document.getElementById('bookingForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);

    fetch('/api/bookings.php', {
        method: 'POST',
        body: fd
    }).then(r => r.json()).then(res => {
        if (res.status === 'success') {
            alert('Booking created successfully! Reference ID: ' + res.data.id);
            window.location.href = '/dashboard.php';
        } else {
            const errBox = document.getElementById('errorBox');
            errBox.style.display = 'block';
            errBox.textContent = res.message;
            window.scrollTo(0,0);
        }
    }).catch(e => {
        alert('Network Error occurred.');
    });
});
</script>

</body>
</html>
