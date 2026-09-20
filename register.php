<?php
require_once 'config/db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$errors  = [];
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];
    $role     = $_POST['role'];
    $phone    = trim($_POST['phone']);
    $address  = trim($_POST['address']);

    if (empty($name) || empty($email) || empty($password) || empty($role)) {
        $errors[] = "Please fill all required fields.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }
    if ($password !== $confirm) {
        $errors[] = "Passwords do not match.";
    }
    if (!in_array($role, ['donor', 'ngo', 'volunteer'])) {
        $errors[] = "Please select a valid role.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = "An account with this email already exists.";
        }
        $stmt->close();
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        // Donors are auto-verified; NGOs and Volunteers need admin approval
        $is_verified = ($role === 'donor') ? 1 : 0;

        $stmt = $conn->prepare(
            "INSERT INTO users (name, email, password, role, phone, address, is_verified)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("ssssssi", $name, $email, $hashed_password, $role, $phone, $address, $is_verified);

        if ($stmt->execute()) {
            $success = ($role !== 'donor')
                ? "Registration successful! Your account is pending admin verification &mdash; you'll be able to log in once approved."
                : "Registration successful! You can now log in.";
        } else {
            $errors[] = "Something went wrong. Please try again.";
        }
        $stmt->close();
    }
}

include 'includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-md-7 col-lg-5">
    <div class="form-card">
      <span class="hero-eyebrow">Join the chain</span>
      <h2 class="mt-2">Create an account</h2>

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
        <div class="alert alert-success mt-3"><?php echo $success; ?></div>
        <div class="text-center mt-3">
          <a href="login.php" class="btn btn-primary px-4">Go to Login</a>
        </div>
      <?php else: ?>

      <form method="POST" action="register.php" class="mt-4" novalidate>
        <div class="mb-3">
          <label class="form-label">Full Name</label>
          <input type="text" name="name" class="form-control" required
                 value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" class="form-control" required
                 value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Phone Number</label>
          <input type="text" name="phone" class="form-control"
                 value="<?php echo isset($phone) ? htmlspecialchars($phone) : ''; ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Address</label>
          <textarea name="address" class="form-control" rows="2"><?php echo isset($address) ? htmlspecialchars($address) : ''; ?></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label">I am registering as</label>
          <select name="role" class="form-select" required>
            <option value="">-- Select Role --</option>
            <option value="donor" <?php echo (isset($role) && $role === 'donor') ? 'selected' : ''; ?>>Donor</option>
            <option value="ngo" <?php echo (isset($role) && $role === 'ngo') ? 'selected' : ''; ?>>NGO</option>
            <option value="volunteer" <?php echo (isset($role) && $role === 'volunteer') ? 'selected' : ''; ?>>Volunteer</option>
          </select>
          <div class="form-text">NGO and Volunteer accounts need admin verification before login.</div>
        </div>

        <div class="mb-3">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" required minlength="6">
        </div>

        <div class="mb-4">
          <label class="form-label">Confirm Password</label>
          <input type="password" name="confirm_password" class="form-control" required minlength="6">
        </div>

        <button type="submit" class="btn btn-primary w-100">Register</button>
      </form>

      <p class="text-center mt-3 mb-0">
        Already have an account? <a href="login.php">Login here</a>
      </p>

      <?php endif; ?>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
