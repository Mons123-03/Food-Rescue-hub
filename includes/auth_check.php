<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Call at the top of any page that should only be visible to
 * logged-in users with one of the given roles.
 * Example: require_role(['donor']);
 */
function require_role($allowed_roles) {
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
        header("Location: /FoodRescueHub/login.php");
        exit();
    }
}
?>
