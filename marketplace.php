<?php
session_start();
include 'server/main.php';
check_loggedin($con);

$errorMessage = '';
if (isset($_SESSION['market_error'])) {
    $errorMessage = $_SESSION['market_error'];
    unset($_SESSION['market_error']);
}

// Fetch user coins
$stmt = $con->prepare("SELECT coins FROM accounts WHERE id = ?");
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$stmt->bind_result($coins);
$stmt->fetch();
$stmt->close();

// Fetch marketplace avatars
$stmt = $con->prepare("
    SELECT m.id, m.avatar_image, m.price, m.expires_at, a.username
    FROM avatar_market m
    JOIN accounts a ON m.seller_id = a.id
    WHERE m.expires_at > NOW()
    ORDER BY m.listed_at DESC
");
$stmt->execute();
$result = $stmt->get_result();
$market_avatars = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch user's own avatars
$stmt = $con->prepare("SELECT id, avatar_image FROM user_avatars WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$res = $stmt->get_result();
$user_avatars = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="de">
<head>
<!-- Google Tag Manager -->

<!-- End Google Tag Manager -->

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Kizuna Markplatz</title>
    <meta name="description" content="Verbinde dich mit anderen Anime-Enthusiasten im Kizuna Chat, einem unterhaltsamen und ansprechenden grafischen Chat-Erlebnis. Teile deine Leidenschaft für Anime, Manga und mehr!">
    <meta name="keywords" content="Anime Chat, Grafischer Chat, Anime Community, Manga, Anime Fans, Online Chat, Sozial, Kizuna Chat">
    <meta name="author" content="Kizuna Chat">

    <meta property="og:type" content="website">
    <meta property="og:url" content="https://kizuna-chat.de/">
    <meta property="og:title" content="Kizuna Chat - Grafischer Anime Chat für Anime-Fans">
    <meta property="og:description" content="Verbinde dich mit anderen Anime-Enthusiasten im Kizuna Chat, einem unterhaltsamen und ansprechenden grafischen Chat-Erlebnis. Teile deine Leidenschaft für Anime, Manga und mehr!">
    <meta property="og:image" content="https://kizuna-chat.de/server/img/kizuna_chat.png">

    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://kizuna-chat.de/">
    <meta property="twitter:title" content="Kizuna Chat - Grafischer Anime Chat für Anime-Fans">
    <meta property="twitter:description" content="Verbinde dich mit anderen Anime-Enthusiasten im Kizuna Chat, einem unterhaltsamen und ansprechenden grafischen Chat-Erlebnis. Teile deine Leidenschaft für Anime, Manga und mehr!">
    <meta property="twitter:image" content="https://kizuna-chat.de/server/img/kizuna_chat.png">

    <meta name="robots" content="index, follow">

    <link rel="icon" href="/favicon.ico" type="image/x-icon">

    <title>Kizuna Marktplatz</title>
    <link rel="apple-touch-icon" href="server/img/apple_favicon.png">
    <link rel="stylesheet" href="style.css"> <?php // Ensure path is correct ?>
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.1/css/all.css">
  <style>
    .marketplace-container {
      max-width: 800px;
      margin: 40px auto;
      padding: 20px;
      background: var(--card-background);
      border-radius: 10px;
      box-shadow: var(--popup-shadow);
    }
    .market-avatar {
      display: inline-block;
      text-align: center;
      margin: 10px;
      padding: 10px;
      border: 1px solid var(--border-color);
      border-radius: 10px;
      background: var(--light-bg);
    }
    .market-avatar img {
      width: 100px;
      height: 100px;
      border-radius: 8px;
    }
    .price, .seller, .expires {
      margin: 5px 0;
    }
    .offer-form {
      margin-top: 30px;
      padding-top: 20px;
      border-top: 1px solid var(--border-color);
    }
    #avatarPreview img {
      margin-top: 10px;
      max-width: 100px;
      max-height: 100px;
      border-radius: 8px;
    }
    .notification.error {
      background: #f44336;
      color: #fff;
      padding: 10px 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      width: fit-content;
    }
    .notification.show {
      opacity: 1;
    }
    .avatar-option.selected {
      border: 2px solid var(--accent-color);
      box-shadow: 0 0 6px var(--accent-color);
    }
  </style>
</head>
<body>
<!-- Google Tag Manager (noscript) -->

<!-- End Google Tag Manager (noscript) -->


<div id="alpha-warning-popup" class="alpha-popup hidden">
    <div class="alpha-popup-content">
        <p>
            Willkommen! Bitte beachte, dass sich diese Webseite derzeit in einer frühen
            <strong>Alpha-Entwicklungsphase</strong> befindet.
        </p>
        <p>
            Viele Funktionen sind möglicherweise unvollständig, enthalten Fehler oder funktionieren nicht wie erwartet.
            Wir bitten um dein Verständnis und dein Feedback, während wir die Seite weiterentwickeln!
        </p>
        <button id="accept-alpha-warning">Verstanden, Fortfahren</button>
    </div>
</div>

<div id="topbar">
  <div class="wrapper" style="display: flex; align-items: center; justify-content: space-between">
    <div class="coinblock">
      </div>
  </div>
</div>

<div class="navigation">
  <div class="wrapper">
    <div class="flex_between">
      <a class="chatbutton chatArrow" href="chat" target="_blank">Zum Chat</a>
    </div>
  </div>
</div>

<nav class="navtop">
  <div>
    <h1><img src="server/img/chinochat.png" alt="Chinochat Icon" class="nav-icon">Kizuna Chat</h1>
    <a href="index"><i class="fas fa-home"></i>Home</a>
    <a href="profile"><i class="fas fa-user-circle"></i>Profil (Level <?= $user_level ?>)</a>
    <a href="marketplace"><i class="fas fa-store"></i>Marktplatz</a>

    <?php // Link for Administrators ?>
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'Admin'): ?>
      <a href="server/admin/index" target="_blank"><i class="fas fa-user-cog"></i>Admin</a>
    <?php endif; ?>

    <?php // Link for Moderators ?>
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'Moderator'): ?>
      <?php $moderator_link = "server/moderator/index"; ?>
      <a href="<?= $moderator_link ?>" target="_blank"><i class="fas fa-user-shield"></i>Moderator</a>
    <?php endif; ?>

    <a href="server/logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
    <button id="darkModeToggle" class="dark-mode-btn" type="button">Dark Mode</button>
  </div>
