<?php
include 'main.php';
check_loggedin($con);
?>
<?php
$redirectUrl = '../index.php'; // Change this URL to your destination

// Check if headers are not already sent
if (!headers_sent()) {
    header("Location: $redirectUrl");
    exit();
} else {
    // Fallback using JavaScript and meta refresh if headers have already been sent
    echo "<script type='text/javascript'>";
    echo "window.location.href='$redirectUrl';";
    echo "</script>";
    echo "<noscript>";
    echo "<meta http-equiv='refresh' content='0;url=$redirectUrl' />";
    echo "</noscript>";
}
?>
