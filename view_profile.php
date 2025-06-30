<?php
include 'server/main.php';

if (!isset($_SESSION['loggedin'])) {
    header('Location: server/login.php');
    exit;
}

// Get user ID from URL
if (!isset($_GET['id'])) {
    header('Location: users.php');
    exit;
}

$user_id = (int)$_GET['id'];

// -----------------------------------------------------
// Fetch profile details (including new columns: last_activity)
// -----------------------------------------------------
$stmt = $con->prepare('
    SELECT 
        username, 
        profile_pic, 
        title, 
        role, 
        created_at, 
        last_activity,
        bio, 
        favorite_anime, 
        cover_photo
    FROM accounts
    WHERE id = ?
');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($username, $profile_pic, $title, $role, $created_at, $last_activity, $bio, $favorite_anime, $cover_photo);
$stmt->fetch();
$stmt->close();

// Redirect if user doesn't exist
if (!$username) {
    header('Location: users.php');
    exit;
}

// Convert null values to empty strings (or default image paths)
$profile_pic   = $profile_pic   ?? '';
$bio           = $bio           ?? '';
$favorite_anime = $favorite_anime ?? '';
$cover_photo   = $cover_photo   ?? '';

// Profile picture path: if not provided, use default
$profile_pic_path = !empty($profile_pic) 
    ? 'server/uploads/' . basename($profile_pic) 
    : 'server/uploads/default.png';

// Cover photo path: if not provided, use a default cover banner
$cover_photo_path = !empty($cover_photo) 
    ? 'server/uploads/' . basename($cover_photo) 
    : 'server/uploads/default_cover.png';

// -----------------------------------------------------
// Check friendship status
// -----------------------------------------------------
$friendship_status = 'none'; // Options: 'none', 'pending', 'accepted'
$stmt = $con->prepare('
    SELECT status 
    FROM friends
    WHERE 
        (sender_id = ? AND receiver_id = ?)
     OR (sender_id = ? AND receiver_id = ?)
    LIMIT 1
');
$stmt->bind_param('iiii', $_SESSION['id'], $user_id, $user_id, $_SESSION['id']);
$stmt->execute();
$stmt->bind_result($friend_status);
if ($stmt->fetch()) {
    $friendship_status = $friend_status;
}
$stmt->close();

// -----------------------------------------------------
// Fetch friends of the profile being viewed
// -----------------------------------------------------
$stmt = $con->prepare("
    SELECT a.id, a.username, a.profile_pic 
    FROM accounts a
    JOIN friends f ON (f.sender_id = a.id OR f.receiver_id = a.id)
    WHERE 
        (f.sender_id = ? OR f.receiver_id = ?)
        AND f.status = 'accepted'
        AND a.id != ?
");
$stmt->bind_param('iii', $user_id, $user_id, $user_id);
$stmt->execute();
$friends_result = $stmt->get_result();
$friends_of_user = $friends_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>

<!-- Google Tag Manager -->

<!-- End Google Tag Manager -->

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,minimum-scale=1">
    <title>Profil <?=htmlspecialchars($username)?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.1/css/all.css">
	<link rel="icon" href="favicon.ico" type="image/x-icon">
	<link rel="apple-touch-icon" href="server/img/apple_favicon.png">
</head>
<body>

<!-- Google Tag Manager (noscript) -->

<!-- End Google Tag Manager (noscript) -->

<!-- Navigation Bar -->
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

    <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'Admin'): ?>
      <a href="server/admin/index" target="_blank"><i class="fas fa-user-cog"></i>Admin</a>
    <?php endif; ?>

    <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'Moderator'): ?>
      <?php $moderator_link = "server/moderator/index"; ?>
      <a href="<?= $moderator_link ?>" target="_blank"><i class="fas fa-user-shield"></i>Moderator</a>
    <?php endif; ?>

    <a href="server/logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
    <button id="darkModeToggle" class="dark-mode-btn" type="button">Dark Mode</button>
  </div>
</nav>

<!-- Profile Header with Cover Photo & Overlapping Profile Picture -->
<div class="profile-header">
    <div class="cover-photo" style="background-image: url('<?=htmlspecialchars($cover_photo_path)?>');"></div>
    <img class="profile-pic" src="<?=htmlspecialchars($profile_pic_path)?>" alt="Profile Picture">
</div>

<div class="profile-container">
    <h2><?=htmlspecialchars($username)?></h2>

    <!-- Title -->
    <p><strong>Titel:</strong> <?=!empty($title) ? htmlspecialchars($title) : 'No Title'?></p>

    <!-- Role -->
    <p><strong>Rolle:</strong> <?=htmlspecialchars($role)?></p>

    <!-- Date Joined / Created -->
    <p><strong>Mitglied seit:</strong> <?= date('d.m.Y', strtotime($created_at)) ?></p>

    <!-- Last Online using EU/Ger format (YYYY.MM.DD HH.MM) -->
    <p><strong>Zuletzt Online:</strong> <?=date('d.m.Y H.i', strtotime($last_activity))?></p>

    <!-- Friend/Unfriend Links if viewing another user's profile -->
    <?php if ($user_id != $_SESSION['id']): ?>
        <?php if ($friendship_status === 'none'): ?>
            <a class="profile-btn" href="server/add_friend.php?receiver_id=<?=$user_id?>">Send Friend Request</a>
        <?php elseif ($friendship_status === 'pending'): ?>
            <p style="margin-top: 10px;"><em>Friend request pending</em></p>
        <?php elseif ($friendship_status === 'accepted'): ?>
            <form method="post" onsubmit="return confirm('Are you sure you want to unfriend this user?');" action="server/unfriend.php" style="margin-top: 10px;">
                <input type="hidden" name="unfriend_id" value="<?=$user_id?>">
                <button type="submit" class="profile-btn" style="background-color: #e74c3c;">Unfriend</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Display Bio if exists -->
    <?php if (!empty($bio)): ?>
        <div class="user-bio" style="margin-top: 15px;">
            <h3>About Me</h3>
            <p><?=nl2br(htmlspecialchars($bio))?></p>
        </div>
    <?php endif; ?>

    <!-- Display Favorite Anime if exists -->
    <?php if (!empty($favorite_anime)): ?>
        <div class="favorite-anime" style="margin-top: 15px;">
            <h3>Interests</h3>
            <p><?=htmlspecialchars($favorite_anime)?></p>
        </div>
    <?php endif; ?>

    <!-- Display User's Friends in a Friend Box -->
    <?php if (!empty($friends_of_user)): ?>
        <div class="friend-box">
            <h3>Friends</h3>
            <ul class="friend-list">
                <?php foreach ($friends_of_user as $friend): ?>
                    <?php 
                        $friend_pic = $friend['profile_pic'] ?? '';
                        $friend_pic_path = !empty($friend_pic) 
                            ? 'server/uploads/' . basename($friend_pic) 
                            : 'server/uploads/default.png';
                    ?>
                    <li>
                        <a href="view_profile?id=<?= (int)$friend['id'] ?>">
                            <img src="<?=htmlspecialchars($friend_pic_path)?>" width="40" height="40">
                            <?=htmlspecialchars($friend['username'])?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php else: ?>
        <p style="margin-top: 20px;">noch keine Freunde :/</p>
    <?php endif; ?>
</div>
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
<!-- Dark Mode Toggle (Optional) -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const body = document.body;
    const darkModeBtn = document.getElementById('darkModeToggle');
    
    if (localStorage.getItem('darkMode') === 'enabled') {
      body.classList.add('dark-mode');
      darkModeBtn.textContent = 'Light Mode';
    }

    darkModeBtn.addEventListener('click', function() {
      body.classList.toggle('dark-mode');
      if (body.classList.contains('dark-mode')) {
        localStorage.setItem('darkMode', 'enabled');
        darkModeBtn.textContent = 'Light Mode';
      } else {
        localStorage.setItem('darkMode', 'disabled');
        darkModeBtn.textContent = 'Dark Mode';
      }
    });
});
</script>
