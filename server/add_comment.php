<?php
include_once 'main.php';


if (!isset($_SESSION['loggedin'])) {
    echo json_encode(["success" => false, "message" => "Not logged in"]);
    exit;
}

$post_id = intval($_POST['post_id'] ?? 0);
$comment = trim($_POST['comment'] ?? '');
if ($post_id <= 0 || empty($comment)) {
    echo json_encode(["success" => false, "message" => "Invalid input"]);
    exit;
}

// Insert the comment into the database
$stmt = $con->prepare("INSERT INTO post_comments (post_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("iis", $post_id, $_SESSION['id'], $comment);
if ($stmt->execute()) {
    $comment_id = $con->insert_id;
    $stmt->close();
    // Retrieve the newly added comment with the user's name
    $stmt = $con->prepare("SELECT pc.*, a.username FROM post_comments pc JOIN accounts a ON pc.user_id = a.id WHERE pc.id = ?");
    $stmt->bind_param("i", $comment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $commentData = $result->fetch_assoc();
    $stmt->close();
    $commentDate = date("F j, Y, g:i a", strtotime($commentData['created_at']));
    $comment_html = "<div class='comment'>
                        <span class='comment-author'>" . htmlspecialchars($commentData['username']) . "</span>
                        <span class='comment-date'>" . htmlspecialchars($commentDate) . "</span>
                        <p class='comment-content'>" . htmlspecialchars($commentData['content']) . "</p>
                     </div>";
    echo json_encode(["success" => true, "comment_html" => $comment_html]);
} else {
    echo json_encode(["success" => false, "message" => "Database error"]);
}
?>
