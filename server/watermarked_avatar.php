<?php
if (!isset($_GET['img'])) {
    http_response_code(400);
    exit('Missing image parameter.');
}

$filename = basename($_GET['img']);
$avatarPath = __DIR__ . '/uploads/avatars/' . $filename;
$logoPath = __DIR__ . '/img/logo_watermark.png';

if (!file_exists($avatarPath) || !file_exists($logoPath)) {
    http_response_code(404);
    exit('Image or watermark not found.');
}

// Load avatar image
$avatar = imagecreatefrompng($avatarPath);
if (!$avatar) {
    http_response_code(500);
    exit('Could not load avatar image.');
}

// Load watermark logo
$originalWatermark = imagecreatefrompng($logoPath);
if (!$originalWatermark) {
    http_response_code(500);
    exit('Could not load watermark image.');
}

// Get avatar dimensions
$avatarWidth = imagesx($avatar);
$avatarHeight = imagesy($avatar);

// Resize watermark to match avatar width
$wmOriginalWidth = imagesx($originalWatermark);
$wmOriginalHeight = imagesy($originalWatermark);
$targetWidth = $avatarWidth;
$aspectRatio = $wmOriginalHeight / $wmOriginalWidth;
$targetHeight = intval($targetWidth * $aspectRatio);

// Create resized watermark
$resizedWatermark = imagecreatetruecolor($targetWidth, $targetHeight);
imagealphablending($resizedWatermark, false);
imagesavealpha($resizedWatermark, true);
$transparent = imagecolorallocatealpha($resizedWatermark, 0, 0, 0, 127);
imagefill($resizedWatermark, 0, 0, $transparent);
imagecopyresampled($resizedWatermark, $originalWatermark, 0, 0, 0, 0, $targetWidth, $targetHeight, $wmOriginalWidth, $wmOriginalHeight);

// Center watermark vertically
$destX = 0;
$destY = intval(($avatarHeight - $targetHeight) / 2);

// Merge with transparency
imagealphablending($avatar, true);
imagesavealpha($avatar, true);
imagecopy($avatar, $resizedWatermark, $destX, $destY, 0, 0, $targetWidth, $targetHeight);

// Output final image
header('Content-Type: image/png');
imagepng($avatar);

// Clean up
imagedestroy($avatar);
imagedestroy($originalWatermark);
imagedestroy($resizedWatermark);
