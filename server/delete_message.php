<?php
// delete_message.php
include 'main.php';
check_loggedin($con);

// Only allow deletion for admins
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: chat.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message_id'])) {
    $message_id = (int)$_POST['message_id'];
    $stmt = $con->prepare("DELETE FROM chat_messages WHERE id = ?");
    $stmt->bind_param('i', $message_id);
    $stmt->execute();
    $stmt->close();
}

header("Location: chat.php");
exit;
?>
