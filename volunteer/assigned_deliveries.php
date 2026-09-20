<?php
require_once '../config/db.php';
require_once '../includes/auth_check.php';
require_role(['volunteer']);

$volunteer_id = $_SESSION['user_id'];

$filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$labels = ['all'=>'All','assigned'=>'Assigned','picked_up'=>'Picked Up','delivered'=>'Delivered'];
if (!array_key_exists($filter, $labels)) $filter = 'all';

if ($filter === 'all') {
    $stmt = $conn->prepare(
        "SELECT del.*, d.food_name, d.quantity, d.pickup_address, d.food_type,
                du.name as donor_name, du.phone as donor_phone,
                ngo.name as ngo_name, ngo.address as ngo_address, ngo.phone as ngo_phone
         FROM deliveries del
         JOIN requests r ON del.request_id = r.request_id
         JOIN donations d ON r.donation_id = d.donation_id
         JOIN users du ON d.donor_id = du.user_id
         JOIN users ngo ON r.ngo_id = ngo.user_id
         WHERE del.volunteer_id = ?
         ORDER BY FIELD(del.status,'assigned','picked_up','delivered'), del.delivery_id DESC"
    );
    $stmt->bind_param("i", $volunteer_id);
} else {
    $stmt = $conn->prepare(
        "SELECT del.*, d.food_name, d.quantity, d.pickup_address, d.food_type,
                du.name as donor_name, du.phone as donor_phone,
                ngo.name as ngo_name, ngo.address as ngo_address, ngo.phone as ngo_phone
         FROM deliveries del
         JOIN requests r ON del.request_id = r.request_id
         JOIN donations d ON r.donation_id = d.donation_id
         JOIN users du ON d.donor_id = du.user_id
         JOIN users ngo ON r.ngo_id = ngo.user_id
         WHERE del.volunteer_id = ? AND del.status = ?
         ORDER BY del.delivery_id DESC"
    );
    $stmt->bind_param("is", $volunteer_id, $filter);
}
$stmt->execute();
$deliveries = $stmt->get_result();

// Counts for stats
$counts = ['assigned'=>0,'picked_up'=>0,'delivered'=>0];
$res = $conn->prepare("SELECT status, COUNT(*) c FROM deliveries WHERE volunteer_id=? GROUP BY status");
$res->bind_param("i", $volunteer_id);
$res->execute();
$crow = $res->get_result();
while ($r = $crow->fetch_assoc()) {
    if (isset($counts[$r['status']])) $counts[$r['status']] = (int)$r['c'];
}
$res->close();

include '../includes/header.php';
?>

<link rel="stylesheet" href="/FoodRescueHub/css/dashboard.css">

<span class="hero-eyebrow">Your deliveries</span>
<h2 class="mt-2">Assigned Deliveries</h2>
<p class="lede">All deliveries you've accepted. Use the actions below to advance each one through the chain.</p>

<div class="row g-3 mt-2 mb-4">
    <div class="col-4">
        <div class="stat-block accent-mango">
            <div class="stat-number"><?php echo $counts['assigned']; ?></div>
            <div class="stat-label">Assigned</div>
        </div>
    </div>
    <div class="col-4">
        <div class="stat-block accent-blue">
            <div class="stat-number"><?php echo $counts['picked_up']; ?></div>
            <div class="stat-label">Picked Up</div>
        </div>
    </div>
    <div class="col-4">
        <div class="stat-block">
            <div class="stat-number"><?php echo $counts['delivered']; ?></div>
            <div class="stat-label">Delivered</div>
        </div>
    </div>
</div>

<div class="d-flex gap-2 flex-wrap mb-4">
    <?php foreach ($labels as $k => $v): ?>
        <a href="assigned_deliveries.php?status=<?php echo $k; ?>"
           class="btn btn-sm <?php echo $filter===$k ? 'btn-primary':'btn-outline-primary'; ?>">
            <?php echo $v; ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($deliveries->num_rows === 0): ?>
    <div class="empty-state">
        <p class="mb-2">No deliveries in this category.</p>
        <a href="dashboard.php" class="btn btn-primary btn-sm">Accept Open Deliveries</a>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php while ($d = $deliveries->fetch_assoc()): ?>
            <div class="col-md-6">
                <div class="ticket">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="ticket-eyebrow">Delivery #<?php echo $d['delivery_id']; ?></span>
                        <span class="stamp stamp-<?php echo $d['status']; ?>"><?php echo str_replace('_',' ',$d['status']); ?></span>
                    </div>
                    <h3 class="ticket-title mt-1"><?php echo htmlspecialchars($d['food_name']); ?></h3>

                    <div class="info-grid mb-3">
                        <div class="info-item">
                            <span class="info-label">Food Type</span>
                            <span class="info-value text-capitalize"><?php echo $d['food_type']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Quantity</span>
                            <span class="info-value"><?php echo htmlspecialchars($d['quantity']); ?></span>
                        </div>
                        <div class="info-item" style="grid-column:1/-1">
                            <span class="info-label">📍 Pickup From</span>
                            <span class="info-value"><?php echo htmlspecialchars($d['donor_name']); ?>, <?php echo htmlspecialchars($d['pickup_address']); ?></span>
                        </div>
                        <?php if ($d['donor_phone']): ?>
                        <div class="info-item">
                            <span class="info-label">Donor Phone</span>
                            <span class="info-value"><?php echo htmlspecialchars($d['donor_phone']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="info-item" style="grid-column:1/-1">
                            <span class="info-label">🚚 Deliver To</span>
                            <span class="info-value"><?php echo htmlspecialchars($d['ngo_name']); ?><?php echo $d['ngo_address'] ? ', '.htmlspecialchars($d['ngo_address']) : ''; ?></span>
                        </div>
                        <?php if ($d['ngo_phone']): ?>
                        <div class="info-item">
                            <span class="info-label">NGO Phone</span>
                            <span class="info-value"><?php echo htmlspecialchars($d['ngo_phone']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <hr class="ticket-divider">

                    <a href="update_delivery.php?id=<?php echo $d['delivery_id']; ?>" class="btn btn-primary w-100">
                        <?php if ($d['status'] === 'assigned'): ?>Mark as Picked Up
                        <?php elseif ($d['status'] === 'picked_up'): ?>Mark as Delivered
                        <?php else: ?>View Details<?php endif; ?>
                    </a>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
