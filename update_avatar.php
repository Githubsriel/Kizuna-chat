<?php
include_once 'server/main.php';



if (!isset($_SESSION['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

if (isset($_POST['x']) && isset($_POST['y'])) {
    $x = intval($_POST['x']);
    $y = intval($_POST['y']);
    
    $userId = $_SESSION['id'];
    $username = $_SESSION['name'];

    // Always fetch the currently active avatar from the DB
    $stmt = $con->prepare("
        SELECT avatar_image 
        FROM user_avatars 
        WHERE user_id = ? AND is_active = 1 
        LIMIT 1
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($avatar_image);
    $stmt->fetch();
    $stmt->close();

    if (empty($avatar_image)) {
        $avatar_image = "server/uploads/avatars/default_avatar.png";
    }

    // Check if user is currently AFK
    $stmt = $con->prepare("SELECT afk FROM accounts WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($currentAfk);
    $stmt->fetch();
    $stmt->close();

    // Update chat_avatars
    $stmt = $con->prepare("
        INSERT INTO chat_avatars (user_id, username, avatar_image, x, y) 
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            avatar_image = VALUES(avatar_image), 
            x = VALUES(x), 
            y = VALUES(y)
    ");
    $stmt->bind_param("issii", $userId, $username, $avatar_image, $x, $y);
    $stmt->execute();
    if ($stmt->error) {
        echo json_encode(['status' => 'error', 'message' => $stmt->error]);
        exit;
    }
    $stmt->close();

    // If the user was AFK, mark them as back and send a system message
    if ($currentAfk == 1) {
        $stmt = $con->prepare("UPDATE accounts SET afk = 0 WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();

        $systemUserId = 0; // or 9999 if you have a system user
        $messageText = $username . " ist wieder da";
        $stmt = $con->prepare("
            INSERT INTO chat_messages (sender_id, message, created_at, type) 
            VALUES (?, ?, NOW(), 'system')
        ");
        $stmt->bind_param("is", $systemUserId, $messageText);
        $stmt->execute();
        $stmt->close();
    }

    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
}
?>
