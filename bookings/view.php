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

            <div class="card" id="commissionsModule">
                <div class="card-header">Commissions & Brokerage</div>
                <form id="recordCommissionForm" style="margin-bottom: 1.5rem; background: #f8fafc; padding: 1rem; border-radius: 4px; border: 1px solid #cbd5e1;">
                    <h4 style="margin-top:0; margin-bottom:1rem;">Map Commission</h4>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="action" value="calculate">
                    <input type="hidden" name="booking_id" value="<?php echo $id_safe; ?>">

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label>Channel Partner ID</label><br>
                            <input type="number" name="channel_partner_id" style="width:100%; padding:0.5rem; box-sizing:border-box;">
                        </div>
                        <div>
                            <label>OR Salesperson ID</label><br>
                            <input type="number" name="salesperson_id" style="width:100%; padding:0.5rem; box-sizing:border-box;">
                        </div>
                        <div>
                            <label>Percentage (%)</label><br>
                            <input type="number" step="0.01" name="percentage" style="width:100%; padding:0.5rem; box-sizing:border-box;">
                        </div>
                        <div>
                            <label>OR Fixed Amount</label><br>
                            <input type="number" step="0.01" name="fixed_amount" style="width:100%; padding:0.5rem; box-sizing:border-box;">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">Map Commission</button>
                    <div id="commMsg" style="margin-top: 0.5rem; font-size: 0.9rem;"></div>
                </form>

                <h4 style="margin-bottom:0.5rem;">Allocated Commissions</h4>
                <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                    <thead>
                        <tr>
                            <th style="text-align:left; border-bottom:1px solid #ccc; padding:0.5rem;">Partner / Agent</th>
                            <th style="text-align:left; border-bottom:1px solid #ccc; padding:0.5rem;">Amount</th>
                            <th style="text-align:left; border-bottom:1px solid #ccc; padding:0.5rem;">Status / Outstanding</th>
                        </tr>
                    </thead>
                    <tbody id="commissionsTableBody">
                        <tr><td colspan="3" style="padding:0.5rem; text-align:center;">Loading...</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="card" id="documentsModule">
                <div class="card-header">Documents</div>
                <form id="uploadDocForm" style="margin-bottom: 1.5rem;" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="action" value="upload">
                    <input type="hidden" name="entity_type" value="Booking">
                    <input type="hidden" name="entity_id" value="<?php echo $id_safe; ?>">

                    <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem; flex-wrap:wrap;">
                        <select name="category" required style="padding:0.5rem;">
                            <option value="Booking / Sales">Booking / Sales</option>
                            <option value="Customer / KYC">Customer / KYC</option>
                        </select>
                        <select name="document_type" required style="padding:0.5rem;">
                            <option value="Booking Form">Booking Form</option>
                            <option value="Agreement">Agreement</option>
                            <option value="Payment Receipt">Payment Receipt</option>
                            <option value="PAN">PAN</option>
                            <option value="Aadhaar">Aadhaar</option>
                            <option value="Other">Other</option>
                        </select>
                        <input type="file" name="document" required style="padding:0.5rem;" accept=".pdf,.jpg,.jpeg,.png,.webp,.csv,.doc,.docx">
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top:0.5rem;">Upload Document</button>
                    <div id="uploadMsg" style="margin-top: 0.5rem; font-size: 0.9rem;"></div>
                </form>

                <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                    <thead>
                        <tr>
                            <th style="text-align:left; border-bottom:1px solid #ccc; padding:0.5rem;">Document</th>
                            <th style="text-align:left; border-bottom:1px solid #ccc; padding:0.5rem;">Type</th>
                            <th style="text-align:right; border-bottom:1px solid #ccc; padding:0.5rem;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="documentsTableBody">
                        <tr><td colspan="3" style="padding:0.5rem; text-align:center;">Loading...</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="card" id="postSaleModule">
                <div class="card-header">Post-Sale Handover</div>
                <form id="postSaleForm" style="margin-bottom: 0;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="booking_id" value="<?php echo $id_safe; ?>">

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div><label>Expected Possession</label><br><input type="date" name="expected_possession_date" id="ps_exp" style="width:100%; padding:0.5rem; box-sizing:border-box;"></div>
                        <div><label>Actual Possession</label><br><input type="date" name="actual_possession_date" id="ps_act" style="width:100%; padding:0.5rem; box-sizing:border-box;"></div>
                        <div><label>Handover Date</label><br><input type="date" name="handover_date" id="ps_hdo" style="width:100%; padding:0.5rem; box-sizing:border-box;"></div>
                        <div>
                            <label>Status</label><br>
                            <select name="handover_status" id="ps_status" style="width:100%; padding:0.5rem; box-sizing:border-box;">
                                <option value="Pending">Pending</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Completed">Completed</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                        <label><input type="checkbox" name="keys_delivered" id="ps_keys"> Keys Delivered</label>
                        <label><input type="checkbox" name="documents_delivered" id="ps_docs"> Documents Delivered</label>
                        <label><input type="checkbox" name="customer_confirmation" id="ps_conf"> Customer Confirmed</label>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <label>Snagging / Punch List</label><br>
                        <textarea name="snagging_list" id="ps_snag" rows="2" style="width:100%; padding:0.5rem; box-sizing:border-box;"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Post-Sale Details</button>
                    <div id="psMsg" style="margin-top: 0.5rem; font-size: 0.9rem;"></div>
                </form>
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

