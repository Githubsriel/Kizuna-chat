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
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';

    if ($receiver_id <= 0 || empty($message)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
        exit;
    }
    
    // Encrypt the text message using AES-256-GCM
    $cipher = "aes-256-gcm";
    $iv_length = openssl_cipher_iv_length($cipher); // typically 12 bytes
    $iv = openssl_random_pseudo_bytes($iv_length);
    $tag = "";
    $encrypted = openssl_encrypt($message, $cipher, ENCRYPTION_KEY, OPENSSL_RAW_DATA, $iv, $tag);
    if ($encrypted === false) {
        echo json_encode(['status' => 'error', 'message' => 'Encryption failed']);
        exit;
    }
    // Concatenate IV, tag, and ciphertext and then base64 encode
    $encrypted_message = base64_encode($iv . $tag . $encrypted);

    // Insert into private_messages with type 'text'
    $stmt = $con->prepare("INSERT INTO private_messages (sender_id, receiver_id, message, type) VALUES (?, ?, ?, 'text')");
    $stmt->bind_param("iis", $sender_id, $receiver_id, $encrypted_message);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Message sent']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to send message']);
    }
    $stmt->close();
}
?>
