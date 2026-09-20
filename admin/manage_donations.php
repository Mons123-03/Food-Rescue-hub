<?php
require_once '../config/db.php';
require_once '../includes/auth_check.php';
require_role(['admin']);

$message = "";

// Handle approve/reject of a request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    $req = $conn->prepare("SELECT donation_id FROM requests WHERE request_id = ? AND status = 'pending'");
    $req->bind_param("i", $request_id);
    $req->execute();
    $row = $req->get_result()->fetch_assoc();
    $req->close();

    if ($row) {
        $donation_id = $row['donation_id'];

        if ($action === 'approve') {
            $conn->begin_transaction();
            try {
                $u1 = $conn->prepare("UPDATE requests SET status = 'approved', decision_time = NOW() WHERE request_id = ?");
                $u1->bind_param("i", $request_id);
                $u1->execute();
                $u1->close();

                $u2 = $conn->prepare("UPDATE donations SET status = 'approved' WHERE donation_id = ?");
                $u2->bind_param("i", $donation_id);
                $u2->execute();
                $u2->close();

                $u3 = $conn->prepare("INSERT INTO deliveries (request_id, status) VALUES (?, 'open')");
                $u3->bind_param("i", $request_id);
                $u3->execute();
                $u3->close();

                $conn->commit();
                $message = "Request approved — it's now open for a volunteer to pick up.";
            } catch (Exception $e) {
                $conn->rollback();
                $message = "Something went wrong approving that request.";
            }
        } elseif ($action === 'reject') {
            $u1 = $conn->prepare("UPDATE requests SET status = 'rejected', decision_time = NOW() WHERE request_id = ?");
            $u1->bind_param("i", $request_id);
            $u1->execute();
            $u1->close();

            // Reopen the donation so other NGOs can request it
            $u2 = $conn->prepare("UPDATE donations SET status = 'available' WHERE donation_id = ?");
            $u2->bind_param("i", $donation_id);
            $u2->execute();
            $u2->close();

            $message = "Request rejected. The donation is available again.";
        }
    }
}

// Pending requests needing a decision
$pending = $conn->query(
    "SELECT r.request_id, r.request_time, d.food_name, d.quantity, d.expiry_time,
            don.name as donor_name, ngo.name as ngo_name
     FROM requests r
     JOIN donations d ON r.donation_id = d.donation_id
     JOIN users don ON d.donor_id = don.user_id
     JOIN users ngo ON r.ngo_id = ngo.user_id
     WHERE r.status = 'pending'
     ORDER BY r.request_time ASC"
);

// All donations overview
$all = $conn->query(
    "SELECT d.donation_id, d.food_name, d.quantity, d.status, d.created_at, don.name as donor_name
     FROM donations d
     JOIN users don ON d.donor_id = don.user_id
     ORDER BY d.created_at DESC
     LIMIT 50"
);

include '../includes/header.php';
?>

<span class="hero-eyebrow">Pipeline control</span>
<h2 class="mt-2">Manage Donations</h2>
<p class="lede">Approve or reject NGO requests, then watch each donation move through the chain.</p>

<?php if ($message): ?>
  <div class="alert alert-success mt-3"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<h2 class="mt-5 mb-3">Requests awaiting decision</h2>
<?php if ($pending->num_rows === 0): ?>
  <div class="empty-state">
    <p class="mb-0">No pending requests right now.</p>
  </div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-manifest align-middle">
      <thead>
        <tr><th>Food</th><th>Donor</th><th>NGO</th><th>Requested</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php while ($p = $pending->fetch_assoc()): ?>
          <tr>
            <td><strong><?php echo htmlspecialchars($p['food_name']); ?></strong><br>
              <span class="text-muted" style="font-size:0.8rem;"><?php echo htmlspecialchars($p['quantity']); ?></span></td>
            <td><?php echo htmlspecialchars($p['donor_name']); ?></td>
            <td><?php echo htmlspecialchars($p['ngo_name']); ?></td>
            <td class="mono"><?php echo date("d M, h:i A", strtotime($p['request_time'])); ?></td>
            <td>
              <div class="d-flex gap-2">
                <form method="POST" action="manage_donations.php">
                  <input type="hidden" name="request_id" value="<?php echo $p['request_id']; ?>">
                  <input type="hidden" name="action" value="approve">
                  <button type="submit" class="btn btn-primary btn-sm">Approve</button>
                </form>
                <form method="POST" action="manage_donations.php">
                  <input type="hidden" name="request_id" value="<?php echo $p['request_id']; ?>">
                  <input type="hidden" name="action" value="reject">
                  <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<h2 class="mt-5 mb-3">All donations</h2>
<div class="table-responsive">
  <table class="table table-manifest align-middle">
    <thead>
      <tr><th>#</th><th>Food</th><th>Donor</th><th>Quantity</th><th>Posted</th><th>Status</th></tr>
    </thead>
    <tbody>
      <?php while ($a = $all->fetch_assoc()): ?>
        <tr>
          <td class="mono"><?php echo $a['donation_id']; ?></td>
          <td><strong><?php echo htmlspecialchars($a['food_name']); ?></strong></td>
          <td><?php echo htmlspecialchars($a['donor_name']); ?></td>
          <td><?php echo htmlspecialchars($a['quantity']); ?></td>
          <td class="mono"><?php echo date("d M", strtotime($a['created_at'])); ?></td>
          <td><span class="stamp stamp-<?php echo $a['status']; ?>"><?php echo str_replace('_',' ',$a['status']); ?></span></td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

<?php include '../includes/footer.php'; ?>
