<?php
include_once 'main.php';
// No additional session_start() call if main.php already handles it.

if (!isset($_SESSION['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sender_id = $_SESSION['id'];
    $receiver_id = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : 0;
    if ($receiver_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid receiver']);
        exit;
    }
    
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'Image upload error']);
        exit;
    }
    
    // Check that the file is no larger than 10MB.
    $maxSize = 10 * 1024 * 1024; // 10MB in bytes
    if ($_FILES['image']['size'] > $maxSize) {
        echo json_encode(['status' => 'error', 'message' => 'Image file too large. Maximum allowed size is 10MB.']);
        exit;
    }
    
    // Read the uploaded file content.
    $fileContent = file_get_contents($_FILES['image']['tmp_name']);
    
    // Encrypt the file content using AES-256-GCM.
    $cipher = "aes-256-gcm";
    $iv_length = openssl_cipher_iv_length($cipher); // Typically 12 bytes for GCM
    $iv = openssl_random_pseudo_bytes($iv_length);
    $tag = "";
    $encrypted = openssl_encrypt($fileContent, $cipher, ENCRYPTION_KEY, OPENSSL_RAW_DATA, $iv, $tag);
    if ($encrypted === false) {
        echo json_encode(['status' => 'error', 'message' => 'Encryption failed']);
        exit;
    }
    // Concatenate IV, tag, and ciphertext then base64 encode.
    $encrypted_data = base64_encode($iv . $tag . $encrypted);
    
    // Generate a unique filename.
    $filename = uniqid('dm_img_', true) . '.enc';
    $uploadDir = "server/encrypted_dm_images/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $filePath = $uploadDir . $filename;
    
    // Save the encrypted file.
    if (file_put_contents($filePath, $encrypted_data) === false) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save encrypted image']);
        exit;
    }
    
    // Insert a new DM record with type 'image'.
    $stmt = $con->prepare("INSERT INTO private_messages (sender_id, receiver_id, message, type) VALUES (?, ?, ?, 'image')");
    $stmt->bind_param("iis", $sender_id, $receiver_id, $filePath);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Image sent']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to send image']);
    }
    $stmt->close();
}
?>
