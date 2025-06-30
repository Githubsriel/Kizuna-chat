<?php
require_once 'main.php';

// *** MANUALLY INCLUDE PARSEDOWN ***
// Make sure the path is correct relative to add_club_post.php
require_once __DIR__ . '/lib/Parsedown.php';

// Instantiate Parsedown
$parsedown = new Parsedown();
// Optional: $parsedown->setSafeMode(true); // Recommended (default)

// Ensure user is logged in
if (!isset($_SESSION['loggedin'], $_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit;
}

// Validate content
$content = trim($_POST['content'] ?? '');
if (empty($content)) {
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "message" => "Post content is required."]);
    exit;
}

// Initialize image path
$imagePathDatabase = ""; // Path to store in DB
$imagePathRelative = ""; // Path relative to server root for src attribute
$targetDir = "uploads/club_posts/"; // Path relative to server root

if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) { // Check error code explicitly
    // Ensure the base directory exists relative to the script's location
    $baseUploadDir = __DIR__ . '/' . $targetDir; // Use __DIR__ for reliability
    if (!is_dir($baseUploadDir)) {
        if (!mkdir($baseUploadDir, 0755, true)) {
             header('Content-Type: application/json');
             echo json_encode(["success" => false, "message" => "Failed to create upload directory."]);
             exit;
        }
    }

    // Sanitize filename (optional but recommended)
    $filename = preg_replace("/[^a-zA-Z0-9._-]/", "", basename($_FILES['image']['name']));
    $targetFile = $baseUploadDir . time() . "_" . $filename;
    $imagePathDatabase = $targetDir . time() . "_" . $filename; // Store this path in DB
    $imagePathRelative = $imagePathDatabase; // For use in <img> src

    // Validate file type and size (example - adjust as needed)
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 5 * 1024 * 1024; // 5 MB
    if (!in_array($_FILES['image']['type'], $allowedTypes)) {
         header('Content-Type: application/json');
         echo json_encode(["success" => false, "message" => "Invalid file type."]);
         exit;
    }
    if ($_FILES['image']['size'] > $maxSize) {
         header('Content-Type: application/json');
         echo json_encode(["success" => false, "message" => "File size exceeds limit."]);
         exit;
    }

    // Move the uploaded file
    if (!move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
         header('Content-Type: application/json');
         echo json_encode(["success" => false, "message" => "Failed to move uploaded file."]);
         exit;
    }
}

// Insert the post into the database
// Use the $imagePathDatabase which is relative to the web root (or however you structure it)
$stmt = $con->prepare("INSERT INTO club_posts (user_id, content, image, created_at) VALUES (?, ?, ?, NOW())");
if (!$stmt) {
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "message" => "Database prepare error: " . $con->error]);
    exit;
}
$stmt->bind_param("iss", $_SESSION['id'], $content, $imagePathDatabase); // Store the relative path
if (!$stmt->execute()) {
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "message" => "Database execute error: " . $stmt->error]);
    $stmt->close();
    exit;
}
$post_id = $con->insert_id;
$stmt->close();

// Retrieve the new post data (including username) to build the HTML response
$stmt = $con->prepare("
    SELECT cp.id, cp.content, cp.image, cp.created_at, a.username
    FROM club_posts cp
    JOIN accounts a ON cp.user_id = a.id
    WHERE cp.id = ?
");
if (!$stmt) {
     header('Content-Type: application/json');
     echo json_encode(["success" => false, "message" => "Database prepare error (fetch): " . $con->error]);
     exit;
}
$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result();
$postData = $result->fetch_assoc();
$stmt->close();

if (!$postData) {
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "message" => "Could not retrieve the newly created post."]);
    exit;
}
$postDate = date("F j, Y, g:i a", strtotime($postData['created_at']));

// *** PROCESS POST CONTENT WITH PARSEDOWN ***
$processedContent = $parsedown->text($postData['content']);

// --- Build the HTML snippet matching index.php structure ---
$post_html = "
<div class='post-box' data-postid='" . htmlspecialchars($postData['id']) . "'>
  <div class='post-header'>
    <span class='posted-by'>Posted by " . htmlspecialchars($postData['username']) . "</span>
    <span class='post-date'>" . htmlspecialchars($postDate) . "</span>";
// Add delete button if admin is logged in
if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin') {
    $post_html .= " <button class='delete-post-btn' data-postid='" . htmlspecialchars($postData['id']) . "'>Delete</button>";
}
$post_html .= "
  </div>
  <div class='post-content'>";
// *** USE PROCESSED CONTENT *** (No htmlspecialchars needed here)
$post_html .= $processedContent;
// Display image if exists (use the relative path for src)
if (!empty($postData['image'])) {
    // IMPORTANT: Ensure $postData['image'] stores the path accessible via URL
    // If 'server/' is needed prepend it, otherwise use directly. Adjust if needed.
    $imgSrc = 'server/' . ltrim(htmlspecialchars($postData['image']), '/');
    $post_html .= "<img src='" . $imgSrc . "' alt='Club Post Image' style='max-width:100%; margin-top:10px;'>";
}
$post_html .= "
  </div>
  <div class='post-actions'>
    <button class='like-btn' data-postid='" . htmlspecialchars($postData['id']) . "'>Like</button>
    <span class='like-count' id='like-count-" . htmlspecialchars($postData['id']) . "'>0</span> likes
    <button class='toggle-comments-btn' data-postid='" . htmlspecialchars($postData['id']) . "'>
      Comments (<span class='comment-count'>0</span>)
    </button>
  </div>
  <div class='comments-section' id='comments-section-" . htmlspecialchars($postData['id']) . "' style='display: none;'>
    <div class='existing-comments' id='existing-comments-" . htmlspecialchars($postData['id']) . "'>
      </div>
    <form class='comment-form' data-postid='" . htmlspecialchars($postData['id']) . "'>
      <textarea name='comment' placeholder='Write a comment (Markdown supported)...' required></textarea>
      <button type='submit'>Submit Comment</button>
    </form>
  </div>
</div>"; // End post-box

// Send JSON Response
header('Content-Type: application/json');
echo json_encode([
    "success" => true,
    "post_html" => $post_html, // Send the fully structured, processed HTML
    "post_id" => $post_id
]);
exit; // Important to prevent any further output
?>
