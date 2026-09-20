<?php
require_once '../config/db.php';
require_once '../includes/auth_check.php';
require_role(['admin']);

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['action'])) {
    $uid    = (int)$_POST['user_id'];
    $action = $_POST['action'];

    if ($action === 'verify') {
        $s = $conn->prepare("UPDATE users SET is_verified=1 WHERE user_id=? AND role='ngo'");
        $s->bind_param("i", $uid); $s->execute();
        $message = $s->affected_rows > 0 ? "NGO verified successfully." : "Nothing changed.";
        $s->close();
    } elseif ($action === 'suspend') {
        $s = $conn->prepare("UPDATE users SET is_verified=0 WHERE user_id=? AND role='ngo'");
        $s->bind_param("i", $uid); $s->execute();
        $message = $s->affected_rows > 0 ? "NGO suspended." : "Nothing changed.";
        $s->close();
    } elseif ($action === 'delete') {
        $s = $conn->prepare("DELETE FROM users WHERE user_id=? AND role='ngo' AND is_verified=0");
        $s->bind_param("i", $uid); $s->execute();
        $message = $s->affected_rows > 0 ? "NGO account removed." : "Cannot delete a verified NGO with existing data.";
        $s->close();
    }
}

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$valid  = ['all','verified','pending'];
if (!in_array($filter, $valid)) $filter = 'all';

if ($filter === 'verified') {
    $ngos = $conn->query("SELECT u.*, COUNT(r.request_id) as total_requests FROM users u LEFT JOIN requests r ON r.ngo_id=u.user_id WHERE u.role='ngo' AND u.is_verified=1 GROUP BY u.user_id ORDER BY u.created_at DESC");
} elseif ($filter === 'pending') {
    $ngos = $conn->query("SELECT u.*, 0 as total_requests FROM users u WHERE u.role='ngo' AND u.is_verified=0 ORDER BY u.created_at DESC");
} else {
    $ngos = $conn->query("SELECT u.*, COUNT(r.request_id) as total_requests FROM users u LEFT JOIN requests r ON r.ngo_id=u.user_id WHERE u.role='ngo' GROUP BY u.user_id ORDER BY u.is_verified DESC, u.created_at DESC");
}

include '../includes/header.php';
?>

<link rel="stylesheet" href="/FoodRescueHub/css/dashboard.css">

<span class="hero-eyebrow">Admin</span>
<h2 class="mt-2">Manage NGOs</h2>
<p class="lede">Verify, suspend or remove NGO accounts. Only verified NGOs can request food.</p>

<?php if ($message): ?>
    <div class="alert alert-success mt-3"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="d-flex gap-2 flex-wrap mt-4 mb-4">
    <?php foreach (['all'=>'All','verified'=>'Verified','pending'=>'Pending'] as $k => $v): ?>
        <a href="manage_ngos.php?filter=<?php echo $k; ?>"
           class="btn btn-sm <?php echo $filter===$k ? 'btn-primary':'btn-outline-primary'; ?>">
            <?php echo $v; ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($ngos->num_rows === 0): ?>
    <div class="empty-state"><p class="mb-0">No NGOs found in this category.</p></div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-manifest align-middle">
            <thead>
                <tr><th>Name</th><th>Email</th><th>Phone</th><th>Requests Made</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php while ($n = $ngos->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($n['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($n['email']); ?></td>
                        <td><?php echo htmlspecialchars($n['phone'] ?? '—'); ?></td>
                        <td class="mono"><?php echo $n['total_requests']; ?></td>
                        <td>
                            <?php if ($n['is_verified']): ?>
                                <span class="stamp stamp-available">Verified</span>
                            <?php else: ?>
                                <span class="stamp stamp-pending">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td class="mono"><?php echo date("d M Y", strtotime($n['created_at'])); ?></td>
                        <td>
                            <div class="d-flex gap-1 flex-wrap">
                                <?php if (!$n['is_verified']): ?>
                                    <form method="POST">
                                        <input type="hidden" name="user_id" value="<?php echo $n['user_id']; ?>">
                                        <input type="hidden" name="action" value="verify">
                                        <button class="btn btn-primary btn-sm">Verify</button>
                                    </form>
                                    <form method="POST" onsubmit="return confirm('Remove this NGO account?');">
                                        <input type="hidden" name="user_id" value="<?php echo $n['user_id']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button class="btn btn-danger btn-sm">Remove</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" onsubmit="return confirm('Suspend this NGO?');">
                                        <input type="hidden" name="user_id" value="<?php echo $n['user_id']; ?>">
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
