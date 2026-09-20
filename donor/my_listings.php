<?php
require_once '../config/db.php';
require_once '../includes/auth_check.php';
require_role(['donor']);

$donor_id = $_SESSION['user_id'];
$message = "";

// Withdraw a listing — only allowed while it's still unclaimed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['donation_id'], $_POST['action']) && $_POST['action'] === 'withdraw') {
    $donation_id = (int)$_POST['donation_id'];
    $stmt = $conn->prepare("DELETE FROM donations WHERE donation_id = ? AND donor_id = ? AND status = 'available'");
    $stmt->bind_param("ii", $donation_id, $donor_id);
    $stmt->execute();
    $message = $stmt->affected_rows > 0
        ? "Listing withdrawn."
        : "Couldn't withdraw that listing — it may have already been requested.";
    $stmt->close();
}

// Status filter
$labels = [
    'all'        => 'All',
    'available'  => 'Available',
    'requested'  => 'Requested',
    'approved'   => 'Approved',
    'in_transit' => 'In Transit',
    'completed'  => 'Completed',
    'rejected'   => 'Rejected',
    'expired'    => 'Expired',
];
$filter = isset($_GET['status']) && array_key_exists($_GET['status'], $labels) ? $_GET['status'] : 'all';

if ($filter === 'all') {
    $stmt = $conn->prepare("SELECT * FROM donations WHERE donor_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $donor_id);
} else {
    $stmt = $conn->prepare("SELECT * FROM donations WHERE donor_id = ? AND status = ? ORDER BY created_at DESC");
    $stmt->bind_param("is", $donor_id, $filter);
}
$stmt->execute();
$donations = $stmt->get_result();

include '../includes/header.php';
?>

<span class="hero-eyebrow">Full history</span>
<h2 class="mt-2">My Listings</h2>
<p class="lede">Every donation you've posted, filterable by where it stands in the rescue chain.</p>

<?php if ($message): ?>
  <div class="alert alert-success mt-3"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="d-flex flex-wrap gap-2 mt-4 mb-4">
  <?php foreach ($labels as $key => $label):
      $active = $filter === $key ? 'btn-primary' : 'btn-outline-primary';
  ?>
    <a href="my_listings.php?status=<?php echo $key; ?>" class="btn btn-sm <?php echo $active; ?>"><?php echo $label; ?></a>
  <?php endforeach; ?>
</div>

<?php if ($donations->num_rows === 0): ?>
  <div class="empty-state">
    <p class="mb-2">No listings <?php echo $filter !== 'all' ? 'in this category yet' : 'yet'; ?>.</p>
    <a href="donate_food.php" class="btn btn-primary btn-sm">Donate Food</a>
  </div>
<?php else: ?>
  <div class="row g-4">
    <?php while ($d = $donations->fetch_assoc()): ?>
      <div class="col-md-6 col-lg-4">
        <div class="ticket">
          <span class="ticket-eyebrow text-capitalize"><?php echo htmlspecialchars($d['food_type']); ?></span>
          <h3 class="ticket-title"><?php echo htmlspecialchars($d['food_name']); ?></h3>
          <p class="ticket-body mb-1">
            <?php echo $d['description'] ? htmlspecialchars($d['description']) : 'No additional notes.'; ?>
          </p>
          <p class="ticket-body mb-1"><strong>Quantity:</strong> <?php echo htmlspecialchars($d['quantity']); ?></p>
          <p class="ticket-body mb-0"><strong>Pickup:</strong> <?php echo htmlspecialchars($d['pickup_address']); ?></p>

          <hr class="ticket-divider">

          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="stamp stamp-<?php echo $d['status']; ?>"><?php echo str_replace('_',' ',$d['status']); ?></span>
            <span class="mono" style="font-size:0.78rem;color:var(--ink-soft);">
              <?php echo date("d M, h:i A", strtotime($d['expiry_time'])); ?>
            </span>
          </div>

          <?php if ($d['status'] === 'available'): ?>
            <form method="POST" action="my_listings.php" onsubmit="return confirm('Withdraw this listing? This can\'t be undone.');">
              <input type="hidden" name="donation_id" value="<?php echo $d['donation_id']; ?>">
              <input type="hidden" name="action" value="withdraw">
              <button type="submit" class="btn btn-outline-danger btn-sm w-100">Withdraw Listing</button>
            </form>
          <?php endif; ?>

          <div class="ticket-meta">
            <span>Posted <?php echo date("d M Y", strtotime($d['created_at'])); ?></span>
          </div>
        </div>
      </div>
    <?php endwhile; ?>
  </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
