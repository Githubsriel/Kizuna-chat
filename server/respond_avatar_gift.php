<?php
include_once 'main.php';
// No additional session_start() call if main.php already handles it.

header('Content-Type: application/json');

if (!isset($_POST['gift_id'], $_POST['response'], $_SESSION['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Ungültige Anfrageparameter.']);
    exit;
}

$gift_id = (int)$_POST['gift_id'];
$response = $_POST['response'];
$user_id = (int)$_SESSION['id'];

if (!in_array($response, ['accept', 'decline'])) {
    echo json_encode(['status' => 'error', 'message' => 'Ungültiger Antworttyp.']);
    exit;
}

$status = ($response === 'accept') ? 'accepted' : 'declined';

$stmt = $con->prepare("UPDATE avatar_gifts SET status = ?, responded_at = NOW() WHERE id = ? AND receiver_id = ?");
$stmt->bind_param("sii", $status, $gift_id, $user_id);
if (!$stmt->execute()) {
    echo json_encode(['status' => 'error', 'message' => 'Geschenkstatus konnte nicht aktualisiert werden.']);
    exit;
}
$stmt->close();

if ($status === 'accepted') {
    // Get gifted avatar
    $stmtAvatar = $con->prepare("SELECT avatar_image FROM avatar_gifts WHERE id = ?");
    $stmtAvatar->bind_param("i", $gift_id);
    $stmtAvatar->execute();
    $stmtAvatar->bind_result($avatar_image);
    if (!$stmtAvatar->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Geschenk nicht gefunden.']);
        exit;
    }
    $stmtAvatar->close();

    // Check if user already owns the avatar
    $stmtCheck = $con->prepare("SELECT COUNT(*) FROM user_avatars WHERE user_id = ? AND avatar_image = ?");
    $stmtCheck->bind_param("is", $user_id, $avatar_image);
    $stmtCheck->execute();
    $stmtCheck->bind_result($already_owned);
    $stmtCheck->fetch();
    $stmtCheck->close();

    if ($already_owned > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Du besitzt diesen Avatar bereits.']);
        exit;
    }

    // Copy avatar with origin = 'gifted'
    $stmtCopy = $con->prepare("INSERT INTO user_avatars (user_id, avatar_image, origin) VALUES (?, ?, 'gifted')");
    $stmtCopy->bind_param("is", $user_id, $avatar_image);
    if (!$stmtCopy->execute()) {
        echo json_encode(['status' => 'error', 'message' => 'Avatar konnte nicht übertragen werden.']);
        exit;
    }
    $stmtCopy->close();
}

echo json_encode(['status' => 'success', 'message' => 'Deine Antwort wurde gespeichert.']);
?>
