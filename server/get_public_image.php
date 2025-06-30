<?php
// get_public_image.php
session_start();
include 'main.php';

if (!isset($_SESSION['id'])) {
    http_response_code(403);
    exit;
}

if (!isset($_GET['file'])) {
    http_response_code(400);
    exit;
}

// Set the upload directory relative to this file.
$uploadDir = "encrypted_public_images/";
$file = $_GET['file'];
$filePath = realpath($uploadDir . basename($file));

// Security check: ensure file is within the upload directory.
if (!$filePath || strpos($filePath, realpath($uploadDir)) !== 0 || !file_exists($filePath)) {
    http_response_code(404);
    exit;
}

// Read the encrypted file (base64‑encoded).
$encrypted_data_b64 = file_get_contents($filePath);
$encrypted_data = base64_decode($encrypted_data_b64);

$cipher = "aes-256-gcm";
$iv_length = openssl_cipher_iv_length($cipher); // Typically 12 bytes.
$tag_length = 16; // Typical tag length for AES-GCM.

// Ensure the encrypted data is long enough.
if (strlen($encrypted_data) < ($iv_length + $tag_length)) {
    http_response_code(500);
    error_log("Encrypted data too short for file: $filePath");
    exit;
}

$iv = substr($encrypted_data, 0, $iv_length);
$tag = substr($encrypted_data, $iv_length, $tag_length);
$ciphertext = substr($encrypted_data, $iv_length + $tag_length);

$decrypted_image = openssl_decrypt($ciphertext, $cipher, ENCRYPTION_KEY, OPENSSL_RAW_DATA, $iv, $tag);
if ($decrypted_image === false) {
    http_response_code(500);
    error_log("Decryption failed for file: $filePath");
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->buffer($decrypted_image);
if (!$mimeType) {
    $mimeType = "application/octet-stream";
}
header("Content-Type: " . $mimeType);
header("Content-Length: " . strlen($decrypted_image));
echo $decrypted_image;
?>
