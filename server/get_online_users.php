<?php
include_once 'main.php';
// No additional session_start() call if main.php already handles it.

header('Content-Type: application/json');

$online_users = [];

if (isset($_SESSION['loggedin']) && isset($_SESSION['id']) && $_SESSION['loggedin'] === true) {
    $current_user_id = intval($_SESSION['id']);
    $online_threshold_time = date('Y-m-d H:i:s', strtotime("-5 minutes"));

    $stmt = $con->prepare("
        SELECT a.id, a.username, a.profile_pic, ca.room_id, r.name AS room_name
        FROM accounts a
        JOIN chat_avatars ca ON ca.user_id = a.id
        LEFT JOIN rooms r ON ca.room_id = r.id
        WHERE a.id != ?
          AND a.last_activity >= ?
          AND a.banned = 0
        ORDER BY r.name ASC, a.username ASC
    ");

    if ($stmt) {
        $stmt->bind_param("is", $current_user_id, $online_threshold_time);
        $stmt->execute();
        $result = $stmt->get_result();

while ($user = $result->fetch_assoc()) {
    // Set the default profile picture URL as seen from chat.php
    $profilePicPath = 'server/uploads/default.png';
    
    if (!empty($user['profile_pic'])) {
        // Build the URL that chat.php should use:
        $potentialUrl = 'server/uploads/' . basename($user['profile_pic']);
        
        // Build the filesystem path (get_online_users.php is in the server folder)
        $fullFilePath = __DIR__ . '/uploads/' . basename($user['profile_pic']);
        
        // Check if the file exists on disk
        if (file_exists($fullFilePath)) {
            $profilePicPath = $potentialUrl;
        }
    }
    
    $online_users[] = [
        'id'              => $user['id'],
        'username'        => $user['username'],
        'profile_pic_url' => $profilePicPath,
        'room_id'         => $user['room_id'] ?? null,
        'room_name'       => $user['room_name'] ?? 'Unassigned'
    ];
}



        $stmt->close();
    } else {
        error_log("DB Prepare Error: " . $con->error);
    }
}

echo json_encode($online_users);
exit;
?>
