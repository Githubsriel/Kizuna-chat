<?php
// File: server/moderator/main.php

// Include the root config and main files (adjust path: two levels up)
// Assumes root main.php sets up session_start() and $con (database connection)
include_once '../config.php';
include_once '../main.php';

// Use the check_loggedin function (ensure it's available from root main.php)
// Adjust the redirect path to point to the site's home page if not logged in
check_loggedin($con, '../login.php');

// --- Permission Check ---
// Fetch the role of the currently logged-in user
$user_role = ''; // Initialize
$user_id = $_SESSION['id'] ?? null; // Get user ID from session

if ($user_id) {
    // Prepare statement to fetch only the role
    $stmt = $con->prepare('SELECT role FROM accounts WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->bind_result($user_role);
        $stmt->fetch();
        $stmt->close();
    } else {
        // Handle database prepare error
        error_log("Database prepare failed in moderator/main.php: " . $con->error);
        exit('A database error occurred.');
    }
} else {
    // If check_loggedin passed, ID should exist, but handle defensively
    exit('User session identifier not found.');
}

// Check if the user has permission (Allow 'Moderator' OR 'Admin')
// Admins can often perform moderator actions too. Adjust if only 'Moderator' should be allowed.
if ($user_role != 'Moderator' && $user_role != 'Admin') {
    exit('Permission Denied: You do not have the required permissions (Moderator or Admin) to access this page!');
}
// --- End Permission Check ---


// Template moderator header function
function template_moderator_header($title) {
    // CSS path is relative to the PHP file *including* this main.php
    // Option 1: Assume a moderator.css exists in the same directory (e.g., server/moderator/)
    $css_path = 'moderator.css';
    // Option 2: Reuse the admin CSS (adjust path)
    // $css_path = '../admin/admin.css';
    // Option 3: Use an absolute path if structure is fixed
    // $css_path = '/server/admin/admin.css'; // Example absolute path

echo <<<EOT
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,minimum-scale=1">
        <title>$title</title>
        <link href="$css_path" rel="stylesheet" type="text/css"> <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.1/css/all.css">
    </head>
    <body class="moderator"> <header>
            <h1>Moderator Panel</h1> <a class="responsive-toggle" href="#">
                <i class="fas fa-bars"></i>
            </a>
        </header>
        <aside class="responsive-width-100 responsive-hidden">
            <a href="../admin/index.php"><i class="fas fa-users"></i>Accounts</a> <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i>Log Out</a> </aside>
        <main class="responsive-width-100">
EOT;
}

// Template moderator footer function
function template_moderator_footer() {
    // This JS is simple and can likely be reused directly from the admin footer
echo <<<EOT
        </main>
        <script>
        // Basic responsive sidebar toggle
        let toggleBtn = document.querySelector(".responsive-toggle");
        if (toggleBtn) {
            toggleBtn.onclick = function(event) {
                event.preventDefault();
                let aside = document.querySelector("aside");
                if (aside) {
                    // You could toggle a class here instead for better CSS control
                    let currentDisplay = window.getComputedStyle(aside).display;
                    aside.style.display = currentDisplay === "flex" ? "none" : "flex";
                }
            };
        }
        </script>
    </body>
</html>
EOT;
}
?>