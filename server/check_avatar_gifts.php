<?php
include_once 'main.php';


$user_id = $_SESSION['id'];

$stmt = $con->prepare("SELECT g.id, g.avatar_image, a.username FROM avatar_gifts g JOIN accounts a ON g.sender_id = a.id WHERE g.receiver_id = ? AND g.status = 'pending'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$gifts = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode($gifts);
$stmt->close();
?>
