<?php
require_once '../config/db.php';
require_once '../includes/auth_check.php';
require_role(['admin']);

// Platform totals
$totals = [];
$totals['donations']  = $conn->query("SELECT COUNT(*) c FROM donations")->fetch_assoc()['c'];
$totals['completed']  = $conn->query("SELECT COUNT(*) c FROM donations WHERE status='completed'")->fetch_assoc()['c'];
$totals['available']  = $conn->query("SELECT COUNT(*) c FROM donations WHERE status='available'")->fetch_assoc()['c'];
$totals['in_transit'] = $conn->query("SELECT COUNT(*) c FROM donations WHERE status='in_transit'")->fetch_assoc()['c'];
$totals['donors']     = $conn->query("SELECT COUNT(*) c FROM users WHERE role='donor'")->fetch_assoc()['c'];
$totals['ngos']       = $conn->query("SELECT COUNT(*) c FROM users WHERE role='ngo' AND is_verified=1")->fetch_assoc()['c'];
$totals['volunteers'] = $conn->query("SELECT COUNT(*) c FROM users WHERE role='volunteer' AND is_verified=1")->fetch_assoc()['c'];
$totals['requests']   = $conn->query("SELECT COUNT(*) c FROM requests")->fetch_assoc()['c'];
$totals['deliveries'] = $conn->query("SELECT COUNT(*) c FROM deliveries WHERE status='delivered'")->fetch_assoc()['c'];

$success_rate = $totals['donations'] > 0 ? round(($totals['completed'] / $totals['donations']) * 100) : 0;

// Donations per day — last 14 days (for Chart.js)
$daily = $conn->query(
    "SELECT DATE(created_at) as day, COUNT(*) as c FROM donations
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
     GROUP BY DATE(created_at) ORDER BY day ASC"
);
$daily_labels = []; $daily_data = [];
while ($row = $daily->fetch_assoc()) {
    $daily_labels[] = date("d M", strtotime($row['day']));
    $daily_data[]   = (int)$row['c'];
}

// Donations by food type
$by_type = $conn->query("SELECT food_type, COUNT(*) c FROM donations GROUP BY food_type");
$type_labels = []; $type_data = [];
while ($row = $by_type->fetch_assoc()) {
    $type_labels[] = ucfirst($row['food_type']);
    $type_data[]   = (int)$row['c'];
}

// Status breakdown
$by_status = $conn->query("SELECT status, COUNT(*) c FROM donations GROUP BY status");
$status_labels = []; $status_data = [];
while ($row = $by_status->fetch_assoc()) {
    $status_labels[] = ucfirst(str_replace('_',' ',$row['status']));
    $status_data[]   = (int)$row['c'];
}

// Top 5 donors
$top_donors = $conn->query(
    "SELECT u.name, COUNT(d.donation_id) as c FROM donations d
     JOIN users u ON d.donor_id=u.user_id
     GROUP BY u.user_id ORDER BY c DESC LIMIT 5"
);

// Top 5 NGOs
$top_ngos = $conn->query(
    "SELECT u.name, COUNT(r.request_id) as c FROM requests r
     JOIN users u ON r.ngo_id=u.user_id
     WHERE r.status='approved'
     GROUP BY u.user_id ORDER BY c DESC LIMIT 5"
);

include '../includes/header.php';
?>

<link rel="stylesheet" href="/FoodRescueHub/css/dashboard.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

<span class="hero-eyebrow">Admin</span>
<h2 class="mt-2">Reports &amp; Analytics</h2>
<p class="lede">Platform-wide snapshot — donations posted, rescued, and in progress.</p>

