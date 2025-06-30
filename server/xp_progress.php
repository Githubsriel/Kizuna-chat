<?php
include_once 'main.php';
// No additional session_start() call if main.php already handles it.

// Assuming you have the user's xp in a session or retrieved from a DB.
$userExp = $_SESSION['exp'] ?? 0;
$progressData = getLevelProgress($userExp);

header('Content-Type: application/json');
echo json_encode($progressData);
?>
