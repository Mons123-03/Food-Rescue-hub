<?php
require_once '../config/db.php';
require_once '../includes/auth_check.php';
require_role(['volunteer']);

$volunteer_id = $_SESSION['user_id'];
$delivery_id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message      = "";

// Fetch delivery — must belong to this volunteer
$stmt = $conn->prepare(
    "SELECT del.*, d.food_name, d.quantity, d.pickup_address, d.food_type, d.donation_id,
            du.name as donor_name, du.phone as donor_phone,
            ngo.name as ngo_name, ngo.address as ngo_address, ngo.phone as ngo_phone
     FROM deliveries del
     JOIN requests r ON del.request_id = r.request_id
     JOIN donations d ON r.donation_id = d.donation_id
     JOIN users du ON d.donor_id = du.user_id
     JOIN users ngo ON r.ngo_id = ngo.user_id
     WHERE del.delivery_id = ? AND del.volunteer_id = ?"
);
$stmt->bind_param("ii", $delivery_id, $volunteer_id);
$stmt->execute();
$delivery = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$delivery) {
    include '../includes/header.php';
    echo '<div class="alert alert-danger">Delivery not found or does not belong to you. <a href="assigned_deliveries.php">Back</a></div>';
    include '../includes/footer.php';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'picked_up' && $delivery['status'] === 'assigned') {
        $u1 = $conn->prepare("UPDATE deliveries SET status='picked_up', picked_up_time=NOW() WHERE delivery_id=? AND volunteer_id=?");
        $u1->bind_param("ii", $delivery_id, $volunteer_id);
        $u1->execute(); $u1->close();
        $u2 = $conn->prepare("UPDATE donations SET status='in_transit' WHERE donation_id=?");
        $u2->bind_param("i", $delivery['donation_id']);
        $u2->execute(); $u2->close();
        $delivery['status'] = 'picked_up';
        $message = "Marked as picked up! Drive safely.";

    } elseif ($action === 'delivered' && $delivery['status'] === 'picked_up') {
        $u1 = $conn->prepare("UPDATE deliveries SET status='delivered', delivered_time=NOW() WHERE delivery_id=? AND volunteer_id=?");
        $u1->bind_param("ii", $delivery_id, $volunteer_id);
        $u1->execute(); $u1->close();
        $u2 = $conn->prepare("UPDATE donations SET status='completed' WHERE donation_id=?");
        $u2->bind_param("i", $delivery['donation_id']);
        $u2->execute(); $u2->close();
        $delivery['status'] = 'delivered';
        $message = "Delivery complete! One more rescue done.";
    }
}

include '../includes/header.php';
?>

<link rel="stylesheet" href="/FoodRescueHub/css/dashboard.css">

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="assigned_deliveries.php" class="btn btn-outline-primary btn-sm">&larr; My Deliveries</a>
    <span class="hero-eyebrow mb-0">Delivery #<?php echo $delivery_id; ?></span>
</div>

<h2 class="mt-0"><?php echo htmlspecialchars($delivery['food_name']); ?></h2>

<?php if ($message): ?>
    <div class="alert alert-success mt-3"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<!-- Progress bar -->
<div class="delivery-progress mb-5 mt-3">
    <?php
    $steps   = ['assigned'=>'Assigned','picked_up'=>'Picked Up','delivered'=>'Delivered'];
    $reached = false;
    foreach ($steps as $key => $label):
        $active  = $delivery['status'] === $key;
        $done    = !$reached && !$active;
        if ($active) $reached = true;
    ?>
    <div class="progress-step <?php echo $done ? 'done' : ($active ? 'active' : ''); ?>">
        <div class="progress-dot"></div>
        <div class="progress-label"><?php echo $label; ?></div>
    </div>
    <?php if ($key !== 'delivered'): ?>
        <div class="progress-line <?php echo $done ? 'done' : ''; ?>"></div>
    <?php endif; ?>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="form-card">
            <h3 style="font-family:'Work Sans',sans-serif;font-weight:700;font-size:1.1rem;">Delivery Details</h3>

            <div class="info-grid mt-3">
                <div class="info-item">
                    <span class="info-label">Food Type</span>
                    <span class="info-value text-capitalize"><?php echo $delivery['food_type']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Quantity</span>
                    <span class="info-value"><?php echo htmlspecialchars($delivery['quantity']); ?></span>
                </div>
                <div class="info-item" style="grid-column:1/-1">
                    <span class="info-label">📍 Pickup From</span>
                    <span class="info-value"><?php echo htmlspecialchars($delivery['donor_name']); ?><br><?php echo htmlspecialchars($delivery['pickup_address']); ?></span>
                </div>
                <?php if ($delivery['donor_phone']): ?>
                <div class="info-item" style="grid-column:1/-1">
                    <span class="info-label">Donor Phone</span>
                    <span class="info-value"><a href="tel:<?php echo htmlspecialchars($delivery['donor_phone']); ?>"><?php echo htmlspecialchars($delivery['donor_phone']); ?></a></span>
                </div>
                <?php endif; ?>
                <div class="info-item" style="grid-column:1/-1">
                    <span class="info-label">🚚 Deliver To</span>
                    <span class="info-value"><?php echo htmlspecialchars($delivery['ngo_name']); ?><?php echo $delivery['ngo_address'] ? '<br>'.htmlspecialchars($delivery['ngo_address']) : ''; ?></span>
                </div>
                <?php if ($delivery['ngo_phone']): ?>
                <div class="info-item" style="grid-column:1/-1">
                    <span class="info-label">NGO Phone</span>
                    <span class="info-value"><a href="tel:<?php echo htmlspecialchars($delivery['ngo_phone']); ?>"><?php echo htmlspecialchars($delivery['ngo_phone']); ?></a></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="mt-4">
                <?php if ($delivery['status'] === 'assigned'): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="picked_up">
                        <button type="submit" class="btn btn-info w-100">Mark as Picked Up</button>
                    </form>
                <?php elseif ($delivery['status'] === 'picked_up'): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="delivered">
                        <button type="submit" class="btn btn-primary w-100">Mark as Delivered</button>
                    </form>
                <?php else: ?>
                    <div class="text-center p-3" style="background:rgba(47,75,38,0.08);border-radius:4px;">
                        <strong style="color:var(--crate-green);">&#10003; Rescue complete — thank you!</strong>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
