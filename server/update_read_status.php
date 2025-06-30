<?php
include_once 'main.php';
// No additional session_start() call if main.php already handles it.

if (!isset($_SESSION['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

// Get the sender (DM partner) ID from POST.
if (!isset($_POST['sender_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No sender specified']);
    exit;
}

$sender_id = intval($_POST['sender_id']);
$receiver_id = $_SESSION['id'];

// Update messages from $sender_id to the current user that are not marked as read.
$stmt = $con->prepare("UPDATE private_messages SET `read` = 1 WHERE sender_id = ? AND receiver_id = ? AND `read` = 0");
$stmt->bind_param("ii", $sender_id, $receiver_id);
if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Update failed']);
}
$stmt->close();
?>
