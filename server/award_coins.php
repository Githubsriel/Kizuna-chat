<?php
include_once 'main.php';


if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$current_time  = time();
$last_activity = $_SESSION['last_chat_activity'] ?? 0;

// Check if enough time has passed to award coins (e.g., every 30 seconds)
if ($current_time - $last_activity > 30) {
    // Award EXP (existing logic)
    $exp_to_award     = 5;
    $bonus_exp        = 0;
    $consecutive_days = $_SESSION['consecutive_chat_days'] ?? 0;
    if ($consecutive_days > 0) {
        $bonus_exp = min(20, $consecutive_days * 2);
    }
    $total_exp = $exp_to_award + $bonus_exp;
    $stmt = $con->prepare("UPDATE accounts SET exp = exp + ? WHERE id = ?");
    $stmt->bind_param("ii", $total_exp, $_SESSION['id']);
    $stmt->execute();
    $stmt->close();

    // Award Coins:
    $coins_to_award = 1;
    $coins_bonus = ($consecutive_days > 0) ? min(5, $consecutive_days) : 0;
    // Optionally, you could also check if the user has sent a message in the last 60 seconds
    $active_chat_bonus = 0;
    if (isset($_SESSION['last_message_time']) && ($current_time - $_SESSION['last_message_time'] <= 60)) {
        $active_chat_bonus = 1;
    }
    $total_coins = $coins_to_award + $coins_bonus + $active_chat_bonus;
    $stmt = $con->prepare("UPDATE accounts SET coins = coins + ? WHERE id = ?");
    $stmt->bind_param("ii", $total_coins, $_SESSION['id']);
    $stmt->execute();
    $stmt->close();

    // Update session variables for activity
    $_SESSION['last_chat_activity']    = $current_time;
    $_SESSION['consecutive_chat_days'] = $consecutive_days + 1;
    $_SESSION['last_chat_day']         = date('Y-m-d');

    // Optionally do level-up checks here as well...

    echo json_encode(['status' => 'success', 'coins_added' => $total_coins]);
} else {
    echo json_encode(['status' => 'noop']);
}
?>
