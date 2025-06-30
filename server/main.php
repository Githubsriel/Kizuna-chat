<?php
ob_start();  // Start output buffering

// Prevent headers already sent issues by ensuring no output before session_start()
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include the configuration file
include_once 'config.php';

// Connect to the MySQL database using MySQLi
$con = mysqli_connect(db_host, db_user, db_pass, db_name);
if (mysqli_connect_errno()) {
    exit('Failed to connect to MySQL: ' . mysqli_connect_error());
}

// Update the charset
mysqli_set_charset($con, db_charset);

// Function to check if the user is logged in and handle the remember me cookie
function check_loggedin($con, $redirect_file = 'server/login') {
    if (!isset($_SESSION['loggedin'])) {
        if (isset($_COOKIE['rememberme']) && !empty($_COOKIE['rememberme'])) {
            $stmt = $con->prepare('SELECT id, username, role, title, profile_pic, level, exp FROM accounts WHERE rememberme = ?');
            $stmt->bind_param('s', $_COOKIE['rememberme']);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $stmt->bind_result($id, $username, $role, $title, $profile_pic, $level, $exp);
                $stmt->fetch();
                $stmt->close();

                // Ensure session is started and regenerate session ID for security
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                session_regenerate_id(true);

                $_SESSION['loggedin'] = true;
                $_SESSION['name'] = $username;
                $_SESSION['id'] = $id;
                $_SESSION['role'] = $role;
                $_SESSION['title'] = $title;
                $_SESSION['profile_pic'] = !empty($profile_pic) ? 'server/' . $profile_pic : 'server/uploads/default.png';
                $_SESSION['level'] = $level;
                $_SESSION['exp'] = $exp;
            } else {
                header('Location: ' . $redirect_file);
                exit;
            }
        } else {
            header('Location: ' . $redirect_file);
            exit;
        }
    }
}

// EXP System Functions with Exponential Growth

function calculateLevel($exp) {
    $baseExp = 100;
    $growth = 1.7; // Increased growth-Faktor
    $level = floor(log((($growth - 1) * $exp / $baseExp) + 1) / log($growth)) + 1;
    return min($level, 50); // Assuming you set the max level to 50
}

function expToNextLevel($exp) {
    $baseExp = 100;
    $growth = 1.7; // Increased growth-Faktor
    $currentLevel = calculateLevel($exp);
    $nextLevelThreshold = $baseExp * ((pow($growth, $currentLevel) - 1) / ($growth - 1));
    return max(0, round($nextLevelThreshold - $exp));
}

function getLevelProgress($exp) {
    $baseExp = 100;
    $growth = 1.7; // Increased growth-Faktor
    $currentLevel = calculateLevel($exp);
    $currentLevelThreshold = $baseExp * ((pow($growth, $currentLevel - 1) - 1) / ($growth - 1));
    $nextLevelThreshold = $baseExp * ((pow($growth, $currentLevel) - 1) / ($growth - 1));
    $progress = ($exp - $currentLevelThreshold) / ($nextLevelThreshold - $currentLevelThreshold) * 100;

    return [
        'aktuelles_level'         => $currentLevel,
        'fortschritt'             => round($progress, 2),
        'aktuelle_xp'             => number_format(round($exp - $currentLevelThreshold), 0, ',', '.'),
        'noch_benoetigte_xp'      => number_format(round($nextLevelThreshold - $exp), 0, ',', '.')
    ];
}



function awardExp($userId, $amount, $reason = '') {
    global $con;
    
    if (!is_numeric($amount) || $amount <= 0) {
        return false;
    }
    
    try {
        // Get current EXP
        $stmt = $con->prepare('SELECT exp, level FROM accounts WHERE id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->bind_result($currentExp, $currentLevel);
        $stmt->fetch();
        $stmt->close();
        
        // Calculate new values
        $newExp = $currentExp + $amount;
        $newLevel = calculateLevel($newExp);
        
        // Update database
        $stmt = $con->prepare('UPDATE accounts SET exp = ?, level = ? WHERE id = ?');
        $stmt->bind_param('iii', $newExp, $newLevel, $userId);
        $stmt->execute();
        $stmt->close();
        
        // Update session if it's the current user
        if (isset($_SESSION['id']) && $_SESSION['id'] == $userId) {
            $_SESSION['level'] = $newLevel;
            $_SESSION['exp'] = $newExp;
        }
        
        // Log the EXP change
        if (!empty($reason)) {
            $stmt = $con->prepare('INSERT INTO exp_logs (user_id, amount, reason) VALUES (?, ?, ?)');
            $stmt->bind_param('iis', $userId, $amount, $reason);
            $stmt->execute();
            $stmt->close();
        }
        
        return [
            'level_up' => ($newLevel > $currentLevel),
            'new_level' => $newLevel,
            'new_exp' => $newExp
        ];
    } catch (Exception $e) {
        error_log("Error awarding EXP: " . $e->getMessage());
        return false;
    }
}

// Function to update a user's title
function update_user_title($con, $user_id, $new_title) {
    $stmt = $con->prepare('UPDATE accounts SET title = ? WHERE id = ?');
    $stmt->bind_param('si', $new_title, $user_id);
    if ($stmt->execute()) {
        $_SESSION['title'] = $new_title; // Update session title
        return true;
    }
    return false;
}

// Send activation email function
function send_activation_email($email, $code) {
    $subject = 'Account Activation Required';
    $headers = 'From: ' . mail_from . "\r\n" .
               'Reply-To: ' . mail_from . "\r\n" .
               'Return-Path: ' . mail_from . "\r\n" .
               'X-Mailer: PHP/' . phpversion() . "\r\n" .
               'MIME-Version: 1.0' . "\r\n" .
               'Content-Type: text/html; charset=UTF-8' . "\r\n";
    $activate_link = activation_link . '?email=' . $email . '&code=' . $code;
    $email_template = str_replace('%link%', $activate_link, file_get_contents('activation-email-template.html'));
    mail($email, $subject, $email_template, $headers);
}

// Friend request functions
function send_friend_request($con, $sender_id, $receiver_id) {
    $stmt = $con->prepare('SELECT id FROM friends WHERE sender_id = ? AND receiver_id = ?');
    $stmt->bind_param('ii', $sender_id, $receiver_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 0) {
        $stmt = $con->prepare('INSERT INTO friends (sender_id, receiver_id, status) VALUES (?, ?, "pending")');
        $stmt->bind_param('ii', $sender_id, $receiver_id);
        $stmt->execute();
        return "Friend request sent!";
    } else {
        return "Request already exists!";
    }
}

function respond_to_request($con, $request_id, $action) {
    if ($action == "accept") {
        $stmt = $con->prepare('UPDATE friends SET status = "accepted" WHERE id = ?');
    } elseif ($action == "decline") {
        $stmt = $con->prepare('UPDATE friends SET status = "declined" WHERE id = ?');
    }
    $stmt->bind_param('i', $request_id);
    $stmt->execute();
    return "Request updated!";
}

function get_friend_requests($con, $user_id) {
    $stmt = $con->prepare('SELECT f.id, a.username AS sender 
                           FROM friends f 
                           JOIN accounts a ON f.sender_id = a.id 
                           WHERE f.receiver_id = ? AND f.status = "pending"');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function get_friends_list($con, $user_id) {
    $stmt = $con->prepare('SELECT a.username 
                           FROM friends f 
                           JOIN accounts a ON (f.sender_id = a.id OR f.receiver_id = a.id) 
                           WHERE (f.sender_id = ? OR f.receiver_id = ?) AND f.status = "accepted"');
    $stmt->bind_param('ii', $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}
?>