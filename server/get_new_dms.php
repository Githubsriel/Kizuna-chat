<?php
include_once 'main.php';
// No additional session_start() call if main.php already handles it.

if (!isset($_SESSION['id'])) {
    echo json_encode([]);
    exit;
}

$currentUser = $_SESSION['id'];

// Query: return distinct partner IDs that have sent messages to the current user in the last minute.
// In a production system, you'd use a "read" flag to prevent reopening already seen conversations.
$query = "SELECT DISTINCT pm.sender_id, a.username 
          FROM private_messages pm 
          JOIN accounts a ON pm.sender_id = a.id 
          WHERE pm.receiver_id = ? 
            AND pm.created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)";
$stmt = $con->prepare($query);
$stmt->bind_param("i", $currentUser);
$stmt->execute();
$result = $stmt->get_result();
$newDMs = [];
while ($row = $result->fetch_assoc()) {
    $newDMs[] = [
        "partner_id" => $row['sender_id'],
        "partner_username" => $row['username']
    ];
}
$stmt->close();
echo json_encode($newDMs);
?>
