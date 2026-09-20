<?php
require_once '../config/db.php';
require_once '../includes/auth_check.php';
require_role(['admin']);

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['action'])) {
    $uid = (int)$_POST['user_id'];
    $action = $_POST['action'];

    if ($action === 'verify') {
        $s = $conn->prepare("UPDATE users SET is_verified=1 WHERE user_id=? AND role='volunteer'");
        $s->bind_param("i", $uid); $s->execute();
        $message = $s->affected_rows > 0 ? "Volunteer verified." : "Nothing changed.";
        $s->close();
    } elseif ($action === 'suspend') {
        $s = $conn->prepare("UPDATE users SET is_verified=0 WHERE user_id=? AND role='volunteer'");
        $s->bind_param("i", $uid); $s->execute();
        $message = $s->affected_rows > 0 ? "Volunteer suspended." : "Nothing changed.";
        $s->close();
    } elseif ($action === 'delete') {
        $s = $conn->prepare("DELETE FROM users WHERE user_id=? AND role='volunteer' AND is_verified=0");
        $s->bind_param("i", $uid); $s->execute();
        $message = $s->affected_rows > 0 ? "Volunteer removed." : "Cannot delete a volunteer with active deliveries.";
        $s->close();
    }
}

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
if (!in_array($filter, ['all','verified','pending'])) $filter = 'all';

if ($filter === 'verified') {
    $vols = $conn->query("SELECT u.*, COUNT(del.delivery_id) as total_deliveries FROM users u LEFT JOIN deliveries del ON del.volunteer_id=u.user_id WHERE u.role='volunteer' AND u.is_verified=1 GROUP BY u.user_id ORDER BY u.created_at DESC");
} elseif ($filter === 'pending') {
    $vols = $conn->query("SELECT u.*, 0 as total_deliveries FROM users u WHERE u.role='volunteer' AND u.is_verified=0 ORDER BY u.created_at DESC");
} else {
    $vols = $conn->query("SELECT u.*, COUNT(del.delivery_id) as total_deliveries FROM users u LEFT JOIN deliveries del ON del.volunteer_id=u.user_id WHERE u.role='volunteer' GROUP BY u.user_id ORDER BY u.is_verified DESC, u.created_at DESC");
}

include '../includes/header.php';
?>

<link rel="stylesheet" href="/FoodRescueHub/css/dashboard.css">

<span class="hero-eyebrow">Admin</span>
<h2 class="mt-2">Manage Volunteers</h2>
<p class="lede">Verify volunteers who'll be handling pickups and deliveries on behalf of NGOs.</p>

<?php if ($message): ?>
    <div class="alert alert-success mt-3"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="d-flex gap-2 flex-wrap mt-4 mb-4">
    <?php foreach (['all'=>'All','verified'=>'Verified','pending'=>'Pending'] as $k => $v): ?>
        <a href="manage_volunteers.php?filter=<?php echo $k; ?>"
           class="btn btn-sm <?php echo $filter===$k ? 'btn-primary':'btn-outline-primary'; ?>">
            <?php echo $v; ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($vols->num_rows === 0): ?>
    <div class="empty-state"><p class="mb-0">No volunteers found in this category.</p></div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-manifest align-middle">
            <thead>
                <tr><th>Name</th><th>Email</th><th>Phone</th><th>Deliveries Done</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php while ($v = $vols->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($v['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($v['email']); ?></td>
                        <td><?php echo htmlspecialchars($v['phone'] ?? '—'); ?></td>
                        <td class="mono"><?php echo $v['total_deliveries']; ?></td>
                        <td>
                            <?php if ($v['is_verified']): ?>
                                <span class="stamp stamp-available">Verified</span>
                            <?php else: ?>
                                <span class="stamp stamp-pending">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td class="mono"><?php echo date("d M Y", strtotime($v['created_at'])); ?></td>
                        <td>
                            <div class="d-flex gap-1 flex-wrap">
                                <?php if (!$v['is_verified']): ?>
                                    <form method="POST">
                                        <input type="hidden" name="user_id" value="<?php echo $v['user_id']; ?>">
                                        <input type="hidden" name="action" value="verify">
                                        <button class="btn btn-primary btn-sm">Verify</button>
                                    </form>
                                    <form method="POST" onsubmit="return confirm('Remove this volunteer?');">
                                        <input type="hidden" name="user_id" value="<?php echo $v['user_id']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button class="btn btn-danger btn-sm">Remove</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" onsubmit="return confirm('Suspend this volunteer?');">
                                        <input type="hidden" name="user_id" value="<?php echo $v['user_id']; ?>">
                                        <input type="hidden" name="action" value="suspend">
                                        <button class="btn btn-warning btn-sm">Suspend</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
