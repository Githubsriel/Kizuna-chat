<?php
include_once 'main.php';


mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    if (!isset($_SESSION['id'])) {
        throw new Exception('Nicht eingeloggt.');
    }

    $buyer_id = $_SESSION['id'];
    $market_id = intval($_POST['market_id'] ?? 0);
    if ($market_id <= 0) throw new Exception('Ungültige Anfrage.');

    // Fetch listing info
    $stmt = $con->prepare("SELECT seller_id, avatar_image, price FROM avatar_market WHERE id = ? AND expires_at > NOW()");
    $stmt->bind_param("i", $market_id);
    $stmt->execute();
    $stmt->bind_result($seller_id, $avatar_image, $price);
    if (!$stmt->fetch()) {
        throw new Exception('Avatar ist nicht verfügbar.');
    }
    $stmt->close();

    if ($seller_id == $buyer_id) {
        throw new Exception('Du kannst deinen eigenen Avatar nicht kaufen.');
    }

    // Prevent duplicate ownership
    $stmt = $con->prepare("SELECT COUNT(*) FROM user_avatars WHERE user_id = ? AND avatar_image = ?");
    $stmt->bind_param("is", $buyer_id, $avatar_image);
    $stmt->execute();
    $stmt->bind_result($has_avatar);
    $stmt->fetch();
    $stmt->close();

    if ($has_avatar > 0) {
        throw new Exception('Du besitzt diesen Avatar bereits.');
    }

    // Get buyer's current coins
    $stmt = $con->prepare("SELECT coins FROM accounts WHERE id = ?");
    $stmt->bind_param("i", $buyer_id);
    $stmt->execute();
    $stmt->bind_result($buyer_coins);
    $stmt->fetch();
    $stmt->close();

    if ($buyer_coins < $price) {
        throw new Exception('Nicht genügend Münzen.');
    }

    // Tax settings
    $tax_percent = 25;
    $tax = floor($price * ($tax_percent / 100));
    $seller_earnings = $price - $tax;

    // Begin transaction
    $con->begin_transaction();

    // Deduct from buyer
    $stmt = $con->prepare("UPDATE accounts SET coins = coins - ? WHERE id = ?");
    $stmt->bind_param("ii", $price, $buyer_id);
    $stmt->execute();

    // Credit seller
    $stmt = $con->prepare("UPDATE accounts SET coins = coins + ? WHERE id = ?");
    $stmt->bind_param("ii", $seller_earnings, $seller_id);
    $stmt->execute();

    // Credit system account (id 0) with tax
    $stmt = $con->prepare("UPDATE accounts SET coins = coins + ? WHERE id = 0");
    $stmt->bind_param("i", $tax);
    $stmt->execute();

    // Add avatar to buyer's collection, origin = 'bought'
    $stmt = $con->prepare("INSERT INTO user_avatars (user_id, avatar_image, origin) VALUES (?, ?, 'bought')");
    $stmt->bind_param("is", $buyer_id, $avatar_image);
    $stmt->execute();

    $con->commit();

    header("Location: ../marketplace.php");
    exit;

} catch (Exception $e) {
    if ($con->errno) $con->rollback();
    $_SESSION['market_error'] = $e->getMessage();
    header("Location: ../marketplace.php");
    exit;
}
?>
