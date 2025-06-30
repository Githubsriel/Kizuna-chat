<?php
include_once 'server/main.php';



header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

if (!isset($_POST['avatar_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No avatar specified']);
    exit;
}

$avatarId = intval($_POST['avatar_id']);
$userId = $_SESSION['id'];
$username = $_SESSION['name'];

// 1) Verify that the avatar belongs to the user
$stmt = $con->prepare("SELECT avatar_image FROM user_avatars WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $avatarId, $userId);
$stmt->execute();
$stmt->bind_result($avatarImage);
if (!$stmt->fetch()) {
    $stmt->close();
    echo json_encode(['status' => 'error', 'message' => 'Avatar not found']);
    exit;
}
$stmt->close();

// 2) Set this avatar as active, and deactivate others
$stmt1 = $con->prepare("UPDATE user_avatars SET is_active = 0 WHERE user_id = ?");
$stmt1->bind_param("i", $userId);
$stmt1->execute();
$stmt1->close();

$stmt2 = $con->prepare("UPDATE user_avatars SET is_active = 1 WHERE id = ? AND user_id = ?");
$stmt2->bind_param("ii", $avatarId, $userId);
$stmt2->execute();
$stmt2->close();

// 3) Keep avatar position in chat
$oldX = 100;
$oldY = 100;
$stmt3 = $con->prepare("SELECT x, y FROM chat_avatars WHERE user_id = ?");
$stmt3->bind_param("i", $userId);
$stmt3->execute();
$stmt3->bind_result($dbX, $dbY);
if ($stmt3->fetch()) {
    $oldX = $dbX;
    $oldY = $dbY;
}
$stmt3->close();

// 4) Upsert into chat_avatars
$stmt4 = $con->prepare("
    INSERT INTO chat_avatars (user_id, username, avatar_image, x, y)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE 
        avatar_image = VALUES(avatar_image),
        username = VALUES(username),
        x = VALUES(x),
        y = VALUES(y)
");
$stmt4->bind_param("issii", $userId, $username, $avatarImage, $oldX, $oldY);
$stmt4->execute();
$stmt4->close();

// 5) Update session
$_SESSION['active_avatar'] = $avatarImage;

echo json_encode([
    'status' => 'success',
    'new_avatar_url' => $avatarImage
]);
?>
