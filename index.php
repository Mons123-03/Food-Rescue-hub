<?php include 'includes/header.php'; ?>

<section class="hero">
  <div class="row align-items-center">
    <div class="col-lg-8">
      <span class="hero-eyebrow">No. 1 surplus food network</span>
      <h1>Don't waste it.<br>Rescue it.</h1>
      <p class="lede mt-3">
        Restaurants and households post surplus food before it spoils.
        Verified NGOs request it. Volunteers carry it across town.
        Every handoff is logged &mdash; from crate to plate.
      </p>

      <?php if (!isset($_SESSION['user_id'])): ?>
        <div class="mt-4 d-flex gap-3 flex-wrap">
          <a href="register.php" class="btn btn-primary btn-lg">Become a Donor</a>
          <a href="register.php" class="btn btn-outline-primary btn-lg">Join as NGO / Volunteer</a>
        </div>
      <?php endif; ?>

      <div class="manifest">
        <div class="manifest-step">
          <span class="num">1</span>
          <span class="label">Donor posts food</span>
        </div>
        <span class="manifest-arrow">&rarr;</span>
        <div class="manifest-step">
          <span class="num">2</span>
          <span class="label">NGO requests it</span>
        </div>
        <span class="manifest-arrow">&rarr;</span>
        <div class="manifest-step">
          <span class="num">3</span>
          <span class="label">Admin approves</span>
        </div>
        <span class="manifest-arrow">&rarr;</span>
        <div class="manifest-step">
          <span class="num">4</span>
          <span class="label">Volunteer delivers</span>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="mt-5 pt-3">
  <h2 class="mb-4">Four roles, one rescue chain</h2>
  <div class="row g-4">
    <div class="col-md-6 col-lg-3">
      <div class="ticket role-card">
        <div class="role-icon">&#127813;</div>
        <h3 class="ticket-title">Donor</h3>
        <p class="ticket-body">Post surplus food with quantity, type, and pickup window before it goes to waste.</p>
        <hr class="ticket-divider">
        <span class="ticket-meta">Restaurants &middot; Events &middot; Households</span>
      </div>
    </div>
    <div class="col-md-6 col-lg-3">
      <div class="ticket role-card role-ngo">
        <div class="role-icon">&#129309;</div>
        <h3 class="ticket-title">NGO</h3>
        <p class="ticket-body">Browse available donations and request what your community needs.</p>
        <hr class="ticket-divider">
        <span class="ticket-meta">Verified accounts only</span>
      </div>
    </div>
    <div class="col-md-6 col-lg-3">
      <div class="ticket role-card role-volunteer">
        <div class="role-icon">&#128666;</div>
        <h3 class="ticket-title">Volunteer</h3>
        <p class="ticket-body">Accept approved deliveries, pick up from the donor, and hand off to the NGO.</p>
        <hr class="ticket-divider">
        <span class="ticket-meta">Verified accounts only</span>
      </div>
    </div>
    <div class="col-md-6 col-lg-3">
      <div class="ticket role-card role-admin">
        <div class="role-icon">&#128737;</div>
        <h3 class="ticket-title">Admin</h3>
        <p class="ticket-body">Verifies NGOs and volunteers, approves requests, and oversees every donation.</p>
        <hr class="ticket-divider">
        <span class="ticket-meta">Platform oversight</span>
      </div>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
