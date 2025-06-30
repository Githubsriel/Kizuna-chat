<?php
session_start();
include 'main.php';

// Enable mysqli error reporting as exceptions
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    if (!isset($_SESSION['id'])) {
        throw new Exception('Nicht eingeloggt.');
    }

    $user_id = $_SESSION['id'];
    $avatar_image = $_POST['avatar_image'] ?? '';
    $price = intval($_POST['price'] ?? 0);
    $duration_hours = intval($_POST['duration'] ?? 0);

    // Input validation
    if (empty($avatar_image) || $price <= 0 || $duration_hours <= 0 || $duration_hours > 168) {
        throw new Exception('Ungültige Eingabe.');
    }

    // Only allow avatars with origin = 'original'
    $stmt = $con->prepare("SELECT origin FROM user_avatars WHERE user_id = ? AND avatar_image = ?");
    $stmt->bind_param("is", $user_id, $avatar_image);
    $stmt->execute();
    $stmt->bind_result($origin);
    if (!$stmt->fetch()) {
        throw new Exception('Du besitzt diesen Avatar nicht.');
    }
    $stmt->close();

    if ($origin !== 'original') {
        throw new Exception('Nur originale Avatare können verkauft werden.');
    }

    // Check if already listed
    $stmt = $con->prepare("SELECT COUNT(*) FROM avatar_market WHERE seller_id = ? AND avatar_image = ?");
    $stmt->bind_param("is", $user_id, $avatar_image);
    $stmt->execute();
    $stmt->bind_result($already_listed);
    $stmt->fetch();
    $stmt->close();

    if ($already_listed > 0) {
        $_SESSION['market_error'] = 'Dieser Avatar wird bereits im Marktplatz angeboten.';
        header('Location: ../marketplace.php');
        exit;
    }

    // Insert into market
    $expires_at = date('Y-m-d H:i:s', time() + ($duration_hours * 3600));
    $stmt = $con->prepare("INSERT INTO avatar_market (seller_id, avatar_image, price, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isis", $user_id, $avatar_image, $price, $expires_at);
    $stmt->execute();
    $stmt->close();

    header("Location: ../marketplace.php");
    exit;

} catch (Exception $e) {
    $_SESSION['market_error'] = 'Fehler: ' . $e->getMessage();
    header('Location: ../marketplace.php');
    exit;
}
?>