</nav>

<div class="marketplace-container">
  <p><strong>Deine Münzen:</strong> <?= htmlspecialchars($coins) ?> 🪙</p>

  <?php if (!empty($errorMessage)): ?>
    <div class="notification error show"><?= htmlspecialchars($errorMessage) ?></div>
  <?php endif; ?>

  <h2>🛒 Avatar Marktplatz</h2>

  <div id="marketAvatars">
    <?php if (count($market_avatars) === 0): ?>
      <p>Zurzeit sind keine Avatare zum Verkauf gelistet.</p>
    <?php else: ?>
      <?php foreach ($market_avatars as $avatar): ?>
        <div class="market-avatar">
          <img src="server/watermarked_avatar.php?img=<?= urlencode(basename($avatar['avatar_image'])) ?>" alt="Avatar">

          <div class="price"><?= $avatar['price'] ?> Münzen</div>
          <div class="seller">Verkäufer: <?= htmlspecialchars($avatar['username']) ?></div>
          <?php
            $remaining = strtotime($avatar['expires_at']) - time();
            $days = floor($remaining / 86400);
            $hours = floor(($remaining % 86400) / 3600);
            $minutes = floor(($remaining % 3600) / 60);
            $timeString = "{$days} Tage, {$hours} Std, {$minutes} Min verbleibend";
          ?>
          <div class="expires"><?= $timeString ?></div>

          <?php if ($avatar['username'] === $_SESSION['name']): ?>
            <form method="POST" action="server/remove_market_avatar.php">
              <input type="hidden" name="market_id" value="<?= $avatar['id'] ?>">
              <button type="submit">Entfernen</button>
            </form>
          <?php else: ?>
            <button onclick="confirmPurchase(<?= $avatar['id'] ?>, <?= $avatar['price'] ?>)">🛒 Kaufen</button>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="offer-form">
    <h3>🪙 Avatar zum Verkauf anbieten</h3>
    <form action="server/list_avatar.php" method="POST">
      <input type="hidden" name="avatar_image" id="selectedAvatarInput" required>

      <div id="avatarGrid" class="avatar-grid">
        <?php foreach ($user_avatars as $ua): ?>
          <img src="<?= htmlspecialchars($ua['avatar_image']) ?>" alt="Avatar Option" class="avatar-option" data-avatar="<?= htmlspecialchars($ua['avatar_image']) ?>">
        <?php endforeach; ?>
      </div>

      <label for="price">Preis (Münzen):</label>
      <input type="number" name="price" min="1" required>

      <label for="duration">Laufzeit (Stunden):</label>
      <input type="number" name="duration" min="1" max="168" value="24">

      <button type="submit">Avatar anbieten</button>
    </form>
  </div>
