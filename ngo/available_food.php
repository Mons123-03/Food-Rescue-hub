<?php
require_once '../config/db.php';
require_once '../includes/auth_check.php';
require_role(['ngo']);

$ngo_id  = $_SESSION['user_id'];
$message = "";
$message_type = "success";

// Handle request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['donation_id'])) {
    $donation_id = (int)$_POST['donation_id'];

    // Check if NGO already requested this
    $check = $conn->prepare("SELECT request_id FROM requests WHERE donation_id = ? AND ngo_id = ?");
    $check->bind_param("ii", $donation_id, $ngo_id);
    $check->execute();
    $already = $check->get_result()->num_rows > 0;
    $check->close();

    if ($already) {
        $message = "You have already requested this donation.";
        $message_type = "danger";
    } else {
        $chk = $conn->prepare("SELECT status FROM donations WHERE donation_id = ?");
        $chk->bind_param("i", $donation_id);
        $chk->execute();
        $row = $chk->get_result()->fetch_assoc();
        $chk->close();

        if (!$row || $row['status'] !== 'available') {
            $message = "Sorry — that donation is no longer available.";
            $message_type = "danger";
        } else {
            $conn->begin_transaction();
            try {
                $ins = $conn->prepare("INSERT INTO requests (donation_id, ngo_id, status) VALUES (?, ?, 'pending')");
                $ins->bind_param("ii", $donation_id, $ngo_id);
                $ins->execute();
                $ins->close();
                $upd = $conn->prepare("UPDATE donations SET status = 'requested' WHERE donation_id = ?");
                $upd->bind_param("i", $donation_id);
                $upd->execute();
                $upd->close();
                $conn->commit();
                $message = "Request sent! Admin will review it shortly.";
            } catch (Exception $e) {
                $conn->rollback();
                $message = "Something went wrong. Please try again.";
                $message_type = "danger";
            }
        }
    }
}

// Filters
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';
$valid_types = ['all','veg','non-veg','packaged','cooked'];
if (!in_array($type_filter, $valid_types)) $type_filter = 'all';

$sort = isset($_GET['sort']) && $_GET['sort'] === 'newest' ? 'created_at DESC' : 'expiry_time ASC';

$where = "d.status = 'available' AND d.expiry_time > NOW()";
$params = [];
$types  = "";

if ($type_filter !== 'all') {
    $where .= " AND d.food_type = ?";
    $params[] = $type_filter;
    $types    .= "s";
}

$sql = "SELECT d.*, u.name as donor_name, u.phone as donor_phone
        FROM donations d
        JOIN users u ON d.donor_id = u.user_id
        WHERE $where ORDER BY $sort";

$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$donations = $stmt->get_result();

include '../includes/header.php';
?>

<link rel="stylesheet" href="/FoodRescueHub/css/dashboard.css">

<span class="hero-eyebrow">Browse</span>
<h2 class="mt-2">Available Food</h2>
<p class="lede">All unclaimed donations right now, sorted by urgency. Request what your community needs.</p>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> mt-3"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<!-- Filters -->
<div class="filter-bar d-flex flex-wrap align-items-center gap-3 mt-4 mb-4 p-3">
    <div class="d-flex gap-2 flex-wrap">
        <?php foreach (['all'=>'All Types','veg'=>'Vegetarian','non-veg'=>'Non-Veg','packaged'=>'Packaged','cooked'=>'Cooked'] as $k => $v): ?>
            <a href="available_food.php?type=<?php echo $k; ?>&sort=<?php echo isset($_GET['sort'])?$_GET['sort']:'expiry'; ?>"
               class="btn btn-sm <?php echo $type_filter===$k ? 'btn-primary':'btn-outline-primary'; ?>">
                <?php echo $v; ?>
            </a>
        <?php endforeach; ?>
    </div>
    <div class="ms-auto d-flex gap-2">
        <a href="available_food.php?type=<?php echo $type_filter; ?>&sort=expiry"
           class="btn btn-sm <?php echo (!isset($_GET['sort'])||$_GET['sort']==='expiry')?'btn-primary':'btn-outline-primary'; ?>">
            Expiring Soon
        </a>
        <a href="available_food.php?type=<?php echo $type_filter; ?>&sort=newest"
           class="btn btn-sm <?php echo (isset($_GET['sort'])&&$_GET['sort']==='newest')?'btn-primary':'btn-outline-primary'; ?>">
            Newest First
        </a>
    </div>
</div>

<?php if ($donations->num_rows === 0): ?>
    <div class="empty-state">
        <p class="mb-0">No donations available matching your filter right now. Try "All Types" or check back soon.</p>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php while ($d = $donations->fetch_assoc()):
            $hours_left = round((strtotime($d['expiry_time']) - time()) / 3600, 1);
            $urgent = $hours_left <= 3;
        ?>
            <div class="col-md-6 col-lg-4">
                <div class="ticket <?php echo $urgent ? 'ticket-urgent' : ''; ?>">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <span class="ticket-eyebrow text-capitalize"><?php echo htmlspecialchars($d['food_type']); ?></span>
                        <span class="stamp <?php echo $urgent ? 'stamp-rejected' : 'stamp-available'; ?>">
                            <?php echo $urgent ? '⚡ Expiring' : 'Fresh'; ?>
                        </span>
                    </div>
                    <h3 class="ticket-title"><?php echo htmlspecialchars($d['food_name']); ?></h3>
                    <p class="ticket-body">
                        <?php echo $d['description'] ? htmlspecialchars($d['description']) : 'No additional notes.'; ?>
                    </p>

                    <div class="info-grid mb-2">
                        <div class="info-item">
                            <span class="info-label">Quantity</span>
                            <span class="info-value"><?php echo htmlspecialchars($d['quantity']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Donor</span>
                            <span class="info-value"><?php echo htmlspecialchars($d['donor_name']); ?></span>
                        </div>
                        <div class="info-item" style="grid-column:1/-1">
                            <span class="info-label">Pickup From</span>
                            <span class="info-value"><?php echo htmlspecialchars($d['pickup_address']); ?></span>
                        </div>
                    </div>

                    <hr class="ticket-divider">

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="mono" style="font-size:0.78rem;color:var(--ink-soft);">
                            <?php echo $urgent
                                ? "⚠ Only {$hours_left}h left"
                                : "Best before: " . date("d M, h:i A", strtotime($d['expiry_time'])); ?>
                        </span>
                    </div>

                    <form method="POST" action="available_food.php?type=<?php echo $type_filter; ?>"
                          onsubmit="return confirm('Request this donation?');">
                        <input type="hidden" name="donation_id" value="<?php echo $d['donation_id']; ?>">
                        <button type="submit" class="btn btn-primary w-100">Request This Donation</button>
                    </form>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
