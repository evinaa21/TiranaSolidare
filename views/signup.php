<?php
// HTML form for signup
include '../includes/header.php';
?>
<form method="POST" action="../actions/signup_action.php">
    <input type="text" name="emri" placeholder="Emri" required />
    <input type="email" name="email" placeholder="Email" required />
    <input type="password" name="password" placeholder="Password" required />
    <button type="submit">Sign Up</button>
</form>
<?php include '../includes/footer.php'; ?>