</div>

<!-- Confirmation Modal -->
<div id="confirmationModal" class="modal">
  <div class="modal-content">
    <span class="close-button" onclick="closeModal('confirmationModal')">&times;</span>
    <p id="confirmationMessage">Möchtest du diesen Avatar wirklich kaufen?</p>
    <div class="modal-actions">
      <button id="confirmYes" class="btn">Ja</button>
      <button onclick="closeModal('confirmationModal')" class="btn">Nein</button>
    </div>
  </div>
</div>

<script>
// Modal logic
function openModal(modalId) {
  document.getElementById(modalId).style.display = "block";
}
function closeModal(modalId) {
  document.getElementById(modalId).style.display = "none";
}
function confirmPurchase(marketId, price) {
  const confirmationMessage = document.getElementById('confirmationMessage');
  const confirmYes = document.getElementById('confirmYes');

  confirmationMessage.textContent = `Möchtest du diesen Avatar wirklich für ${price} Münzen kaufen?`;
  openModal('confirmationModal');

  const newConfirmYes = confirmYes.cloneNode(true);
  confirmYes.parentNode.replaceChild(newConfirmYes, confirmYes);

  newConfirmYes.addEventListener('click', function () {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'server/buy_avatar.php';

    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'market_id';
    input.value = marketId;
    form.appendChild(input);

    document.body.appendChild(form);
    form.submit();
  });
}

// General UI Interactions
document.addEventListener('DOMContentLoaded', () => {
  const grid = document.getElementById('avatarGrid');
  const hiddenInput = document.getElementById('selectedAvatarInput');
  const darkModeBtn = document.getElementById('darkModeToggle');
  const alphaPopup = document.getElementById('alpha-warning-popup');
  const acceptAlphaButton = document.getElementById('accept-alpha-warning');

  // Avatar selection logic
  if (grid && hiddenInput) {
    grid.querySelectorAll('.avatar-option').forEach(img => {
      img.addEventListener('click', () => {
        grid.querySelectorAll('.avatar-option').forEach(el => el.classList.remove('selected'));
        img.classList.add('selected');
        hiddenInput.value = img.dataset.avatar;
      });
    });

    const firstAvatar = grid.querySelector('.avatar-option');
    if (firstAvatar) firstAvatar.click();
  }

  // Dark mode toggle
  if (darkModeBtn) {
    darkModeBtn.addEventListener('click', () => {
      document.body.classList.toggle('dark-mode');
      if (document.body.classList.contains('dark-mode')) {
        localStorage.setItem('darkMode', 'enabled');
        darkModeBtn.textContent = 'Light Mode';
      } else {
        localStorage.setItem('darkMode', 'disabled');
        darkModeBtn.textContent = 'Dark Mode';
      }
    });

    // Apply stored preference on load
    if (localStorage.getItem('darkMode') === 'enabled') {
      document.body.classList.add('dark-mode');
      darkModeBtn.textContent = 'Light Mode';
    }
  }

  // Alpha warning
  if (acceptAlphaButton && alphaPopup) {
    acceptAlphaButton.addEventListener('click', () => {
      alphaPopup.classList.add('hidden');
      setCookie("alphaWarningAccepted", "true", 365);
    });

    // Check if warning was already accepted
    if (document.cookie.indexOf("alphaWarningAccepted=true") === -1) {
      alphaPopup.classList.remove("hidden");
    }
  }

  // Set cookie helper
  function setCookie(name, value, days) {
    const expires = new Date(Date.now() + days*24*60*60*1000).toUTCString();
    document.cookie = `${name}=${value}; expires=${expires}; path=/`;
  }
});
</script>

		
<footer id="footer">
  <div class="wrapper">
    <div class="coinblock">
      <a href="regeln">Regeln</a>
      <a href="handbuch">Handbuch</a>
      <a href="impressum">Impressum</a>
    </div>
    <p>&copy; 2025 Kizuna-Chat</p>
  </div>
</footer>
</body>
</html>
