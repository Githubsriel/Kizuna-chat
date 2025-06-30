<?php
include_once 'main.php';
// No additional session_start() call if main.php already handles it.

if (!isset($_SESSION['id'], $_POST['receiver_id'], $_POST['avatar_image'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}

$sender_id = $_SESSION['id'];
$receiver_id = (int)$_POST['receiver_id'];
$avatar_image = $_POST['avatar_image'];

// Insert gift into avatar_gifts
$stmt = $con->prepare("INSERT INTO avatar_gifts (sender_id, receiver_id, avatar_image) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $sender_id, $receiver_id, $avatar_image);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Avatar gifted successfully.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to send avatar gift.']);
}
$stmt->close();
