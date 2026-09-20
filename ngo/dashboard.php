<?php
require_once '../config/db.php';
require_once '../includes/auth_check.php';
require_role(['ngo']);

$ngo_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT r.status, COUNT(*) c FROM requests r WHERE r.ngo_id = ? GROUP BY r.status");
$stmt->bind_param("i", $ngo_id);
$stmt->execute();
$res = $stmt->get_result();
$counts = ['pending'=>0,'approved'=>0,'rejected'=>0];
while ($row = $res->fetch_assoc()) { $counts[$row['status']] = (int)$row['c']; }
$stmt->close();
$total = array_sum($counts);

$stmt = $conn->prepare(
    "SELECT r.request_id, r.status as request_status, r.request_time,
            d.food_name, d.quantity, d.food_type, d.pickup_address, d.status as donation_status,
            del.status as delivery_status
     FROM requests r
     JOIN donations d ON r.donation_id = d.donation_id
     LEFT JOIN deliveries del ON del.request_id = r.request_id
     WHERE r.ngo_id = ?
     ORDER BY r.request_time DESC"
);
$stmt->bind_param("i", $ngo_id);
$stmt->execute();
$requests = $stmt->get_result();

include '../includes/header.php';
?>

<span class="hero-eyebrow">NGO dashboard</span>
<h2 class="mt-2">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></h2>
<p class="lede">Track every request you've made and where it stands in the rescue chain.</p>

<div class="row g-3 mt-2">
  <div class="col-6 col-lg-3">
    <div class="stat-block">
      <div class="stat-number"><?php echo $total; ?></div>
      <div class="stat-label">Total requests</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-block accent-mango">
      <div class="stat-number"><?php echo $counts['pending']; ?></div>
      <div class="stat-label">Awaiting admin</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-block accent-blue">
      <div class="stat-number"><?php echo $counts['approved']; ?></div>
      <div class="stat-label">Approved</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-block accent-tomato">
      <div class="stat-number"><?php echo $counts['rejected']; ?></div>
      <div class="stat-label">Rejected</div>
    </div>
  </div>
</div>

<div class="d-flex justify-content-between align-items-center mt-5 mb-3">
  <h2 class="mb-0">Your requests</h2>
  <a href="request_food.php" class="btn btn-primary">Browse Available Food</a>
</div>

<?php if ($requests->num_rows === 0): ?>
  <div class="empty-state">
    <p class="mb-2">You haven't requested any food yet.</p>
    <a href="request_food.php" class="btn btn-primary btn-sm">Browse available donations</a>
  </div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-manifest align-middle">
      <thead>
        <tr>
          <th>Food</th>
          <th>Quantity</th>
          <th>Requested</th>
          <th>Status</th>
          <th>Delivery</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($r = $requests->fetch_assoc()): ?>
          <tr>
            <td><strong><?php echo htmlspecialchars($r['food_name']); ?></strong></td>
            <td><?php echo htmlspecialchars($r['quantity']); ?></td>
            <td class="mono"><?php echo date("d M, h:i A", strtotime($r['request_time'])); ?></td>
            <td><span class="stamp stamp-<?php echo $r['request_status']; ?>"><?php echo $r['request_status']; ?></span></td>
            <td>
              <?php if ($r['delivery_status']): ?>
                <span class="stamp stamp-<?php echo $r['delivery_status']; ?>"><?php echo str_replace('_',' ',$r['delivery_status']); ?></span>
              <?php else: ?>
                <span class="text-muted">&mdash;</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
