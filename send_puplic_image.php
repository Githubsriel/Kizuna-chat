<?php
include_once 'server/main.php';



header('Content-Type: application/json'); // Set response type

// --- Basic Setup & Validation ---
$uploadDir = 'server/uploads/public_chat_images/'; // Make sure this directory exists and is writable
$maxFileSize = 5 * 1024 * 1024; // 5 MB limit (adjust as needed)
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'Upload error or no file provided. Code: ' . ($_FILES['image']['error'] ?? 'N/A')]);
    exit;
}

$file = $_FILES['image'];

if ($file['size'] > $maxFileSize) {
    echo json_encode(['status' => 'error', 'message' => 'File size exceeds limit.']);
    exit;
}

// More robust type checking
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);
if (!in_array($mimeType, $allowedTypes)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid file type: ' . $mimeType]);
    exit;
}

// --- Generate Filename & Move File ---
$fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
$safeFilename = uniqid('img_', true) . '.' . strtolower($fileExtension); // Generate unique name
$destination = $uploadDir . $safeFilename;
$webPath = $destination; // Assuming the web server can directly serve files from this path relative to the web root

 // Create directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true); // Adjust permissions as needed, 0775 is usually safer
}


if (move_uploaded_file($file['tmp_name'], $destination)) {
    // --- Save to Database ---
    $senderId = $_SESSION['id'];
    // Note: Message column will be NULL or empty for images
    $messageType = 'image';

    $stmt = $con->prepare("INSERT INTO chat_messages (sender_id, message, message_type, image_url, created_at) VALUES (?, NULL, ?, ?, NOW())");
    if ($stmt === false) {
        // Log error: $con->error
         error_log("Prepare failed: (" . $con->errno . ") " . $con->error);
         echo json_encode(['status' => 'error', 'message' => 'Database prepare error.']);
         unlink($destination); // Clean up uploaded file if DB fails
         exit;
    }

    $stmt->bind_param("iss", $senderId, $messageType, $webPath);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Image uploaded.', 'image_url' => $webPath]);
    } else {
         error_log("Execute failed: (" . $stmt->errno . ") " . $stmt->error);
         echo json_encode(['status' => 'error', 'message' => 'Database error during insert.']);
         unlink($destination); // Clean up uploaded file if DB fails
    }
    $stmt->close();

} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to move uploaded file. Check permissions for ' . $uploadDir]);
}

$con->close(); // Close connection if appropriate here
?>
