<?php
include_once 'main.php';

$user_id = $_SESSION['id'];
$afk_message = isset($_POST['afk_message']) ? trim($_POST['afk_message']) : '';

// Get current AFK state
$stmt = $con->prepare("SELECT afk FROM accounts WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($current_afk);
$stmt->fetch();
$stmt->close();

// Flip AFK state
$new_afk = ($current_afk == 1) ? 0 : 1;

// Prepare system message
if ($new_afk == 1) {
    $msgSuffix = $afk_message !== '' ? ": $afk_message" : '';
    $messageText = $_SESSION['name'] . " ist AFK" . $msgSuffix;
} else {
    $afk_message = ''; // Clear message on return
    $messageText = $_SESSION['name'] . " ist wieder da";
}

// Update database (include message when going AFK, or clear when returning)
$stmt = $con->prepare("UPDATE accounts SET afk = ?, afk_message = ? WHERE id = ?");
$stmt->bind_param("isi", $new_afk, $afk_message, $user_id);
$stmt->execute();
$stmt->close();

// Insert system message
$stmt = $con->prepare("
    INSERT INTO chat_messages (sender_id, message, created_at, type)
    VALUES (0, ?, NOW(), 'system')
");
$stmt->bind_param("s", $messageText);
$stmt->execute();
$stmt->close();

// Respond with JSON
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'afk'    => $new_afk,
    'afk_message' => $afk_message
]);
