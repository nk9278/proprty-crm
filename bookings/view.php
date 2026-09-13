<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';

requireLogin();
requirePermission('bookings.view');

$id = $_GET['id'] ?? '';
$id_safe = htmlspecialchars($id);
$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token); ?>">
    <title>Zopa CRM - View Booking</title>
    <style>
        body { font-family: sans-serif; background-color: #F1EDED; color: #1E1C1C; margin: 0; }
        .header { background-color: white; padding: 1rem 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 1.5rem; }
        .container { max-width: 1000px; margin: 2rem auto; padding: 0 1rem; display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; }

        .card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
        .card-header { font-size: 1.25rem; font-weight: bold; margin-bottom: 1rem; border-bottom: 2px solid #F1EDED; padding-bottom: 0.5rem; display: flex; justify-content: space-between;}

        .kv-pair { margin-bottom: 0.75rem; font-size: 0.95rem; }
        .kv-pair strong { display: inline-block; width: 150px; color: #64748b; }

        .cs-row { display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #e2e8f0; font-size: 0.95rem;}
        .cs-row.total { font-weight: bold; font-size: 1.1rem; border-top: 2px solid #cbd5e1; border-bottom: none; }

        .btn { padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.9rem; }
        .btn-primary { background-color: #CF1F3C; color: white; }
        .btn-danger { background-color: #dc3545; color: white; }

        .badge { padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem; background: #e2e8f0; }

        @media(max-width: 768px) {
            .container { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="header">
    <h1>Booking Details</h1>
    <div class="header-nav">
        <a href="/dashboard.php" class="btn btn-primary" style="margin-right: 1rem;">Dashboard</a>
    </div>
</div>

<div class="container" id="bookingContent">
    <div style="grid-column: 1 / -1; text-align: center;">Loading...</div>
</div>

<script>
const bookingId = "<?php echo $id_safe; ?>";
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

function escapeHTML(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function loadBooking() {
    fetch('/api/bookings.php?action=view&id=' + encodeURIComponent(bookingId))
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                renderBooking(res.data);
            } else {
                document.getElementById('bookingContent').innerHTML = `<div style="grid-column:1/-1;color:red;">Error: ${escapeHTML(res.message)}</div>`;
            }
        });
}

function renderBooking(b) {
    const cs = b.cost_sheet || {};

    let cancelBtn = '';
    if (b.status !== 'Cancelled') {
        cancelBtn = `<button class="btn btn-danger" onclick="cancelBooking()">Cancel Booking</button>`;
    }

    document.getElementById('bookingContent').innerHTML = `
        <div class="left-col">
            <div class="card">
                <div class="card-header">
                    <span>${escapeHTML(b.booking_reference)}</span>
                    <span class="badge">${escapeHTML(b.status)}</span>
                </div>
                <div class="kv-pair"><strong>Customer:</strong> <a href="/customers/view.php?id=${b.customer_id}">${escapeHTML(b.customer_name)}</a></div>
                <div class="kv-pair"><strong>Mobile:</strong> ${escapeHTML(b.customer_mobile)}</div>
                <div class="kv-pair"><strong>Date:</strong> ${escapeHTML(b.booking_date)}</div>
                <div class="kv-pair"><strong>Salesperson:</strong> ${escapeHTML(b.salesperson_name)}</div>
                <hr style="border:0; border-top:1px solid #e2e8f0; margin: 1rem 0;">
                <div class="kv-pair"><strong>Project:</strong> ${escapeHTML(b.project_name || 'N/A')}</div>
                <div class="kv-pair"><strong>Property:</strong> ${escapeHTML(b.property_name || 'N/A')}</div>
                <div class="kv-pair"><strong>Unit Number:</strong> ${escapeHTML(b.unit_number)}</div>

                <div style="margin-top: 1rem;">
                    ${cancelBtn}
                </div>
            </div>

            <div class="card" id="paymentsModulePlaceholder">
                <div class="card-header">Payments & Collections</div>

                <form id="recordPaymentForm" style="margin-bottom: 1.5rem; background: #f8fafc; padding: 1rem; border-radius: 4px; border: 1px solid #cbd5e1;">
                    <h4 style="margin-top:0; margin-bottom:1rem;">Record Payment</h4>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="action" value="record">
                    <input type="hidden" name="booking_id" value="<?php echo $id_safe; ?>">

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div><label>Amount *</label><br><input type="number" step="0.01" name="amount" required style="width:100%; padding:0.5rem; box-sizing:border-box;"></div>
                        <div><label>Date *</label><br><input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required style="width:100%; padding:0.5rem; box-sizing:border-box;"></div>

                        <div>
                            <label>Mode *</label><br>
                            <select name="payment_mode" required style="width:100%; padding:0.5rem; box-sizing:border-box;">
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Online">Online</option>
                                <option value="Cash">Cash</option>
                                <option value="Credit Card">Credit Card</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div><label>Reference #</label><br><input type="text" name="transaction_reference" style="width:100%; padding:0.5rem; box-sizing:border-box;"></div>
                    </div>
                    <div style="margin-top: 1rem;">
                        <label>Notes</label><br>
                        <input type="text" name="notes" style="width:100%; padding:0.5rem; box-sizing:border-box;">
                    </div>

                    <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">Record Payment</button>
                    <div id="paymentMsg" style="margin-top: 0.5rem; font-size: 0.9rem;"></div>
                </form>

                <h4 style="margin-bottom:0.5rem;">Payment History</h4>
                <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                    <thead>
                        <tr>
                            <th style="text-align:left; border-bottom:1px solid #ccc; padding:0.5rem;">Date</th>
                            <th style="text-align:left; border-bottom:1px solid #ccc; padding:0.5rem;">Mode/Ref</th>
                            <th style="text-align:left; border-bottom:1px solid #ccc; padding:0.5rem;">Amount</th>
                            <th style="text-align:left; border-bottom:1px solid #ccc; padding:0.5rem;">Receipt</th>
                        </tr>
                    </thead>
                    <tbody id="paymentsTableBody">
                        <tr><td colspan="4" style="padding:0.5rem; text-align:center;">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="right-col">
            <div class="card">
                <div class="card-header">Cost Sheet</div>
                <div class="cs-row"><span>Base Price:</span> <span>${escapeHTML(cs.base_price)}</span></div>
                <div class="cs-row"><span>PLC:</span> <span>${escapeHTML(cs.plc)}</span></div>
                <div class="cs-row"><span>Floor Rise:</span> <span>${escapeHTML(cs.floor_rise)}</span></div>
                <div class="cs-row"><span>Parking:</span> <span>${escapeHTML(cs.parking)}</span></div>
                <div class="cs-row"><span>Club/Maint:</span> <span>${(parseFloat(cs.club_charges||0) + parseFloat(cs.maintenance||0)).toFixed(2)}</span></div>
                <div class="cs-row"><span>EDC/IDC:</span> <span>${(parseFloat(cs.edc||0) + parseFloat(cs.idc||0)).toFixed(2)}</span></div>
                <div class="cs-row"><span>GST/Other:</span> <span>${(parseFloat(cs.gst||0) + parseFloat(cs.other_charges||0)).toFixed(2)}</span></div>
                <div class="cs-row"><span>Discount:</span> <span>- ${escapeHTML(cs.discount)}</span></div>

                <div class="cs-row total"><span>Final Payable:</span> <span>${escapeHTML(cs.final_amount)}</span></div>

                <div class="cs-row" style="margin-top: 1rem; color: green;"><span>Amount Received:</span> <span>${escapeHTML(cs.amount_received)}</span></div>
                <div class="cs-row" style="color: #dc3545;"><span>Outstanding:</span> <span>${escapeHTML(cs.outstanding_amount)}</span></div>
            </div>
        </div>
    `;
}

function loadPayments() {
    fetch('/api/payments.php?action=list_for_booking&booking_id=' + encodeURIComponent(bookingId))
        .then(r => r.json())
        .then(res => {
            const tbody = document.getElementById('paymentsTableBody');
            if(!tbody) return;

            tbody.innerHTML = '';
            if (res.status === 'success' && res.data.length > 0) {
                res.data.forEach(p => {
                    tbody.innerHTML += `
                        <tr>
                            <td style="padding:0.5rem; border-bottom:1px solid #eee;">${escapeHTML(p.payment_date)}</td>
                            <td style="padding:0.5rem; border-bottom:1px solid #eee;">${escapeHTML(p.payment_mode)}<br><small style="color:#64748b">${escapeHTML(p.transaction_reference)}</small></td>
                            <td style="padding:0.5rem; border-bottom:1px solid #eee;">${escapeHTML(p.amount)}</td>
                            <td style="padding:0.5rem; border-bottom:1px solid #eee;">${escapeHTML(p.receipt_number)}</td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="4" style="padding:0.5rem; text-align:center;">No payments recorded.</td></tr>';
            }
        });
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('recordPaymentForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            const msg = document.getElementById('paymentMsg');

            fetch('/api/payments.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success') {
                        msg.style.color = 'green';
                        msg.textContent = res.message;
                        this.reset();
                        loadBooking(); // Reload booking to update cost sheet
                        loadPayments();
                    } else {
                        msg.style.color = 'red';
                        msg.textContent = res.message;
                    }
                });
        });
    }
});

function cancelBooking() {
    if (!confirm('Are you sure you want to cancel this booking? This will release the unit back to inventory.')) return;

    const fd = new FormData();
    fd.append('action', 'cancel');
    fd.append('id', bookingId);
    fd.append('csrf_token', csrfToken);

    fetch('/api/bookings.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                alert('Booking cancelled.');
                loadBooking();
            } else {
                alert('Error: ' + res.message);
            }
        });
}

document.addEventListener('DOMContentLoaded', () => {
    loadBooking();
    loadPayments();
});
</script>

</body>
</html>
