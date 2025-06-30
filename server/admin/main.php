<?php
// Include the root "main.php" file and check if user is logged-in...
include_once '../config.php';
include_once '../main.php';
check_loggedin($con, '../login.php');
$stmt = $con->prepare('SELECT password, email, role, username FROM accounts WHERE id = ?');
// Get the account info using the logged-in session ID
$stmt->bind_param('i', $_SESSION['id']);
$stmt->execute();
$stmt->bind_result($password, $email, $role, $username);
$stmt->fetch();
$stmt->close();
// Check if the user is an admin...
if ($role != 'Admin') {
    exit('You do not have permission to access this page!');
}
// Template admin header
function template_admin_header($title) {
echo <<<EOT
<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width,minimum-scale=1">
		<title>$title</title>
		<link href="admin.css" rel="stylesheet" type="text/css">
		<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.1/css/all.css">
	</head>
	<body class="admin">
        <header>
            <h1>Admin Panel</h1>
            <a class="responsive-toggle" href="#">
                <i class="fas fa-bars"></i>
            </a>
        </header>
            <aside class="responsive-width-100 responsive-hidden">
            <a href="index.php"><i class="fas fa-users"></i>Accounts</a>
            <a href="emailtemplate.php"><i class="fas fa-envelope"></i>Email Template</a>
            <a href="settings.php"><i class="fas fa-tools"></i>Settings</a>
            <a href="titles.php"><i class="fas fa-tag"></i>Titles</a>
			<a href="moderation_log.php"><i class="fas fa-gavel"></i> Moderation Log</a>
			<a href="transactions.php"><i class="fas fa-coins"></i> Transaction Log</a>
			<a href="rooms.php"><i class="fas fa-door-open"></i> Room Management</a>
			<a href="verlaufstext.php"><i class="fas fa-file-alt"></i> Verlaufstext</a>

            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i>Log Out</a>
        </aside>

        <main class="responsive-width-100">
EOT;
}
// Template admin footer
function template_admin_footer() {
echo <<<EOT
        </main>
        <script>
        document.querySelector(".responsive-toggle").onclick = function(event) {
            event.preventDefault();
            var aside_display = document.querySelector("aside").style.display;
            document.querySelector("aside").style.display = aside_display == "flex" ? "none" : "flex";
        };
        </script>
    </body>
</html>
EOT;
}
?>
