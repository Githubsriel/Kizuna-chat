<?php
include_once 'server/main.php';


if (isset($_SESSION['id'])) {
    $stmt = $con->prepare("DELETE FROM chat_avatars WHERE user_id = ?");
    $stmt->bind_param('i', $_SESSION['id']);
    $stmt->execute();
    $stmt->close();
}
?>
