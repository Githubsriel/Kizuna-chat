<?php
session_start();
include 'main.php';
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Nicht eingeloggt.']);
    exit;
}

$user_id = $_SESSION['id'];
$market_id = $_POST['market_id'] ?? null;

if (!$market_id) {
    echo json_encode(['status' => 'error', 'message' => 'Ungültige Anfrage.']);
    exit;
}

$stmt = $con->prepare("DELETE FROM avatar_market WHERE id = ? AND seller_id = ?");
$stmt->bind_param("ii", $market_id, $user_id);

if ($stmt->execute()) {
    $stmt->close();
    header('Location: ../marketplace.php');
    exit;
} else {
    echo json_encode(['status' => 'error', 'message' => 'Fehler beim entfernen.']);
}
$stmt->close();
?>
