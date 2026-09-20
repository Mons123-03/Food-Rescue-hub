<?php
require_once '../config/db.php';
require_once '../includes/auth_check.php';
require_role(['ngo']);

$ngo_id = $_SESSION['user_id'];
$message = "";
$message_type = "success";

// Handle a request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['donation_id'])) {
    $donation_id = (int)$_POST['donation_id'];

    // Re-check the donation is still available (avoid race conditions)
    $check = $conn->prepare("SELECT status FROM donations WHERE donation_id = ?");
    $check->bind_param("i", $donation_id);
    $check->execute();
    $row = $check->get_result()->fetch_assoc();
    $check->close();

    if (!$row || $row['status'] !== 'available') {
        $message = "Sorry — that donation was just claimed by another NGO.";
        $message_type = "danger";
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO requests (donation_id, ngo_id, status) VALUES (?, ?, 'pending')");
            $stmt->bind_param("ii", $donation_id, $ngo_id);
            $stmt->execute();
            $stmt->close();

            $update = $conn->prepare("UPDATE donations SET status = 'requested' WHERE donation_id = ?");
            $update->bind_param("i", $donation_id);
            $update->execute();
            $update->close();

            $conn->commit();
            $message = "Request sent! An admin will review it shortly.";
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Something went wrong. Please try again.";
            $message_type = "danger";
        }
    }
}

// Fetch all available donations, donor info included
$donations = $conn->query(
    "SELECT d.*, u.name as donor_name
     FROM donations d
     JOIN users u ON d.donor_id = u.user_id
     WHERE d.status = 'available' AND d.expiry_time > NOW()
     ORDER BY d.expiry_time ASC"
);

include '../includes/header.php';
?>

<span class="hero-eyebrow">Available now</span>
<h2 class="mt-2">Request Food</h2>
<p class="lede">These donations are unclaimed right now. Request the ones that fit your need — first come, first served.</p>

<?php if ($message): ?>
  <div class="alert alert-<?php echo $message_type; ?> mt-3"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if ($donations->num_rows === 0): ?>
  <div class="empty-state mt-4">
    <p class="mb-0">No donations available right now. Check back soon — new listings come in throughout the day.</p>
  </div>
<?php else: ?>
  <div class="row g-4 mt-1">
    <?php while ($d = $donations->fetch_assoc()):
        $hours_left = round((strtotime($d['expiry_time']) - time()) / 3600, 1);
    ?>
      <div class="col-md-6 col-lg-4">
        <div class="ticket">
          <span class="ticket-eyebrow text-capitalize"><?php echo htmlspecialchars($d['food_type']); ?></span>
          <h3 class="ticket-title"><?php echo htmlspecialchars($d['food_name']); ?></h3>
          <p class="ticket-body">
            <?php echo $d['description'] ? htmlspecialchars($d['description']) : 'No additional notes from the donor.'; ?>
          </p>
          <p class="ticket-body mb-1"><strong>Quantity:</strong> <?php echo htmlspecialchars($d['quantity']); ?></p>
          <p class="ticket-body mb-0"><strong>Pickup:</strong> <?php echo htmlspecialchars($d['pickup_address']); ?></p>

          <hr class="ticket-divider">

          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="stamp <?php echo $hours_left <= 3 ? 'stamp-rejected' : 'stamp-available'; ?>">
              <?php echo $hours_left <= 3 ? 'Expiring soon' : 'Fresh'; ?>
            </span>
            <span class="mono" style="font-size:0.78rem;color:var(--ink-soft);">
              by <?php echo date("d M, h:i A", strtotime($d['expiry_time'])); ?>
            </span>
          </div>

          <form method="POST" action="request_food.php" onsubmit="return confirm('Request this donation?');">
            <input type="hidden" name="donation_id" value="<?php echo $d['donation_id']; ?>">
            <button type="submit" class="btn btn-primary w-100">Request This</button>
          </form>

          <div class="ticket-meta">
            <span>Donor: <?php echo htmlspecialchars($d['donor_name']); ?></span>
          </div>
        </div>
      </div>
    <?php endwhile; ?>
  </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
