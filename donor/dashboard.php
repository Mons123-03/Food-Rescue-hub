<?php
require_once '../config/db.php';
require_once '../includes/auth_check.php';
require_role(['donor']);

$donor_id = $_SESSION['user_id'];

// Stats
$stmt = $conn->prepare("SELECT status, COUNT(*) as c FROM donations WHERE donor_id = ? GROUP BY status");
$stmt->bind_param("i", $donor_id);
$stmt->execute();
$res = $stmt->get_result();
$counts = ['available'=>0,'requested'=>0,'approved'=>0,'in_transit'=>0,'completed'=>0,'rejected'=>0,'expired'=>0];
while ($row = $res->fetch_assoc()) { $counts[$row['status']] = (int)$row['c']; }
$stmt->close();

$total = array_sum($counts);

// Listings
$stmt = $conn->prepare("SELECT * FROM donations WHERE donor_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $donor_id);
$stmt->execute();
$donations = $stmt->get_result();

include '../includes/header.php';
?>

<span class="hero-eyebrow">Donor dashboard</span>
<h2 class="mt-2">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></h2>
<p class="lede">Here's everything you've posted, and where each donation stands right now.</p>

<div class="row g-3 mt-2">
  <div class="col-6 col-lg-3">
    <div class="stat-block">
      <div class="stat-number"><?php echo $total; ?></div>
      <div class="stat-label">Total posted</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-block accent-mango">
      <div class="stat-number"><?php echo $counts['available'] + $counts['requested']; ?></div>
      <div class="stat-label">Awaiting pickup</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-block accent-blue">
      <div class="stat-number"><?php echo $counts['approved'] + $counts['in_transit']; ?></div>
      <div class="stat-label">In transit</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-block">
      <div class="stat-number"><?php echo $counts['completed']; ?></div>
      <div class="stat-label">Delivered</div>
    </div>
  </div>
</div>

<div class="d-flex justify-content-between align-items-center mt-5 mb-3">
  <h2 class="mb-0">Your donations</h2>
  <a href="donate_food.php" class="btn btn-primary">+ Donate Food</a>
</div>

<?php if ($donations->num_rows === 0): ?>
  <div class="empty-state">
    <p class="mb-2">You haven't posted any donations yet.</p>
    <a href="donate_food.php" class="btn btn-primary btn-sm">Post your first donation</a>
  </div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-manifest align-middle">
      <thead>
        <tr>
          <th>Food</th>
          <th>Quantity</th>
          <th>Type</th>
          <th>Expires</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($d = $donations->fetch_assoc()): ?>
          <tr>
            <td><strong><?php echo htmlspecialchars($d['food_name']); ?></strong></td>
            <td><?php echo htmlspecialchars($d['quantity']); ?></td>
            <td class="text-capitalize"><?php echo htmlspecialchars($d['food_type']); ?></td>
            <td class="mono"><?php echo date("d M, h:i A", strtotime($d['expiry_time'])); ?></td>
            <td><span class="stamp stamp-<?php echo $d['status']; ?>"><?php echo str_replace('_',' ',$d['status']); ?></span></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
