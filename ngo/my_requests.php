<?php
require_once '../config/db.php';
require_once '../includes/auth_check.php';
require_role(['ngo']);

$ngo_id = $_SESSION['user_id'];

// Cancel a pending request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action']) && $_POST['action'] === 'cancel') {
    $request_id = (int)$_POST['request_id'];
    $conn->begin_transaction();
    try {
        $u1 = $conn->prepare("UPDATE requests SET status='rejected' WHERE request_id=? AND ngo_id=? AND status='pending'");
        $u1->bind_param("ii", $request_id, $ngo_id);
        $u1->execute();
        $affected = $u1->affected_rows;
        $u1->close();
        if ($affected > 0) {
            // Get the donation_id to free it back up
            $sel = $conn->prepare("SELECT donation_id FROM requests WHERE request_id=?");
            $sel->bind_param("i", $request_id);
            $sel->execute();
            $rid = $sel->get_result()->fetch_assoc();
            $sel->close();
            $u2 = $conn->prepare("UPDATE donations SET status='available' WHERE donation_id=?");
            $u2->bind_param("i", $rid['donation_id']);
            $u2->execute();
            $u2->close();
        }
        $conn->commit();
        $message = "Request cancelled. The donation is back in the pool.";
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Could not cancel the request.";
    }
}

// Filter
$filter  = isset($_GET['status']) ? $_GET['status'] : 'all';
$labels  = ['all'=>'All','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'];
if (!array_key_exists($filter, $labels)) $filter = 'all';

if ($filter === 'all') {
    $stmt = $conn->prepare(
        "SELECT r.*, d.food_name, d.quantity, d.food_type, d.pickup_address, d.expiry_time,
                du.name as donor_name, del.status as delivery_status, del.delivery_id
         FROM requests r
         JOIN donations d ON r.donation_id = d.donation_id
         JOIN users du ON d.donor_id = du.user_id
         LEFT JOIN deliveries del ON del.request_id = r.request_id
         WHERE r.ngo_id = ? ORDER BY r.request_time DESC"
    );
    $stmt->bind_param("i", $ngo_id);
} else {
    $stmt = $conn->prepare(
        "SELECT r.*, d.food_name, d.quantity, d.food_type, d.pickup_address, d.expiry_time,
                du.name as donor_name, del.status as delivery_status, del.delivery_id
         FROM requests r
         JOIN donations d ON r.donation_id = d.donation_id
         JOIN users du ON d.donor_id = du.user_id
         LEFT JOIN deliveries del ON del.request_id = r.request_id
         WHERE r.ngo_id = ? AND r.status = ? ORDER BY r.request_time DESC"
    );
    $stmt->bind_param("is", $ngo_id, $filter);
}
$stmt->execute();
$requests = $stmt->get_result();

include '../includes/header.php';
?>

<link rel="stylesheet" href="/FoodRescueHub/css/dashboard.css">

<span class="hero-eyebrow">Your requests</span>
<h2 class="mt-2">My Requests</h2>
<p class="lede">Track every food request you've made and exactly where it is in the chain.</p>

<?php if (isset($message)): ?>
    <div class="alert alert-success mt-3"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="d-flex gap-2 flex-wrap mt-4 mb-4">
    <?php foreach ($labels as $k => $v): ?>
        <a href="my_requests.php?status=<?php echo $k; ?>"
           class="btn btn-sm <?php echo $filter===$k ? 'btn-primary':'btn-outline-primary'; ?>">
            <?php echo $v; ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($requests->num_rows === 0): ?>
    <div class="empty-state">
        <p class="mb-2">No requests found in this category.</p>
        <a href="available_food.php" class="btn btn-primary btn-sm">Browse Available Food</a>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-manifest align-middle">
            <thead>
                <tr>
                    <th>Food</th>
                    <th>Quantity</th>
                    <th>Donor</th>
                    <th>Requested On</th>
                    <th>Request Status</th>
                    <th>Delivery Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($r = $requests->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($r['food_name']); ?></strong><br>
                            <span class="text-muted" style="font-size:0.8rem;text-transform:capitalize;"><?php echo $r['food_type']; ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($r['quantity']); ?></td>
                        <td><?php echo htmlspecialchars($r['donor_name']); ?></td>
                        <td class="mono"><?php echo date("d M, h:i A", strtotime($r['request_time'])); ?></td>
                        <td><span class="stamp stamp-<?php echo $r['status']; ?>"><?php echo $r['status']; ?></span></td>
                        <td>
                            <?php if ($r['delivery_status']): ?>
                                <span class="stamp stamp-<?php echo $r['delivery_status']; ?>"><?php echo str_replace('_',' ',$r['delivery_status']); ?></span>
                            <?php else: ?>
                                <span class="text-muted">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($r['status'] === 'pending'): ?>
                                <form method="POST" action="my_requests.php?status=<?php echo $filter; ?>"
                                      onsubmit="return confirm('Cancel this request?');">
                                    <input type="hidden" name="request_id" value="<?php echo $r['request_id']; ?>">
                                    <input type="hidden" name="action" value="cancel">
                                    <button type="submit" class="btn btn-danger btn-sm">Cancel</button>
                                </form>
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
