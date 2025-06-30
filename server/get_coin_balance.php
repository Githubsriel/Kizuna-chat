<?php
include_once 'main.php';
// No additional session_start() call if main.php already handles it.

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$stmt = $con->prepare("SELECT coins FROM accounts WHERE id = ?");
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$stmt->bind_result($coins);
$stmt->fetch();
$stmt->close();

echo json_encode(['coins' => $coins]);

?>
