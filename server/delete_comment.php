<?php
include_once 'main.php';
// No additional session_start() call if main.php already handles it.

// Ensure the user is logged in and is an admin
if (!isset($_SESSION['loggedin']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    echo json_encode(["success" => false, "message" => "Unauthorized."]);
    exit;
}

// Read JSON input
$data = json_decode(file_get_contents("php://input"), true);
$comment_id = intval($data['comment_id'] ?? 0);
if ($comment_id <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid comment ID."]);
    exit;
}

// Delete the comment from the database
$stmt = $con->prepare("DELETE FROM post_comments WHERE id = ?");
$stmt->bind_param("i", $comment_id);
if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "message" => "Error deleting comment: " . $stmt->error]);
}
$stmt->close();
?>
