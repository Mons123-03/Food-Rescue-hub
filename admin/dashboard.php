<?php
require_once '../config/db.php';
require_once '../includes/auth_check.php';
require_role(['admin']);

$message = "";

// Handle verify / reject of NGO & volunteer accounts
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['action'])) {
    $uid = (int)$_POST['user_id'];
    if ($_POST['action'] === 'verify') {
        $stmt = $conn->prepare("UPDATE users SET is_verified = 1 WHERE user_id = ? AND is_verified = 0");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        $message = $affected > 0
            ? "Account verified."
            : "Nothing changed — that account may already be verified, or the page was stale. Refresh and try again.";
    } elseif ($_POST['action'] === 'reject') {
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ? AND is_verified = 0");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        $message = $affected > 0
            ? "Account removed."
            : "Nothing changed — that account may already be verified, or the page was stale. Refresh and try again.";
    }
}

// Platform stats
$stats = [];
$stats['donors']      = $conn->query("SELECT COUNT(*) c FROM users WHERE role='donor'")->fetch_assoc()['c'];
$stats['ngos']         = $conn->query("SELECT COUNT(*) c FROM users WHERE role='ngo' AND is_verified=1")->fetch_assoc()['c'];
$stats['volunteers']   = $conn->query("SELECT COUNT(*) c FROM users WHERE role='volunteer' AND is_verified=1")->fetch_assoc()['c'];
$stats['donations']    = $conn->query("SELECT COUNT(*) c FROM donations")->fetch_assoc()['c'];
$stats['completed']    = $conn->query("SELECT COUNT(*) c FROM donations WHERE status='completed'")->fetch_assoc()['c'];
$stats['pending_req']  = $conn->query("SELECT COUNT(*) c FROM requests WHERE status='pending'")->fetch_assoc()['c'];

// Accounts pending verification
$pending = $conn->query("SELECT user_id, name, email, role, created_at FROM users WHERE is_verified = 0 AND role IN ('ngo','volunteer') ORDER BY created_at ASC");

include '../includes/header.php';
?>

<span class="hero-eyebrow">Admin dashboard</span>
<h2 class="mt-2">Platform Overview</h2>
<p class="lede">Verify new accounts and keep an eye on the rescue pipeline.</p>

<?php if ($message): ?>
  <div class="alert alert-success mt-3"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="row g-3 mt-2">
  <div class="col-6 col-lg-3">
    <div class="stat-block">
      <div class="stat-number"><?php echo $stats['donors']; ?></div>
      <div class="stat-label">Donors</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-block accent-mango">
      <div class="stat-number"><?php echo $stats['ngos']; ?></div>
      <div class="stat-label">Verified NGOs</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-block accent-blue">
      <div class="stat-number"><?php echo $stats['volunteers']; ?></div>
      <div class="stat-label">Verified Volunteers</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-block accent-tomato">
      <div class="stat-number"><?php echo $stats['pending_req']; ?></div>
      <div class="stat-label">Requests awaiting you</div>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-6 col-lg-3">
    <div class="stat-block">
      <div class="stat-number"><?php echo $stats['donations']; ?></div>
      <div class="stat-label">Total donations posted</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-block">
      <div class="stat-number"><?php echo $stats['completed']; ?></div>
      <div class="stat-label">Successfully delivered</div>
    </div>
  </div>
  <div class="col-12 col-lg-6 d-flex align-items-center justify-content-lg-end">
    <a href="manage_donations.php" class="btn btn-primary">Review Requests &amp; Donations</a>
  </div>
</div>

<h2 class="mt-5 mb-3">Pending verifications</h2>
<?php if ($pending->num_rows === 0): ?>
  <div class="empty-state">
    <p class="mb-0">No accounts waiting for verification.</p>
  </div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-manifest align-middle">
      <thead>
        <tr><th>Name</th><th>Email</th><th>Role</th><th>Registered</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php while ($p = $pending->fetch_assoc()): ?>
          <tr>
            <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
            <td><?php echo htmlspecialchars($p['email']); ?></td>
            <td><span class="stamp stamp-pending text-capitalize"><?php echo htmlspecialchars($p['role']); ?></span></td>
            <td class="mono"><?php echo date("d M Y", strtotime($p['created_at'])); ?></td>
            <td>
              <div class="d-flex gap-2">
                <form method="POST" action="dashboard.php">
                  <input type="hidden" name="user_id" value="<?php echo $p['user_id']; ?>">
                  <input type="hidden" name="action" value="verify">
                  <button type="submit" class="btn btn-primary btn-sm">Verify</button>
                </form>
                <form method="POST" action="dashboard.php" onsubmit="return confirm('Reject and remove this account?');">
                  <input type="hidden" name="user_id" value="<?php echo $p['user_id']; ?>">
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

<?php include '../includes/footer.php'; ?>
