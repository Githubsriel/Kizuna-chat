<?php
include_once 'main.php';
check_loggedin($con);
?>

<div class="sidebar">
    <h2>Taddl-hub</h2>
    <ul>
        <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
        <li><a href="server/profile.php"><i class="fas fa-user-circle"></i> Profil</a></li>
        <?php if ($_SESSION['role'] == 'Admin'): ?>
            <li><a href="admin/index.php" target="_blank"><i class="fas fa-user-cog"></i> Admin</a></li>
        <?php endif; ?>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>
