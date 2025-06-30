<?php
include_once 'main.php';


if (!isset($_GET['receiver_id'])) {
    header('Location: ../users.php');
    exit;
}

$sender_id = $_SESSION['id'];
$receiver_id = (int)$_GET['receiver_id'];

// Prevent sending to self
if ($sender_id === $receiver_id) {
    header('Location: ../view_profile.php?id=' . $receiver_id);
    exit;
}

// Check if request already exists (any direction)
$stmt = $con->prepare('
    SELECT id FROM friends 
    WHERE 
        (sender_id = ? AND receiver_id = ?) 
     OR (sender_id = ? AND receiver_id = ?)
');
$stmt->bind_param('iiii', $sender_id, $receiver_id, $receiver_id, $sender_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    // Request already exists
    $stmt->close();
    header('Location: ../view_profile.php?id=' . $receiver_id);
    exit;
}

$stmt->close();

// Insert new friend request
$stmt = $con->prepare('INSERT INTO friends (sender_id, receiver_id, status) VALUES (?, ?, "pending")');
$stmt->bind_param('ii', $sender_id, $receiver_id);
$stmt->execute();
$stmt->close();

header('Location: ../view_profile.php?id=' . $receiver_id);
exit;
?>
