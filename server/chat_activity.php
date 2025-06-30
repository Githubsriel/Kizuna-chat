<?php
include_once 'main.php';



header('Content-Type: application/json');

// Default response
$response = [
    'status' => 'no_change', // Default status if no EXP awarded
    'exp_gained' => 0,
    'new_level' => null,
    'exp_to_next' => 0
];

// --- Check if user is logged in and has a valid ID ---
if (isset($_SESSION['loggedin']) && isset($_SESSION['id']) && $_SESSION['loggedin'] === true) {
    $user_id = $_SESSION['id']; // Store user ID for convenience

    // --- BEGIN: Update last_activity timestamp in the database ---
    // This happens on *every* call to this script for a logged-in user,
    // keeping their 'online' status fresh.
    $current_sql_time = date('Y-m-d H:i:s'); // Get current time in SQL DATETIME format

    $stmt_update_activity = $con->prepare("UPDATE accounts SET last_activity = ? WHERE id = ?");
    if ($stmt_update_activity) {
        $stmt_update_activity->bind_param("si", $current_sql_time, $user_id);
        $stmt_update_activity->execute();
        $stmt_update_activity->close();
        // Timestamp updated successfully.
    } else {
        // Log error if statement preparation failed
        error_log("DB Prepare Error (update last_activity): " . $con->error);
        // You might want to set an error status in the response, though it's
        // a background task, so maybe just logging is sufficient.
        // $response['status'] = 'error';
        // $response['message'] = 'Failed to update activity timestamp.';
    }
    // --- END: Update last_activity timestamp ---


    // --- Existing EXP Award Logic (Based on session cooldown) ---
    $current_php_time = time(); // Unix timestamp for PHP time comparisons
    $last_exp_award_time = $_SESSION['last_chat_activity'] ?? 0; // Get last EXP award time from session

    // Check if enough time (e.g., 30 seconds) has passed since the *last EXP award*
    if ($current_php_time - $last_exp_award_time > 30) {
        $exp_to_award = 5; // Base EXP
        $response['exp_gained'] = $exp_to_award;

        // Update user EXP in the database
        // We already updated last_activity above.
        $stmt_exp = $con->prepare("UPDATE accounts SET exp = exp + ? WHERE id = ?");
        if ($stmt_exp) {
            $stmt_exp->bind_param("ii", $exp_to_award, $user_id);
            $stmt_exp->execute();
            $stmt_exp->close();
        } else {
            error_log("DB Prepare Error (update exp): " . $con->error);
        }

        // Update the session variable tracking the *last EXP award time*
        $_SESSION['last_chat_activity'] = $current_php_time;

        // --- Check for Level Up ---
        $stmt_stats = $con->prepare("SELECT exp, level FROM accounts WHERE id = ?");
        if ($stmt_stats) {
            $stmt_stats->bind_param("i", $user_id);
            $stmt_stats->execute();
            $stmt_stats->bind_result($current_exp, $current_level);
            $stmt_stats->fetch();
            $stmt_stats->close();

            // Ensure calculateLevel function is available (should be included via main.php)
            if (function_exists('calculateLevel')) {
                $new_level = calculateLevel($current_exp);

                if ($new_level > $current_level) {
                    // Update level in DB
                    $stmt_level = $con->prepare("UPDATE accounts SET level = ? WHERE id = ?");
                    if ($stmt_level) {
                        $stmt_level->bind_param("ii", $new_level, $user_id);
                        $stmt_level->execute();
                        $stmt_level->close();

                        $_SESSION['level'] = $new_level; // Update level in session

                        $response['status'] = 'level_up';
                        $response['new_level'] = $new_level;
                    } else {
                        error_log("DB Prepare Error (update level): " . $con->error);
                    }
                } else {
                    // EXP was gained, but no level up occurred
                    $response['status'] = 'exp_gained';
                }

                // Calculate EXP to next level (Ensure expToNextLevel function is available)
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
        // --- End Check for Level Up ---

    } else {
        // Cooldown active: Not enough time passed for EXP gain.
        // status remains 'no_change', but DB last_activity was updated regardless.
    }
    // --- End Existing EXP Award Logic ---

} else {
    // User is not logged in or session ID is missing
    $response['status'] = 'not_logged_in';
}

// Send the JSON response back to the JavaScript caller
echo json_encode($response);
?>
