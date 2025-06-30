<?php
include 'main.php';
check_loggedin($con);

// Make sure it's a valid POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    // Only allow accept or decline
    if (!in_array($action, ['accept', 'decline'])) {
        header('Location: ../profile.php');
        exit;
    }

    // Fetch the request to ensure current user is the receiver
    $stmt = $con->prepare('SELECT receiver_id FROM friends WHERE id = ?');
    $stmt->bind_param('i', $request_id);
    $stmt->execute();
    $stmt->bind_result($receiver_id);
    $stmt->fetch();
    $stmt->close();

    if ($receiver_id != $_SESSION['id']) {
        // Not authorized to respond
        header('Location: ../profile.php');
        exit;
    }

    // Update status in DB
    $new_status = $action === 'accept' ? 'accepted' : 'declined';
    $stmt = $con->prepare('UPDATE friends SET status = ? WHERE id = ?');
    $stmt->bind_param('si', $new_status, $request_id);
    $stmt->execute();
    $stmt->close();
}

header('Location: ../profile.php');
exit;
