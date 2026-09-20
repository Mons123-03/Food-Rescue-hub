<?php
require_once '../config/db.php';
require_once '../includes/auth_check.php';
require_role(['admin']);

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action     = $_POST['action'];

    $sel = $conn->prepare("SELECT donation_id FROM requests WHERE request_id=? AND status='pending'");
    $sel->bind_param("i", $request_id);
    $sel->execute();
    $row = $sel->get_result()->fetch_assoc();
    $sel->close();

    if ($row) {
        $donation_id = $row['donation_id'];
        if ($action === 'approve') {
            $conn->begin_transaction();
            try {
                $u1 = $conn->prepare("UPDATE requests SET status='approved', decision_time=NOW() WHERE request_id=?");
                $u1->bind_param("i", $request_id); $u1->execute(); $u1->close();
                $u2 = $conn->prepare("UPDATE donations SET status='approved' WHERE donation_id=?");
                $u2->bind_param("i", $donation_id); $u2->execute(); $u2->close();
                $u3 = $conn->prepare("INSERT INTO deliveries (request_id, status) VALUES (?, 'open')");
                $u3->bind_param("i", $request_id); $u3->execute(); $u3->close();
                $conn->commit();
                $message = "Request approved — delivery is now open for volunteers.";
            } catch (Exception $e) {
                $conn->rollback();
                $message = "Something went wrong. Please try again.";
            }
        } elseif ($action === 'reject') {
            $u1 = $conn->prepare("UPDATE requests SET status='rejected', decision_time=NOW() WHERE request_id=?");
            $u1->bind_param("i", $request_id); $u1->execute(); $u1->close();
            $u2 = $conn->prepare("UPDATE donations SET status='available' WHERE donation_id=?");
            $u2->bind_param("i", $donation_id); $u2->execute(); $u2->close();
            $message = "Request rejected. Donation is back as available.";
        }
    }
}

$filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$labels = ['all'=>'All','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'];
if (!array_key_exists($filter, $labels)) $filter = 'all';

$base_sql = "SELECT r.request_id, r.status, r.request_time, r.decision_time,
                    d.food_name, d.quantity, d.food_type, d.expiry_time, d.status as donation_status,
                    don.name as donor_name, ngo.name as ngo_name
             FROM requests r
             JOIN donations d ON r.donation_id = d.donation_id
             JOIN users don ON d.donor_id = don.user_id
             JOIN users ngo ON r.ngo_id = ngo.user_id";

if ($filter === 'all') {
    $requests = $conn->query($base_sql . " ORDER BY r.request_time DESC");
} else {
    $stmt = $conn->prepare($base_sql . " WHERE r.status=? ORDER BY r.request_time DESC");
    $stmt->bind_param("s", $filter);
    $stmt->execute();
    $requests = $stmt->get_result();
}

include '../includes/header.php';
?>

<link rel="stylesheet" href="/FoodRescueHub/css/dashboard.css">

<span class="hero-eyebrow">Admin</span>
<h2 class="mt-2">Manage Requests</h2>
<p class="lede">Review every NGO food request. Approve to open a delivery slot, reject to free the donation back up.</p>

<?php if ($message): ?>
    <div class="alert alert-success mt-3"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="d-flex gap-2 flex-wrap mt-4 mb-4">
    <?php foreach ($labels as $k => $v): ?>
        <a href="manage_requests.php?status=<?php echo $k; ?>"
           class="btn btn-sm <?php echo $filter===$k ? 'btn-primary':'btn-outline-primary'; ?>">
            <?php echo $v; ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($requests->num_rows === 0): ?>
    <div class="empty-state"><p class="mb-0">No requests found in this category.</p></div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-manifest align-middle">
            <thead>
                <tr><th>Food</th><th>Type</th><th>Donor</th><th>NGO</th><th>Requested</th><th>Status</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php while ($r = $requests->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($r['food_name']); ?></strong><br>
                            <span class="text-muted" style="font-size:0.8rem;"><?php echo htmlspecialchars($r['quantity']); ?></span>
                        </td>
                        <td class="text-capitalize"><?php echo htmlspecialchars($r['food_type']); ?></td>
                        <td><?php echo htmlspecialchars($r['donor_name']); ?></td>
                        <td><?php echo htmlspecialchars($r['ngo_name']); ?></td>
                        <td class="mono"><?php echo date("d M, h:i A", strtotime($r['request_time'])); ?></td>
                        <td><span class="stamp stamp-<?php echo $r['status']; ?>"><?php echo $r['status']; ?></span></td>
                        <td>
                            <?php if ($r['status'] === 'pending'): ?>
                                <div class="d-flex gap-1">
                                    <form method="POST">
                                        <input type="hidden" name="request_id" value="<?php echo $r['request_id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button class="btn btn-primary btn-sm">Approve</button>
                                    </form>
                                    <form method="POST">
                                        <input type="hidden" name="request_id" value="<?php echo $r['request_id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button class="btn btn-danger btn-sm">Reject</button>
                                    </form>
                                </div>
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
