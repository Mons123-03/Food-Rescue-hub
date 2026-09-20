<?php
require_once '../config/db.php';
require_once '../includes/auth_check.php';
require_role(['donor']);

$donor_id = $_SESSION['user_id'];
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $food_name      = trim($_POST['food_name']);
    $description    = trim($_POST['description']);
    $quantity       = trim($_POST['quantity']);
    $food_type      = $_POST['food_type'];
    $pickup_address = trim($_POST['pickup_address']);
    $expiry_time    = $_POST['expiry_time'];

    if (empty($food_name) || empty($quantity) || empty($pickup_address) || empty($expiry_time)) {
        $errors[] = "Please fill all required fields.";
    }
    if (!in_array($food_type, ['veg','non-veg','packaged','cooked'])) {
        $errors[] = "Please select a valid food type.";
    }
    if (!empty($expiry_time) && strtotime($expiry_time) <= time()) {
        $errors[] = "Expiry time must be in the future.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare(
            "INSERT INTO donations (donor_id, food_name, description, quantity, food_type, pickup_address, expiry_time, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'available')"
        );
        $stmt->bind_param("issssss", $donor_id, $food_name, $description, $quantity, $food_type, $pickup_address, $expiry_time);

        if ($stmt->execute()) {
            $success = true;
        } else {
            $errors[] = "Something went wrong. Please try again.";
        }
        $stmt->close();
    }
}

include '../includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <div class="form-card">
      <span class="hero-eyebrow">New listing</span>
      <h2 class="mt-2">Donate Food</h2>
      <p class="lede">Tell us what you have, how much, and how long it's good for. It'll appear to NGOs the moment you submit.</p>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mt-3">
          <ul class="mb-0 ps-3">
            <?php foreach ($errors as $error): ?>
              <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="alert alert-success mt-3">Donation posted! It's now visible to NGOs.</div>
        <div class="d-flex gap-2 mt-3">
          <a href="dashboard.php" class="btn btn-primary">View Dashboard</a>
          <a href="donate_food.php" class="btn btn-outline-primary">Post Another</a>
        </div>
      <?php else: ?>

      <form method="POST" action="donate_food.php" class="mt-4" novalidate>
        <div class="row g-3">
          <div class="col-md-7">
            <label class="form-label">Food Name</label>
            <input type="text" name="food_name" class="form-control" required placeholder="e.g. Vegetable Biryani"
                   value="<?php echo isset($food_name) ? htmlspecialchars($food_name) : ''; ?>">
          </div>
          <div class="col-md-5">
            <label class="form-label">Quantity</label>
            <input type="text" name="quantity" class="form-control" required placeholder="e.g. Serves 25"
                   value="<?php echo isset($quantity) ? htmlspecialchars($quantity) : ''; ?>">
          </div>

          <div class="col-12">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3" placeholder="Any details NGOs should know — allergens, packaging, condition..."><?php echo isset($description) ? htmlspecialchars($description) : ''; ?></textarea>
          </div>

          <div class="col-md-6">
            <label class="form-label">Food Type</label>
            <select name="food_type" class="form-select" required>
              <option value="cooked" <?php echo (isset($food_type) && $food_type==='cooked') ? 'selected':''; ?>>Cooked Meal</option>
              <option value="veg" <?php echo (isset($food_type) && $food_type==='veg') ? 'selected':''; ?>>Vegetarian</option>
              <option value="non-veg" <?php echo (isset($food_type) && $food_type==='non-veg') ? 'selected':''; ?>>Non-Vegetarian</option>
              <option value="packaged" <?php echo (isset($food_type) && $food_type==='packaged') ? 'selected':''; ?>>Packaged / Sealed</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Best Before</label>
            <input type="datetime-local" name="expiry_time" class="form-control" required
                   value="<?php echo isset($expiry_time) ? htmlspecialchars($expiry_time) : ''; ?>">
          </div>

          <div class="col-12">
            <label class="form-label">Pickup Address</label>
            <textarea name="pickup_address" class="form-control" rows="2" required placeholder="Where should the volunteer collect this from?"><?php echo isset($pickup_address) ? htmlspecialchars($pickup_address) : ''; ?></textarea>
          </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 mt-4">Post Donation</button>
      </form>

      <?php endif; ?>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
