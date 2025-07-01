<?php
include_once 'main.php';



header('Content-Type: application/json');

// Default response array
$response = [
    'status' => 'no_change',  // Default if not enough time has passed
    'exp_gained' => 0,
    'coins_added' => 0,
    'new_level' => null,
    'exp_to_next' => 0
];

// Check if the user is logged in
if (isset($_SESSION['loggedin']) && isset($_SESSION['id']) && $_SESSION['loggedin'] === true) {
    $user_id = $_SESSION['id'];
    $current_sql_time = date('Y-m-d H:i:s'); // Get current time in SQL DATETIME format

    // ---------------------------
    // Update last_activity timestamp
    // ---------------------------
    $stmt_update_activity = $con->prepare("UPDATE accounts SET last_activity = ? WHERE id = ?");
    if ($stmt_update_activity) {
        $stmt_update_activity->bind_param("si", $current_sql_time, $user_id);
        $stmt_update_activity->execute();
        $stmt_update_activity->close();
    } else {
        error_log("DB Prepare Error (update last_activity): " . $con->error);
    }

    // ---------------------------
    // Check cooldown based on last awarded time stored in session
    // ---------------------------
    $current_php_time = time();
    $last_exp_award_time = $_SESSION['last_chat_activity'] ?? 0;

    // Check if at least 30 seconds have passed
    if ($current_php_time - $last_exp_award_time > 30) {
        // ---- EXP Award Logic ----
        $exp_to_award     = 5; // base EXP
        $bonus_exp        = 0;
        $consecutive_days = $_SESSION['consecutive_chat_days'] ?? 0;
        if ($consecutive_days > 0) {
            // Bonus EXP: 2 exp per consecutive day, but maximum bonus 20
            $bonus_exp = min(20, $consecutive_days * 2);
        }
        $total_exp = $exp_to_award + $bonus_exp;
        $stmt_exp = $con->prepare("UPDATE accounts SET exp = exp + ? WHERE id = ?");
        if ($stmt_exp) {
            $stmt_exp->bind_param("ii", $total_exp, $user_id);
            $stmt_exp->execute();
            $stmt_exp->close();
        } else {
            error_log("DB Prepare Error (update exp): " . $con->error);
        }
        $response['exp_gained'] = $total_exp;

        // ---- Coins Award Logic ----
        $coins_to_award = 1; // base coins per period
        $coins_bonus = ($consecutive_days > 0) ? min(5, $consecutive_days) : 0;
        // Optionally check if the user has sent a message in the last 60 seconds for an active chat bonus.
        $active_chat_bonus = 0;
        if (isset($_SESSION['last_message_time']) && ($current_php_time - $_SESSION['last_message_time'] <= 60)) {
            $active_chat_bonus = 1;
        }
        $total_coins = $coins_to_award + $coins_bonus + $active_chat_bonus;
        $stmt_coins = $con->prepare("UPDATE accounts SET coins = coins + ? WHERE id = ?");
        if ($stmt_coins) {
            $stmt_coins->bind_param("ii", $total_coins, $user_id);
            $stmt_coins->execute();
            $stmt_coins->close();
        } else {
            error_log("DB Prepare Error (update coins): " . $con->error);
        }
        $response['coins_added'] = $total_coins;

        // ---- Update session variables for activity ----
        $_SESSION['last_chat_activity'] = $current_php_time;
        $_SESSION['consecutive_chat_days'] = $consecutive_days + 1;
        $_SESSION['last_chat_day'] = date('Y-m-d');

        // ---- Level-up Check ----
        $stmt_stats = $con->prepare("SELECT exp, level FROM accounts WHERE id = ?");
        if ($stmt_stats) {
            $stmt_stats->bind_param("i", $user_id);
            $stmt_stats->execute();
            $stmt_stats->bind_result($current_exp, $current_level);
            $stmt_stats->fetch();
            $stmt_stats->close();

            if (function_exists('calculateLevel')) {
                $new_level = calculateLevel($current_exp);
                if ($new_level > $current_level) {
                    $stmt_level = $con->prepare("UPDATE accounts SET level = ? WHERE id = ?");
                    if ($stmt_level) {
                        $stmt_level->bind_param("ii", $new_level, $user_id);
                        $stmt_level->execute();
                        $stmt_level->close();
                        $_SESSION['level'] = $new_level;
                        $response['status'] = 'level_up';
                        $response['new_level'] = $new_level;
                    } else {
                        error_log("DB Prepare Error (update level): " . $con->error);
                    }
                } else {
                    $response['status'] = 'exp_gained';
                }
                if (function_exists('expToNextLevel')) {
                    $response['exp_to_next'] = expToNextLevel($current_exp);
                } else {
                    error_log("Function expToNextLevel not found.");
                }
            } else {
                error_log("Function calculateLevel not found.");
                $response['status'] = 'error';
                $response['message'] = 'Level calculation function missing.';
            }
        } else {
            error_log("DB Prepare Error (fetch stats): " . $con->error);
            $response['status'] = 'error';
            $response['message'] = 'Could not retrieve user stats.';
        }
    }
} else {
    $response['status'] = 'not_logged_in';
}

echo json_encode($response);
?>
