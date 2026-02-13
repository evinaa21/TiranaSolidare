<?php
// views/dashboard.php
session_start();

// Security Check: If not logged in, kick them out
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <title>Paneli Kryesor</title>
</head>
<body>
    <h1>Mirësevini, <?php echo htmlspecialchars($_SESSION['emri']); ?>!</h1>
    
    <p>Roli juaj është: <strong><?php echo $_SESSION['roli']; ?></strong></p>

    <hr>
    
    <?php if ($_SESSION['roli'] == 'Admin'): ?>
        <h3>Paneli i Administratorit</h3>
        <ul>
            <li><a href="manage_events.php">Menaxho Eventet</a></li>
            <li><a href="users.php">Menaxho Përdoruesit</a></li>
        </ul>
    <?php else: ?>
        <h3>Paneli i Vullnetarit</h3>
        <ul>
            <li><a href="events.php">Shiko Eventet e Hapura</a></li>
            <li><a href="my_applications.php">Aplikimet e Mia</a></li>
        </ul>
    <?php endif; ?>

    <br>
    <a href="../actions/logout.php" style="background: red; color: white; padding: 5px 10px; text-decoration: none;">Dil (Logout)</a>

</body>
</html>