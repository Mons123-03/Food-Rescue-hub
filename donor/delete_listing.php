<?php
require_once '../config/db.php';
require_once '../includes/auth_check.php';
require_role(['donor']);

$donor_id    = $_SESSION['user_id'];
$donation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    $stmt = $conn->prepare("DELETE FROM donations WHERE donation_id = ? AND donor_id = ? AND status = 'available'");
    $stmt->bind_param("ii", $donation_id, $donor_id);
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();

    if ($deleted > 0) {
        header("Location: my_listings.php?deleted=1");
    } else {
        header("Location: my_listings.php?error=1");
    }
    exit();
}

// Fetch listing for confirmation screen
$stmt = $conn->prepare("SELECT * FROM donations WHERE donation_id = ? AND donor_id = ? AND status = 'available'");
$stmt->bind_param("ii", $donation_id, $donor_id);
$stmt->execute();
$donation = $stmt->get_result()->fetch_assoc();
$stmt->close();

include '../includes/header.php';
?>

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="my_listings.php" class="btn btn-outline-primary btn-sm">&larr; My Listings</a>
    <span class="hero-eyebrow mb-0">Confirm delete</span>
</div>

<?php if (!$donation): ?>
    <div class="alert alert-danger">
        This listing cannot be deleted — it may have already been requested or does not belong to you.
        <a href="my_listings.php">Back to My Listings</a>
    </div>
<?php else: ?>
    <h2 class="mt-0">Delete Listing</h2>

    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="form-card">
                <div class="alert alert-danger mb-4">
                    <strong>This action cannot be undone.</strong> The listing will be permanently removed.
                </div>

                <div class="ticket mb-4">
                    <span class="ticket-eyebrow text-capitalize"><?php echo htmlspecialchars($donation['food_type']); ?></span>
                    <h3 class="ticket-title"><?php echo htmlspecialchars($donation['food_name']); ?></h3>
                    <p class="ticket-body mb-1"><strong>Quantity:</strong> <?php echo htmlspecialchars($donation['quantity']); ?></p>
                    <p class="ticket-body mb-0"><strong>Pickup:</strong> <?php echo htmlspecialchars($donation['pickup_address']); ?></p>
                    <hr class="ticket-divider">
                    <div class="ticket-meta">
                        <span>Best before: <?php echo date("d M Y, h:i A", strtotime($donation['expiry_time'])); ?></span>
                    </div>
                </div>

                <form method="POST" action="delete_listing.php?id=<?php echo $donation_id; ?>">
                    <input type="hidden" name="confirm" value="1">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-danger flex-grow-1">Yes, Delete This Listing</button>
                        <a href="my_listings.php" class="btn btn-outline-primary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
