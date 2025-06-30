<?php
include_once 'server/main.php';


if (!isset($_SESSION['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in.']);
    exit;
}

$user_id = $_SESSION['id'];

// Validate upload
if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'No file uploaded or an upload error occurred.']);
    exit;
}

$file = $_FILES['avatar'];
$fileInfo = pathinfo($file['name']);
$extension = strtolower($fileInfo['extension']);

if ($extension !== 'png') {
    echo json_encode(['status' => 'error', 'message' => 'Only PNG files are allowed.']);
    exit;
}

$maxSize = 2 * 1024 * 1024; // 2MB
if ($file['size'] > $maxSize) {
    echo json_encode(['status' => 'error', 'message' => 'File size exceeds the 2MB limit.']);
    exit;
}

// Prepare upload folder
$targetDir = 'server/uploads/avatars/';
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
}

// Load image
$sourceImage = @imagecreatefrompng($file['tmp_name']);
if (!$sourceImage) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid PNG file.']);
    exit;
}

$width = imagesx($sourceImage);
$height = imagesy($sourceImage);
$size = max($width, $height);

// Create square canvas
$avatarImage = imagecreatetruecolor($size, $size);
imagealphablending($avatarImage, false);
imagesavealpha($avatarImage, true);
$transparent = imagecolorallocatealpha($avatarImage, 0, 0, 0, 127);
imagefilledrectangle($avatarImage, 0, 0, $size, $size, $transparent);

// Center PNG
$dstX = ($size - $width) / 2;
$dstY = ($size - $height) / 2;
imagecopyresampled($avatarImage, $sourceImage, $dstX, $dstY, 0, 0, $width, $height, $width, $height);

// Save final file
$filename = uniqid('avatar_') . '.png';
$targetPath = $targetDir . $filename;

if (!imagepng($avatarImage, $targetPath)) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to save avatar.']);
    exit;
}

imagedestroy($sourceImage);
imagedestroy($avatarImage);

// Save avatar entry to DB with origin
$stmt = $con->prepare("INSERT INTO user_avatars (user_id, avatar_image, origin) VALUES (?, ?, 'original')");
$stmt->bind_param("is", $user_id, $targetPath);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Avatar uploaded successfully.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database insertion failed.']);
}
$stmt->close();
?>
