<?php
include 'server/main.php';

if (!isset($_SESSION['loggedin'])) {
    header('Location: index.php');
    exit;
}

if (isset($_GET['receiver_id'])) {
    $receiver_id = (int)$_GET['receiver_id'];

    if ($receiver_id !== $_SESSION['id']) { // Prevent sending requests to yourself
        $message = send_friend_request($con, $_SESSION['id'], $receiver_id);
        header("Location: profile.php?msg=" . urlencode($message));
        exit;
    }
}

header("Location: profile.php");
exit;
?>