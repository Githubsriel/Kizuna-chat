<?php
include_once 'main.php';
// No additional session_start() call if main.php already handles it.
header('Content-Type: application/json');

// Optional: only allow logged-in users
if (!isset($_SESSION['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

// Fetch bubble colors of all users
$result = $con->query("SELECT id, bubble_color FROM accounts");

$colors = [];
while ($row = $result->fetch_assoc()) {
    $colors[$row['id']] = $row['bubble_color'];
}

echo json_encode([
    'status' => 'success',
    'colors' => $colors
]);
?>
