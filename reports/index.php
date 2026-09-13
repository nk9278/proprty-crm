<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';

requireLogin();
requirePermission('reports.view');

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token); ?>">
    <title>Zopa CRM - Reports & Analytics</title>
    <style>
        body { font-family: sans-serif; background-color: #F1EDED; color: #1E1C1C; margin: 0; display: flex; flex-direction: column; height: 100vh; }
        .header { background-color: white; padding: 1rem 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 1.5rem; }
        .container { flex: 1; padding: 2rem; overflow-y: auto; max-width: 1200px; margin: 0 auto; width: 100%; box-sizing: border-box; }
        .card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
        .card-header { font-size: 1.25rem; font-weight: bold; margin-bottom: 1rem; border-bottom: 2px solid #F1EDED; padding-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center; }

        .btn { padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.9rem; display: inline-block;}
        .btn-primary { background-color: #CF1F3C; color: white; }
        .btn-outline { background-color: transparent; border: 1px solid #ccc; }

        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .kpi-card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); text-align: center; border-bottom: 4px solid #CF1F3C; }
        .kpi-value { font-size: 2rem; font-weight: bold; margin-top: 0.5rem; }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #eee; }
        th { background-color: #f9f9f9; }

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
    <h1>Reports & Analytics</h1>
    <div class="header-nav">
        <a href="/dashboard.php" class="btn btn-primary">Dashboard</a>
    </div>
</div>

<div class="container">
    <div class="kpi-grid">
        <div class="kpi-card">
            <div>Total Leads</div>
            <div class="kpi-value" id="kpiLeads">-</div>
        </div>
        <div class="kpi-card">
            <div>Total Bookings</div>
            <div class="kpi-value" id="kpiBookings">-</div>
        </div>
        <div class="kpi-card">
            <div>Total Revenue</div>
            <div class="kpi-value" id="kpiRevenue">-</div>
        </div>
        <div class="kpi-card">
            <div>Amount Collected</div>
            <div class="kpi-value" id="kpiCollected" style="color: green;">-</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span>Sales Performance Overview</span>
            <?php if(hasPermission('reports.export')): ?>
            <button class="btn btn-outline" onclick="exportTableToCSV('salesPerformanceTable', 'sales_performance.csv')">Export CSV</button>
            <?php endif; ?>
        </div>
        <table id="salesPerformanceTable">
            <thead>
                <tr>
                    <th>Salesperson</th>
                    <th>Assigned Leads</th>
                    <th>Bookings</th>
                    <th>Generated Revenue</th>
                </tr>
            </thead>
            <tbody id="salesList">
                <tr><td colspan="4" style="text-align:center;">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
function escapeHTML(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

// Simple CSV export function
function exportTableToCSV(tableId, filename) {
    var table = document.getElementById(tableId);
    var rows = Array.from(table.querySelectorAll('tr'));
    var csvContent = "data:text/csv;charset=utf-8,";

    rows.forEach(function(row) {
        var cols = Array.from(row.querySelectorAll('td, th'));
        var dataString = cols.map(c => `"${c.innerText.replace(/"/g, '""')}"`).join(",");
        csvContent += dataString + "\r\n";
    });

    var encodedUri = encodeURI(csvContent);
    var link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", filename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function formatCurrency(amount) {
    if (!amount) return '0.00';
    return Number(amount).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

document.addEventListener('DOMContentLoaded', () => {
    // Load KPIs
    fetch('/api/reports.php?action=dashboard_kpis')
        .then(r => r.json())
        .then(res => {
            if(res.status === 'success') {
                document.getElementById('kpiLeads').textContent = res.data.total_leads;
                document.getElementById('kpiBookings').textContent = res.data.total_bookings;
                document.getElementById('kpiRevenue').textContent = formatCurrency(res.data.total_revenue);
                document.getElementById('kpiCollected').textContent = formatCurrency(res.data.total_collected);
            }
        });

    // Load Sales Performance
    fetch('/api/reports.php?action=sales_performance')
        .then(r => r.json())
        .then(res => {
            const tbody = document.getElementById('salesList');
            tbody.innerHTML = '';
            if(res.status === 'success' && res.data.length > 0) {
                res.data.forEach(row => {
                    tbody.innerHTML += `
                        <tr>
                            <td data-label="Salesperson"><strong>${escapeHTML(row.salesperson)}</strong></td>
                            <td data-label="Assigned Leads">${escapeHTML(row.assigned_leads)}</td>
                            <td data-label="Bookings">${escapeHTML(row.total_bookings)}</td>
                            <td data-label="Generated Revenue">${formatCurrency(row.total_revenue)}</td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">No data available.</td></tr>';
            }
        });
});
</script>

</body>
</html>
