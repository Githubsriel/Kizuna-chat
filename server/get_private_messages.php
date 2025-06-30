<?php
include_once 'main.php';
// No additional session_start() call if main.php already handles it.

if (!isset($_SESSION['id'])) {
    echo json_encode([]);
    exit;
}

$currentUser = $_SESSION['id'];
$partner_id = isset($_GET['partner_id']) ? intval($_GET['partner_id']) : 0;
if ($partner_id <= 0) {
    echo json_encode([]);
    exit;
}

$stmt = $con->prepare("SELECT id, sender_id, receiver_id, message, type, created_at 
                       FROM private_messages 
                       WHERE (sender_id = ? AND receiver_id = ?) 
                          OR (sender_id = ? AND receiver_id = ?)
                       ORDER BY created_at ASC");
$stmt->bind_param("iiii", $currentUser, $partner_id, $partner_id, $currentUser);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
$cipher = "aes-256-gcm";
$iv_length = openssl_cipher_iv_length($cipher); // e.g., 12 bytes for GCM
$tag_length = 16; // typical tag length
while ($row = $result->fetch_assoc()) {
    if ($row['type'] == 'text') {
        // Decrypt text message.
        $encrypted_data = base64_decode($row['message']);
        if (strlen($encrypted_data) < ($iv_length + $tag_length)) {
            $row['message'] = "";
        } else {
            $iv = substr($encrypted_data, 0, $iv_length);
            $tag = substr($encrypted_data, $iv_length, $tag_length);
            $ciphertext = substr($encrypted_data, $iv_length + $tag_length);
            $decrypted = openssl_decrypt($ciphertext, $cipher, ENCRYPTION_KEY, OPENSSL_RAW_DATA, $iv, $tag);
            $row['message'] = $decrypted;
        }
    } else if ($row['type'] == 'image') {
        // For images, provide a URL to the get_private_image.php endpoint.
        $row['image_url'] = "server/get_private_image.php?file=" . urlencode($row['message']);
    }
    $messages[] = $row;
}
$stmt->close();
echo json_encode($messages);
?>
