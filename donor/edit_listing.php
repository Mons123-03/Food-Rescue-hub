<?php
require_once '../config/db.php';
require_once '../includes/auth_check.php';
require_role(['donor']);

$donor_id = $_SESSION['user_id'];
$errors = [];
$success = false;

$donation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT * FROM donations WHERE donation_id = ? AND donor_id = ? AND status = 'available'");
$stmt->bind_param("ii", $donation_id, $donor_id);
$stmt->execute();
$donation = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$donation) {
    include '../includes/header.php';
    echo '<div class="alert alert-danger">Listing not found, already claimed, or you do not have permission to edit it. <a href="my_listings.php">Back to My Listings</a></div>';
    include '../includes/footer.php';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $food_name      = trim($_POST['food_name']);
    $description    = trim($_POST['description']);
    $quantity       = trim($_POST['quantity']);
    $food_type      = $_POST['food_type'];
    $pickup_address = trim($_POST['pickup_address']);
    $expiry_time    = $_POST['expiry_time'];

    if (empty($food_name) || empty($quantity) || empty($pickup_address) || empty($expiry_time))
        $errors[] = "Please fill all required fields.";
    if (!in_array($food_type, ['veg','non-veg','packaged','cooked']))
        $errors[] = "Please select a valid food type.";
    if (!empty($expiry_time) && strtotime($expiry_time) <= time())
        $errors[] = "Expiry time must be in the future.";

    if (empty($errors)) {
        $stmt = $conn->prepare(
            "UPDATE donations SET food_name=?, description=?, quantity=?, food_type=?, pickup_address=?, expiry_time=?
             WHERE donation_id=? AND donor_id=? AND status='available'"
        );
        $stmt->bind_param("ssssssii", $food_name, $description, $quantity, $food_type, $pickup_address, $expiry_time, $donation_id, $donor_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $success = true;
            $donation['food_name']=$food_name; $donation['description']=$description;
            $donation['quantity']=$quantity; $donation['food_type']=$food_type;
            $donation['pickup_address']=$pickup_address; $donation['expiry_time']=$expiry_time;
        } else {
            $errors[] = "Nothing changed or something went wrong.";
        }
        $stmt->close();
    }
}

include '../includes/header.php';
?>

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="my_listings.php" class="btn btn-outline-primary btn-sm">&larr; My Listings</a>
    <span class="hero-eyebrow mb-0">Edit listing</span>
</div>
<h2 class="mt-0">Edit Donation</h2>
<p class="lede">Only listings that haven't been requested yet can be edited.</p>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger mt-3"><ul class="mb-0 ps-3"><?php foreach($errors as $e): ?><li><?php echo htmlspecialchars($e);?></li><?php endforeach;?></ul></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success mt-3">Listing updated successfully.</div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="form-card">
            <form method="POST" action="edit_listing.php?id=<?php echo $donation_id;?>" novalidate>
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label">Food Name</label>
                        <input type="text" name="food_name" class="form-control" required value="<?php echo htmlspecialchars($donation['food_name']);?>">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Quantity</label>
                        <input type="text" name="quantity" class="form-control" required value="<?php echo htmlspecialchars($donation['quantity']);?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($donation['description']);?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Food Type</label>
                        <select name="food_type" class="form-select" required>
                            <option value="cooked"   <?php echo $donation['food_type']==='cooked'   ?'selected':'';?>>Cooked Meal</option>
                            <option value="veg"      <?php echo $donation['food_type']==='veg'      ?'selected':'';?>>Vegetarian</option>
                            <option value="non-veg"  <?php echo $donation['food_type']==='non-veg'  ?'selected':'';?>>Non-Vegetarian</option>
                            <option value="packaged" <?php echo $donation['food_type']==='packaged' ?'selected':'';?>>Packaged / Sealed</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Best Before</label>
                        <input type="datetime-local" name="expiry_time" class="form-control" required
                               value="<?php echo date('Y-m-d\TH:i', strtotime($donation['expiry_time']));?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Pickup Address</label>
                        <textarea name="pickup_address" class="form-control" rows="2" required><?php echo htmlspecialchars($donation['pickup_address']);?></textarea>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary flex-grow-1">Save Changes</button>
                    <a href="my_listings.php" class="btn btn-outline-primary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
