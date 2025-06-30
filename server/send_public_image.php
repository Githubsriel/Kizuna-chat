<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include_once 'main.php';
// No additional session_start() call if main.php already handles it.

if (!isset($_SESSION['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sender_id = $_SESSION['id'];
    // For public chat, we won't use a receiver_id.
    
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'Image upload error']);
        exit;
    }
    
    // Limit file size to 10MB.
    $maxSize = 10 * 1024 * 1024; // 10MB in bytes
    if ($_FILES['image']['size'] > $maxSize) {
        echo json_encode(['status' => 'error', 'message' => 'Image file too large. Maximum allowed size is 10MB.']);
        exit;
    }
    
    // Read the uploaded file content.
    $fileContent = file_get_contents($_FILES['image']['tmp_name']);
    
    // Encrypt the file using AES-256-GCM.
    $cipher = "aes-256-gcm";
    $iv_length = openssl_cipher_iv_length($cipher); // Typically 12 bytes for GCM.
    $iv = openssl_random_pseudo_bytes($iv_length);
    $tag = "";
    $encrypted = openssl_encrypt($fileContent, $cipher, ENCRYPTION_KEY, OPENSSL_RAW_DATA, $iv, $tag);
    if ($encrypted === false) {
        echo json_encode(['status' => 'error', 'message' => 'Encryption failed']);
        exit;
    }
    // Concatenate IV + tag + ciphertext, then base64 encode.
    $encrypted_data = base64_encode($iv . $tag . $encrypted);
    
    // Generate a unique filename.
    $filename = uniqid('public_img_', true) . '.enc';
    $uploadDir = "server/encrypted_public_images/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $filePath = $uploadDir . $filename;
    
    if (file_put_contents($filePath, $encrypted_data) === false) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save encrypted image']);
        exit;
    }
    
    // Insert the public image message into chat_messages with type 'image'.
    // We assume that for public chat, the "message" column stores either encrypted text or the file path.
    $stmt = $con->prepare("INSERT INTO chat_messages (sender_id, message, type) VALUES (?, ?, 'image')");
    $stmt->bind_param("is", $sender_id, $filePath);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Image sent']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to send image']);
    }
    $stmt->close();
}
?>
