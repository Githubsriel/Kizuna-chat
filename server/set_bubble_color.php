<?php
include_once 'main.php';
// No additional session_start() call if main.php already handles it.
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['id'];
$color = trim($_POST['color'] ?? '');

if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid color format']);
    exit;
}

$stmt = $con->prepare("UPDATE accounts SET bubble_color = ? WHERE id = ?");
$stmt->bind_param("si", $color, $user_id);
if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'color' => $color]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'DB error']);
}
$stmt->close();
?>