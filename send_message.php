<?php
include_once 'server/main.php';



// Check if the user is logged in
if (!isset($_SESSION['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

if (isset($_POST['message'])) {
    $message = trim($_POST['message']);
    if (!empty($message)) {

        // Retrieve the room id from POST data (default to 1 if not provided)
        $room = isset($_POST['room']) ? intval($_POST['room']) : 1;

        $userId   = $_SESSION['id'];
        $username = $_SESSION['name'];

        // Check AFK status
        $stmt = $con->prepare("SELECT afk FROM accounts WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->bind_result($currentAfk);
        $stmt->fetch();
        $stmt->close();

        // Insert user's chat message with room id
        $stmt = $con->prepare("INSERT INTO chat_messages (sender_id, message, created_at, type, room_id) VALUES (?, ?, NOW(), 'text', ?)");
        $stmt->bind_param('isi', $userId, $message, $room);
        $stmt->execute();
        $stmt->close();

        // After processing a sent message, update the last message timestamp:
        $_SESSION['last_message_time'] = time();

        // If user was AFK, mark them as active and insert a system message into the same room
        if ($currentAfk == 1) {
            $stmt = $con->prepare("UPDATE accounts SET afk = 0 WHERE id = ?");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();

            // Insert system message; you can change the system user id if necessary
            $systemUserId = 0;
            $messageText  = $username . " ist wieder da";
            $stmt = $con->prepare("INSERT INTO chat_messages (sender_id, message, created_at, type, room_id) VALUES (?, ?, NOW(), 'system', ?)");
            $stmt->bind_param("isi", $systemUserId, $messageText, $room);
            $stmt->execute();
            $stmt->close();
        }

        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Empty message']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'No message provided']);
}
?>
