<?php
// get_room_log.php
session_start();
include 'main.php';
header('Content-Type: application/json');

// Restrict access to moderators and administrators only
if (!isset($_SESSION['role']) || 
   ($_SESSION['role'] !== 'Moderator' && $_SESSION['role'] !== 'Admin')) {
    echo json_encode(['error' => 'Insufficient permissions']);
    exit;
}

// Validate room parameter
if (!isset($_GET['room']) || !is_numeric($_GET['room'])) {
    echo json_encode(['error' => 'Invalid room ID']);
    exit;
}
$room = intval($_GET['room']);

// Prepare query to fetch full log for the specified room, sorted newest first.
$stmt = $con->prepare("
    SELECT c.id, c.message, c.created_at, c.sender_id, a.username, c.type 
    FROM chat_messages c
    JOIN accounts a ON c.sender_id = a.id 
    WHERE c.room_id = ?
    ORDER BY c.created_at DESC
");

if (!$stmt) {
    echo json_encode(['error' => $con->error]);
    exit;
}
$stmt->bind_param("i", $room);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}
$stmt->close();

echo json_encode($messages);
exit;
?>
