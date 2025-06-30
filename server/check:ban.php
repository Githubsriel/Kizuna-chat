<?php
include_once 'main.php';


if (!isset($_SESSION['id'])) {
    echo json_encode(['banned' => 0]);
    exit;
}

$stmt = $con->prepare("SELECT banned FROM accounts WHERE id = ?");
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$stmt->bind_result($banned);
$stmt->fetch();
$stmt->close();

echo json_encode(['banned' => $banned]);