<!-- KPI Row -->
<div class="row g-3 mt-2">
    <div class="col-6 col-lg-3">
        <div class="stat-block">
            <div class="stat-number"><?php echo $totals['donations']; ?></div>
            <div class="stat-label">Total Donations</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-block accent-mango">
            <div class="stat-number"><?php echo $totals['completed']; ?></div>
            <div class="stat-label">Successfully Delivered</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-block accent-blue">
            <div class="stat-number"><?php echo $success_rate; ?>%</div>
            <div class="stat-label">Success Rate</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-block accent-tomato">
            <div class="stat-number"><?php echo $totals['in_transit']; ?></div>
            <div class="stat-label">Currently In Transit</div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1 mb-5">
    <div class="col-4 col-lg-2">
        <div class="stat-block">
            <div class="stat-number"><?php echo $totals['donors']; ?></div>
            <div class="stat-label">Donors</div>
        </div>
    </div>
    <div class="col-4 col-lg-2">
        <div class="stat-block">
            <div class="stat-number"><?php echo $totals['ngos']; ?></div>
            <div class="stat-label">NGOs</div>
        </div>
    </div>
    <div class="col-4 col-lg-2">
        <div class="stat-block">
            <div class="stat-number"><?php echo $totals['volunteers']; ?></div>
            <div class="stat-label">Volunteers</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-block">
            <div class="stat-number"><?php echo $totals['requests']; ?></div>
            <div class="stat-label">Total Requests</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-block">
            <div class="stat-number"><?php echo $totals['deliveries']; ?></div>
            <div class="stat-label">Deliveries Done</div>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="row g-4">
    <div class="col-lg-8">
        <div class="form-card">
            <h3 style="font-family:'Work Sans',sans-serif;font-weight:700;font-size:1rem;">Donations — Last 14 Days</h3>
            <canvas id="dailyChart" height="100"></canvas>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="form-card">
            <h3 style="font-family:'Work Sans',sans-serif;font-weight:700;font-size:1rem;">By Food Type</h3>
            <canvas id="typeChart"></canvas>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="form-card">
            <h3 style="font-family:'Work Sans',sans-serif;font-weight:700;font-size:1rem;">Donation Status Breakdown</h3>
            <canvas id="statusChart"></canvas>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="row g-4">
            <div class="col-12">
                <div class="form-card">
                    <h3 style="font-family:'Work Sans',sans-serif;font-weight:700;font-size:1rem;">Top Donors</h3>
                    <table class="table table-manifest align-middle mb-0" style="font-size:0.9rem;">
                        <thead><tr><th>Rank</th><th>Donor</th><th>Donations</th></tr></thead>
                        <tbody>
                            <?php $rank=1; while ($d=$top_donors->fetch_assoc()): ?>
                                <tr>
                                    <td class="mono">#<?php echo $rank++; ?></td>
                                    <td><?php echo htmlspecialchars($d['name']); ?></td>
                                    <td class="mono"><?php echo $d['c']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-12">
                <div class="form-card">
                    <h3 style="font-family:'Work Sans',sans-serif;font-weight:700;font-size:1rem;">Top NGOs (by Approved Requests)</h3>
                    <table class="table table-manifest align-middle mb-0" style="font-size:0.9rem;">
                        <thead><tr><th>Rank</th><th>NGO</th><th>Approved</th></tr></thead>
                        <tbody>
                            <?php $rank=1; while ($n=$top_ngos->fetch_assoc()): ?>
                                <tr>
                                    <td class="mono">#<?php echo $rank++; ?></td>
                                    <td><?php echo htmlspecialchars($n['name']); ?></td>
                                    <td class="mono"><?php echo $n['c']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const green    = '#2F4B26';
const mango    = '#C97A1D';
const blue     = '#2C6E8E';
const tomato   = '#C84527';
const paper    = '#F1F0E4';
const softInk  = '#4B5A48';

// Daily chart
new Chart(document.getElementById('dailyChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($daily_labels); ?>,
        datasets: [{
            label: 'Donations Posted',
            data: <?php echo json_encode($daily_data); ?>,
            backgroundColor: green, borderRadius: 4
        }]
    },
    options: {
        responsive: true, plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});

// By type doughnut
new Chart(document.getElementById('typeChart'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($type_labels); ?>,
        datasets: [{ data: <?php echo json_encode($type_data); ?>, backgroundColor: [green, mango, blue, tomato] }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});

// Status breakdown
new Chart(document.getElementById('statusChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($status_labels); ?>,
        datasets: [{
            label: 'Count',
            data: <?php echo json_encode($status_data); ?>,
            backgroundColor: [green, mango, blue, tomato, softInk, '#888', '#aaa'],
            borderRadius: 4
        }]
    },
    options: {
        indexAxis: 'y', responsive: true,
        plugins: { legend: { display: false } },
        scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});
</script>

<?php include '../includes/footer.php'; ?>
