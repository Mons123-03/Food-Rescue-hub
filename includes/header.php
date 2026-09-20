<?php if (session_status() === PHP_SESSION_NONE) { session_start(); } ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Food Rescue Hub</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Anton&family=Work+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="/FoodRescueHub/css/style.css">
</head>
<body>

<nav class="site-nav">
  <div class="container">
    <a class="nav-brand" href="/FoodRescueHub/index.php">
      <span class="crate-icon"></span> FOOD RESCUE HUB
    </a>

    <ul class="nav-links">
      <?php if (isset($_SESSION['user_id'])):
        $role    = $_SESSION['role'];
        $current = basename($_SERVER['PHP_SELF']);
      ?>

        <?php if ($role === 'donor'): ?>
          <li><a href="/FoodRescueHub/donor/dashboard.php"     class="<?php echo $current==='dashboard.php'    ?'active':''; ?>">Dashboard</a></li>
          <li><a href="/FoodRescueHub/donor/donate_food.php"   class="<?php echo $current==='donate_food.php'  ?'active':''; ?>">Donate Food</a></li>
          <li><a href="/FoodRescueHub/donor/my_listings.php"   class="<?php echo $current==='my_listings.php'  ?'active':''; ?>">My Listings</a></li>

        <?php elseif ($role === 'ngo'): ?>
          <li><a href="/FoodRescueHub/ngo/dashboard.php"       class="<?php echo $current==='dashboard.php'    ?'active':''; ?>">Dashboard</a></li>
          <li><a href="/FoodRescueHub/ngo/available_food.php"  class="<?php echo $current==='available_food.php'?'active':''; ?>">Browse Food</a></li>
          <li><a href="/FoodRescueHub/ngo/my_requests.php"     class="<?php echo $current==='my_requests.php'  ?'active':''; ?>">My Requests</a></li>

        <?php elseif ($role === 'volunteer'): ?>
          <li><a href="/FoodRescueHub/volunteer/dashboard.php"           class="<?php echo $current==='dashboard.php'          ?'active':''; ?>">Dashboard</a></li>
          <li><a href="/FoodRescueHub/volunteer/assigned_deliveries.php" class="<?php echo $current==='assigned_deliveries.php'?'active':''; ?>">My Deliveries</a></li>

        <?php elseif ($role === 'admin'): ?>
          <li><a href="/FoodRescueHub/admin/dashboard.php"           class="<?php echo $current==='dashboard.php'          ?'active':''; ?>">Dashboard</a></li>
          <li><a href="/FoodRescueHub/admin/manage_requests.php"     class="<?php echo $current==='manage_requests.php'    ?'active':''; ?>">Requests</a></li>
          <li><a href="/FoodRescueHub/admin/manage_donations.php"    class="<?php echo $current==='manage_donations.php'   ?'active':''; ?>">Donations</a></li>
          <li><a href="/FoodRescueHub/admin/manage_ngos.php"         class="<?php echo $current==='manage_ngos.php'        ?'active':''; ?>">NGOs</a></li>
          <li><a href="/FoodRescueHub/admin/manage_volunteers.php"   class="<?php echo $current==='manage_volunteers.php'  ?'active':''; ?>">Volunteers</a></li>
          <li><a href="/FoodRescueHub/admin/reports.php"             class="<?php echo $current==='reports.php'            ?'active':''; ?>">Reports</a></li>
        <?php endif; ?>

        <li class="nav-user-tag"><?php echo htmlspecialchars($_SESSION['name']); ?> &middot; <?php echo strtoupper($role); ?></li>
        <li><a href="/FoodRescueHub/logout.php">Logout</a></li>

      <?php else: ?>
        <li><a href="/FoodRescueHub/login.php">Login</a></li>
        <li><a href="/FoodRescueHub/register.php" class="btn-nav-cta">Register</a></li>
      <?php endif; ?>
    </ul>
  </div>
</nav>

<main class="container py-5">
