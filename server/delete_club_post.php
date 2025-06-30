<?php
// No need for session_start() if main.php already does it. Remove if redundant.
// session_start();
require_once 'main.php'; // Includes DB connection ($con)

// Set content type to JSON for all responses
header('Content-Type: application/json');

// Ensure user is logged in and is an admin
if (!isset($_SESSION['loggedin']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    echo json_encode(["success" => false, "message" => "Unauthorized."]);
    exit;
}

// Read JSON input from the request body
$data = json_decode(file_get_contents("php://input"), true);
$post_id = filter_var($data['post_id'] ?? null, FILTER_VALIDATE_INT); // Validate/Sanitize ID

if (!$post_id) {
    echo json_encode(["success" => false, "message" => "Invalid or missing Post ID."]);
    exit;
}

// --- Deletion Process ---

// 1. Get the image path BEFORE deleting the post (if applicable)
$image_path = null;
$stmt_get_image = $con->prepare("SELECT image FROM club_posts WHERE id = ?");
if ($stmt_get_image) {
    $stmt_get_image->bind_param("i", $post_id);
    if ($stmt_get_image->execute()) {
        $result_image = $stmt_get_image->get_result();
        if ($row = $result_image->fetch_assoc()) {
            if (!empty($row['image'])) {
                 // Construct full server path. IMPORTANT: Adjust 'server/' if your
                 // uploads are stored differently relative to this script.
                 // Use __DIR__ for reliability. Assumes 'uploads/' is inside 'server/'.
                $image_path = __DIR__ . '/' . $row['image'];
            }
        }
    } else {
        error_log("Error executing get image statement: " . $stmt_get_image->error);
        // Decide if this error should stop the deletion process
    }
    $stmt_get_image->close();
} else {
     error_log("Error preparing get image statement: " . $con->error);
     // Decide if this error should stop the deletion process
}


// Start transaction for atomic deletion
$con->begin_transaction();

try {
    // 2. Delete associated comments
    $stmt_del_comments = $con->prepare("DELETE FROM post_comments WHERE post_id = ?");
    if (!$stmt_del_comments) throw new Exception("Prepare failed (comments): " . $con->error);
    $stmt_del_comments->bind_param("i", $post_id);
    if (!$stmt_del_comments->execute()) throw new Exception("Execute failed (comments): " . $stmt_del_comments->error);
    $stmt_del_comments->close();

    // 3. Delete associated likes
    $stmt_del_likes = $con->prepare("DELETE FROM post_likes WHERE post_id = ?");
     if (!$stmt_del_likes) throw new Exception("Prepare failed (likes): " . $con->error);
    $stmt_del_likes->bind_param("i", $post_id);
    if (!$stmt_del_likes->execute()) throw new Exception("Execute failed (likes): " . $stmt_del_likes->error);
    $stmt_del_likes->close();

    // 4. Delete the main post
    $stmt_del_post = $con->prepare("DELETE FROM club_posts WHERE id = ?");
     if (!$stmt_del_post) throw new Exception("Prepare failed (post): " . $con->error);
    $stmt_del_post->bind_param("i", $post_id);
    if (!$stmt_del_post->execute()) throw new Exception("Execute failed (post): " . $stmt_del_post->error);

    // Check if the post was actually deleted (optional, good practice)
    $deleted_rows = $stmt_del_post->affected_rows;
    $stmt_del_post->close();

    if ($deleted_rows > 0) {
        // Commit transaction ONLY if all DB deletions were successful
        $con->commit();

        // 5. Delete the image file AFTER successful DB commit
        if ($image_path && file_exists($image_path)) {
            if (!unlink($image_path)) {
                // Log error if image deletion fails, but report overall success
                error_log("Could not delete image file: " . $image_path);
            }
        }
        // Report success
        echo json_encode(["success" => true, "message" => "Post deleted successfully."]);

    } else {
        // Post ID didn't exist or deletion failed silently
        throw new Exception("Post not found or deletion failed (0 rows affected).");
    }

} catch (Exception $e) {
    // Roll back transaction on any error
    $con->rollback();
    error_log("Post deletion failed for ID $post_id: " . $e->getMessage()); // Log detailed error
    echo json_encode(["success" => false, "message" => "Error deleting post. Check server logs."]); // Generic message to user
}

exit; // Ensure no further output
?>