function loadCommissions() {
    fetch('/api/commissions.php?action=list_for_booking&booking_id=' + encodeURIComponent(bookingId))
        .then(r => r.json())
        .then(res => {
            const tbody = document.getElementById('commissionsTableBody');
            if(!tbody) return;

            tbody.innerHTML = '';
            if (res.status === 'success' && res.data.length > 0) {
                res.data.forEach(c => {
                    const name = c.company_name || c.salesperson_name || 'Unknown';
                    tbody.innerHTML += `
                        <tr>
                            <td style="padding:0.5rem; border-bottom:1px solid #eee;"><strong>${escapeHTML(name)}</strong></td>
                            <td style="padding:0.5rem; border-bottom:1px solid #eee;">Base: ${escapeHTML(c.base_amount)}<br>Comm: <strong>${escapeHTML(c.commission_amount)}</strong></td>
                            <td style="padding:0.5rem; border-bottom:1px solid #eee;">
                                <span class="badge">${escapeHTML(c.status)}</span><br>
                                <small style="color:red">Out: ${escapeHTML(c.outstanding_amount)}</small>
                            </td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="3" style="padding:0.5rem; text-align:center;">No commissions allocated yet.</td></tr>';
            }
        });
}

function loadPostSale() {
    fetch('/api/post_sale.php?action=view&booking_id=' + encodeURIComponent(bookingId))
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success' && res.data) {
                const ps = res.data;
                if(ps.expected_possession_date) document.getElementById('ps_exp').value = ps.expected_possession_date;
                if(ps.actual_possession_date) document.getElementById('ps_act').value = ps.actual_possession_date;
                if(ps.handover_date) document.getElementById('ps_hdo').value = ps.handover_date;
                if(ps.handover_status) document.getElementById('ps_status').value = ps.handover_status;
                if(ps.snagging_list) document.getElementById('ps_snag').value = ps.snagging_list;

                document.getElementById('ps_keys').checked = (ps.keys_delivered == 1);
                document.getElementById('ps_docs').checked = (ps.documents_delivered == 1);
                document.getElementById('ps_conf').checked = (ps.customer_confirmation == 1);
            }
        });
}

function loadDocuments() {
    fetch('/api/documents.php?action=list&entity_type=Booking&entity_id=' + encodeURIComponent(bookingId))
        .then(r => r.json())
        .then(res => {
            const tbody = document.getElementById('documentsTableBody');
            if(!tbody) return;

            tbody.innerHTML = '';
            if (res.status === 'success' && res.data.length > 0) {
                res.data.forEach(d => {
                    tbody.innerHTML += `
                        <tr>
                            <td style="padding:0.5rem; border-bottom:1px solid #eee;"><strong>${escapeHTML(d.original_filename)}</strong></td>
                            <td style="padding:0.5rem; border-bottom:1px solid #eee;">${escapeHTML(d.category)} - ${escapeHTML(d.document_type)}</td>
                            <td style="padding:0.5rem; border-bottom:1px solid #eee; text-align:right;">
                                <a href="/api/documents.php?action=download&id=${d.id}" target="_blank" class="btn btn-outline" style="font-size:0.8rem;">Download</a>
                            </td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="3" style="padding:0.5rem; text-align:center;">No documents uploaded.</td></tr>';
            }
        });
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
    const docForm = document.getElementById('uploadDocForm');
    if (docForm) {
        docForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            const msg = document.getElementById('uploadMsg');
            fetch('/api/documents.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if(res.status === 'success') {
                        msg.style.color = 'green';
                        msg.textContent = res.message;
                        this.reset();
                        loadDocuments();
                    } else {
                        msg.style.color = 'red';
                        msg.textContent = res.message;
                    }
                });
        });
    }

    const commForm = document.getElementById('recordCommissionForm');
    if (commForm) {
        commForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            const msg = document.getElementById('commMsg');
            fetch('/api/commissions.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if(res.status === 'success') {
                        msg.style.color = 'green';
                        msg.textContent = res.message;
                        this.reset();
                        loadCommissions();
                    } else {
                        msg.style.color = 'red';
                        msg.textContent = res.message;
                    }
                });
        });
    }

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
    loadCommissions();
    loadDocuments();
    loadPostSale();
});
</script>

</body>
</html>
