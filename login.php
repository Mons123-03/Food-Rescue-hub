<?php
require_once 'config/db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (isset($_SESSION['user_id'])) {
    header("Location: " . $_SESSION['role'] . "/dashboard.php");
    exit();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $errors[] = "Please enter both email and password.";
    } else {
        $stmt = $conn->prepare("SELECT user_id, name, password, role, is_verified FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                if ($user['role'] !== 'admin' && $user['role'] !== 'donor' && $user['is_verified'] == 0) {
                    $errors[] = "Your account is still awaiting admin verification.";
                } else {
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['name']    = $user['name'];
                    $_SESSION['role']    = $user['role'];

                    header("Location: " . $user['role'] . "/dashboard.php");
                    exit();
                }
            } else {
                $errors[] = "Incorrect email or password.";
            }
        } else {
            $errors[] = "Incorrect email or password.";
        }
        $stmt->close();
    }
}

include 'includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-md-7 col-lg-5">
    <div class="form-card">
      <span class="hero-eyebrow">Welcome back</span>
      <h2 class="mt-2">Login</h2>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mt-3">
          <ul class="mb-0 ps-3">
            <?php foreach ($errors as $error): ?>
              <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="POST" action="login.php" class="mt-4" novalidate>
        <div class="mb-3">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" class="form-control" required
                 value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
        </div>

        <div class="mb-4">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-primary w-100">Login</button>
      </form>

      <p class="text-center mt-3 mb-0">
        Don't have an account? <a href="register.php">Register here</a>
      </p>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
