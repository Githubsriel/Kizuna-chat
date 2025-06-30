<?php
require_once 'main.php'; // Includes DB connection ($con), assumes session started

header('Content-Type: application/json'); // Set JSON header for all responses

// Ensure user is logged in
if (!isset($_SESSION['loggedin'], $_SESSION['id'])) {
    echo json_encode(["success" => false, "message" => "Authentication required."]);
    exit;
}

// Get post ID from request body (sent via x-www-form-urlencoded from JS)
$post_id = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);

if (!$post_id) {
    echo json_encode(["success" => false, "message" => "Invalid or missing Post ID."]);
    exit;
}

$user_id = $_SESSION['id'];
$newState = ''; // Will be 'liked' or 'unliked'
$message = '';  // Optional feedback message

// Check if the user already liked the post
$alreadyLiked = false;
$stmtCheck = $con->prepare("SELECT 1 FROM post_likes WHERE post_id = ? AND user_id = ?");
if ($stmtCheck) {
    $stmtCheck->bind_param("ii", $post_id, $user_id);
    $stmtCheck->execute();
    $stmtCheck->store_result();
    $alreadyLiked = $stmtCheck->num_rows > 0;
    $stmtCheck->close();
} else {
     error_log("Prepare failed (check like): " . $con->error);
     echo json_encode(["success" => false, "message" => "Database error (check)."]);
     exit;
}

// --- Perform Like/Unlike Action within a Transaction ---
$con->begin_transaction();

try {
    if ($alreadyLiked) {
        // --- Unlike the post ---
        $stmtUnlike = $con->prepare("DELETE FROM post_likes WHERE post_id = ? AND user_id = ?");
        if (!$stmtUnlike) throw new Exception("Prepare failed (unlike): " . $con->error);
        $stmtUnlike->bind_param("ii", $post_id, $user_id);
        if (!$stmtUnlike->execute()) throw new Exception("Execute failed (unlike): " . $stmtUnlike->error);
        $stmtUnlike->close();
        $newState = 'unliked';
        //$message = 'Post unliked.'; // Message usually not needed for toggle

    } else {
        // --- Like the post ---
        $stmtLike = $con->prepare("INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)");
         if (!$stmtLike) throw new Exception("Prepare failed (like): " . $con->error);
        $stmtLike->bind_param("ii", $post_id, $user_id);
         if (!$stmtLike->execute()) {
             // Handle potential race condition / duplicate entry if constraint exists
             if ($con->errno == 1062) { // 1062 = Duplicate entry error code
                 // Already liked, treat as success but maybe log it?
                 $newState = 'liked'; // Assume it's now liked
             } else {
                 throw new Exception("Execute failed (like): " . $stmtLike->error);
             }
         } else {
              $newState = 'liked';
              //$message = 'Post liked.'; // Message usually not needed for toggle
         }
        if(isset($stmtLike)) $stmtLike->close(); // Close only if it was prepared
    }

    // --- Get updated like count and liker list (always run after action) ---
    $newTotalLikes = 0;
    $likerUsernames = [];
    $stmtGetLikers = $con->prepare("
        SELECT a.username
        FROM post_likes pl
        JOIN accounts a ON pl.user_id = a.id
        WHERE pl.post_id = ?
        ORDER BY a.username ASC
    ");
     if (!$stmtGetLikers) throw new Exception("Prepare failed (get likers): " . $con->error);
    $stmtGetLikers->bind_param("i", $post_id);
    if (!$stmtGetLikers->execute()) throw new Exception("Execute failed (get likers): " . $stmtGetLikers->error);
    $likersResult = $stmtGetLikers->get_result();
    while ($liker = $likersResult->fetch_assoc()) {
        $likerUsernames[] = htmlspecialchars($liker['username']); // Sanitize usernames for tooltip
    }
    $newTotalLikes = count($likerUsernames);
    $stmtGetLikers->close();

    // Commit the transaction
    $con->commit();

    // --- Prepare success response ---
    $likersTooltipText = $newTotalLikes > 0 ? "Liked by: " . implode(', ', $likerUsernames) : 'Be the first to like this!';
    echo json_encode([
        "success" => true,
        "newState" => $newState,
        "newTotal" => $newTotalLikes,
        "likersTooltip" => $likersTooltipText // Send tooltip text directly
    ]);

} catch (Exception $e) {
    $con->rollback(); // Rollback on any error during DB operations
    error_log("Like/Unlike failed for post $post_id, user $user_id: " . $e->getMessage());
    // Send generic error to client, specific error is logged
    echo json_encode(["success" => false, "message" => "An error occurred while updating the like status."]);
}

exit; // Terminate script
?>