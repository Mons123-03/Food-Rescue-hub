<?php
require_once '../config/db.php';
require_once '../includes/auth_check.php';
require_role(['volunteer']);

$volunteer_id = $_SESSION['user_id'];
$message = "";
$message_type = "success";

// Handle actions: accept / picked_up / delivered
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delivery_id'], $_POST['action'])) {
    $delivery_id = (int)$_POST['delivery_id'];
    $action = $_POST['action'];

    if ($action === 'accept') {
        $stmt = $conn->prepare("UPDATE deliveries SET volunteer_id = ?, status = 'assigned', assigned_time = NOW() WHERE delivery_id = ? AND status = 'open'");
        $stmt->bind_param("ii", $volunteer_id, $delivery_id);
        $stmt->execute();
        $message = $stmt->affected_rows > 0 ? "Delivery accepted — it's now in your list below." : "That delivery was just taken by someone else.";
        $message_type = $stmt->affected_rows > 0 ? "success" : "danger";
        $stmt->close();

    } elseif ($action === 'picked_up') {
        $stmt = $conn->prepare("UPDATE deliveries SET status = 'picked_up', picked_up_time = NOW() WHERE delivery_id = ? AND volunteer_id = ?");
        $stmt->bind_param("ii", $delivery_id, $volunteer_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE donations d JOIN requests r ON d.donation_id = r.donation_id JOIN deliveries del ON del.request_id = r.request_id SET d.status = 'in_transit' WHERE del.delivery_id = ?");
        $stmt->bind_param("i", $delivery_id);
        $stmt->execute();
        $stmt->close();
        $message = "Marked as picked up. Drive safe!";

    } elseif ($action === 'delivered') {
        $stmt = $conn->prepare("UPDATE deliveries SET status = 'delivered', delivered_time = NOW() WHERE delivery_id = ? AND volunteer_id = ?");
        $stmt->bind_param("ii", $delivery_id, $volunteer_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE donations d JOIN requests r ON d.donation_id = r.donation_id JOIN deliveries del ON del.request_id = r.request_id SET d.status = 'completed' WHERE del.delivery_id = ?");
        $stmt->bind_param("i", $delivery_id);
        $stmt->execute();
        $stmt->close();
        $message = "Delivered! One more rescue complete.";
    }
}

// Open deliveries — approved requests with no volunteer yet
$open = $conn->query(
    "SELECT del.delivery_id, d.food_name, d.quantity, d.pickup_address, du.name as donor_name,
            ngo.name as ngo_name, ngo.address as ngo_address
     FROM deliveries del
     JOIN requests r ON del.request_id = r.request_id
     JOIN donations d ON r.donation_id = d.donation_id
     JOIN users du ON d.donor_id = du.user_id
     JOIN users ngo ON r.ngo_id = ngo.user_id
     WHERE del.status = 'open'
     ORDER BY del.delivery_id ASC"
);

// My active + completed deliveries
$stmt = $conn->prepare(
    "SELECT del.delivery_id, del.status as delivery_status, d.food_name, d.quantity, d.pickup_address,
            du.name as donor_name, ngo.name as ngo_name, ngo.address as ngo_address
     FROM deliveries del
     JOIN requests r ON del.request_id = r.request_id
     JOIN donations d ON r.donation_id = d.donation_id
     JOIN users du ON d.donor_id = du.user_id
     JOIN users ngo ON r.ngo_id = ngo.user_id
     WHERE del.volunteer_id = ?
     ORDER BY FIELD(del.status,'assigned','picked_up','delivered'), del.delivery_id DESC"
);
$stmt->bind_param("i", $volunteer_id);
$stmt->execute();
$mine = $stmt->get_result();

include '../includes/header.php';
?>

<span class="hero-eyebrow">Volunteer dashboard</span>
<h2 class="mt-2">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></h2>
<p class="lede">Accept an open delivery, pick up from the donor, drop off at the NGO. That's the whole job.</p>

<?php if ($message): ?>
  <div class="alert alert-<?php echo $message_type; ?> mt-3"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<h2 class="mt-5 mb-3">Open deliveries</h2>
<?php if ($open->num_rows === 0): ?>
  <div class="empty-state">
    <p class="mb-0">No deliveries waiting for a volunteer right now. Check back soon.</p>
  </div>
<?php else: ?>
  <div class="row g-4">
    <?php while ($o = $open->fetch_assoc()): ?>
      <div class="col-md-6 col-lg-4">
        <div class="ticket">
          <span class="ticket-eyebrow">Delivery #<?php echo $o['delivery_id']; ?></span>
          <h3 class="ticket-title"><?php echo htmlspecialchars($o['food_name']); ?></h3>
          <p class="ticket-body mb-1"><strong>Quantity:</strong> <?php echo htmlspecialchars($o['quantity']); ?></p>
          <p class="ticket-body mb-1"><strong>Pickup from:</strong> <?php echo htmlspecialchars($o['donor_name']); ?>, <?php echo htmlspecialchars($o['pickup_address']); ?></p>
          <p class="ticket-body mb-0"><strong>Deliver to:</strong> <?php echo htmlspecialchars($o['ngo_name']); ?><?php echo $o['ngo_address'] ? ', '.htmlspecialchars($o['ngo_address']) : ''; ?></p>
          <hr class="ticket-divider">
          <form method="POST" action="dashboard.php">
            <input type="hidden" name="delivery_id" value="<?php echo $o['delivery_id']; ?>">
            <input type="hidden" name="action" value="accept">
            <button type="submit" class="btn btn-primary w-100">Accept Delivery</button>
          </form>
        </div>
      </div>
    <?php endwhile; ?>
  </div>
<?php endif; ?>

<h2 class="mt-5 mb-3">My deliveries</h2>
<?php if ($mine->num_rows === 0): ?>
  <div class="empty-state">
    <p class="mb-0">You haven't accepted any deliveries yet.</p>
  </div>
<?php else: ?>
  <div class="row g-4">
    <?php while ($m = $mine->fetch_assoc()): ?>
      <div class="col-md-6 col-lg-4">
        <div class="ticket">
          <span class="stamp stamp-<?php echo $m['delivery_status']; ?>"><?php echo str_replace('_',' ',$m['delivery_status']); ?></span>
          <h3 class="ticket-title mt-2"><?php echo htmlspecialchars($m['food_name']); ?></h3>
          <p class="ticket-body mb-1"><strong>Pickup from:</strong> <?php echo htmlspecialchars($m['donor_name']); ?>, <?php echo htmlspecialchars($m['pickup_address']); ?></p>
          <p class="ticket-body mb-0"><strong>Deliver to:</strong> <?php echo htmlspecialchars($m['ngo_name']); ?><?php echo $m['ngo_address'] ? ', '.htmlspecialchars($m['ngo_address']) : ''; ?></p>
          <hr class="ticket-divider">

          <?php if ($m['delivery_status'] === 'assigned'): ?>
            <form method="POST" action="dashboard.php">
              <input type="hidden" name="delivery_id" value="<?php echo $m['delivery_id']; ?>">
              <input type="hidden" name="action" value="picked_up">
              <button type="submit" class="btn btn-info w-100">Mark Picked Up</button>
            </form>
          <?php elseif ($m['delivery_status'] === 'picked_up'): ?>
            <form method="POST" action="dashboard.php">
              <input type="hidden" name="delivery_id" value="<?php echo $m['delivery_id']; ?>">
              <input type="hidden" name="action" value="delivered">
              <button type="submit" class="btn btn-primary w-100">Mark Delivered</button>
            </form>
          <?php else: ?>
            <div class="text-center text-muted mono" style="font-size:0.85rem;">Rescue complete &#10003;</div>
          <?php endif; ?>
        </div>
      </div>
    <?php endwhile; ?>
  </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
