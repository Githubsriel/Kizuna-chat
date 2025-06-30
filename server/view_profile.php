<?php
include 'main.php';

if (!isset($_SESSION['loggedin'])) {
    header('Location: ../index.php');
    exit;
}

// Get user ID from URL
if (!isset($_GET['id'])) {
    header('Location: users.php');
    exit;
}

$user_id = (int)$_GET['id'];

// Fetch profile details
$stmt = $con->prepare('SELECT username, profile_pic, title, role, created_at FROM accounts WHERE id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($username, $profile_pic, $title, $role, $created_at);
$stmt->fetch();
$stmt->close();

// Redirect if user doesn't exist
if (!$username) {
    header('Location: users.php');
    exit;
}

// Profile picture path
$profile_pic_path = !empty($profile_pic) ? 'uploads/' . basename($profile_pic) : 'uploads/default.png';

// Check friendship status
$friendship_status = 'none'; // 'none', 'pending', 'accepted'

$stmt = $con->prepare('
    SELECT status FROM friends
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


// Fetch friends of the profile being viewed
$stmt = $con->prepare("
    SELECT a.id, a.username, a.profile_pic FROM accounts a
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
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-TZMCFGDZ');</script>
<!-- End Google Tag Manager -->

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,minimum-scale=1">
    <title>Profil - <?=htmlspecialchars($username)?></title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.1/css/all.css">
</head>
<body>

<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-TZMCFGDZ"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->

<nav class="navtop">
    <div>
        <h1>Taddl-hub</h1>
        <a href="../index.php"><i class="fas fa-home"></i>Home</a>
        <a href="profile.php"><i class="fas fa-user-circle"></i>Profil</a>
        <a href="users.php"><i class="fas fa-user-circle"></i>User Liste</a>
        <?php if ($_SESSION['role'] == 'Admin'): ?>
        <a href="admin/index.php" target="_blank"><i class="fas fa-user-cog"></i>Admin</a>
        <?php endif; ?>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
    </div>
</nav>

<div class="profile-container">
    <img src="<?=htmlspecialchars($profile_pic_path)?>" alt="Profile Picture">
    <h2><?=htmlspecialchars($username)?></h2>
    <p><strong>Title:</strong> <?=!empty($title) ? htmlspecialchars($title) : 'No Title'?></p>
    <p><strong>Role:</strong> <?=htmlspecialchars($role)?></p>
    <p><strong>Account Created:</strong> <?=date('F j, Y', strtotime($created_at))?></p>

    <?php if ($user_id != $_SESSION['id']): ?>
    <?php if ($friendship_status === 'none'): ?>
        <a class="profile-btn" href="add_friend.php?receiver_id=<?=$user_id?>">Send Friend Request</a>
    <?php elseif ($friendship_status === 'pending'): ?>
        <p style="margin-top: 10px;"><em>Friend request pending</em></p>
    <?php elseif ($friendship_status === 'accepted'): ?>
        <form method="post" onsubmit="return confirm('Are you sure you want to unfriend this user?');" action="unfriend.php" style="margin-top: 10px;">
            <input type="hidden" name="unfriend_id" value="<?=$user_id?>">
            <button type="submit" class="profile-btn" style="background-color: #e74c3c;">Unfriend</button>
        </form>
    <?php endif; ?>
<?php endif; ?>


    <br><a href="users.php">Back to Users</a>

    <?php if (!empty($friends_of_user)): ?>
        <h3 style="margin-top: 20px;">Friends</h3>
        <ul class="friend-list">
            <?php foreach ($friends_of_user as $friend): ?>
                <li>
                    <img src="<?=htmlspecialchars(!empty($friend['profile_pic']) ? 'uploads/' . basename($friend['profile_pic']) : 'uploads/default.png')?>" width="40" height="40" style="border-radius: 50%; vertical-align: middle;">
                    <a href="view_profile.php?id=<?=$friend['id']?>" style="margin-left: 10px; font-weight: bold; color: #3274d6;">
                        <?=htmlspecialchars($friend['username'])?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p style="margin-top: 20px;">This user has no friends yet.</p>
    <?php endif; ?>
</div>

<script src='https://storage.ko-fi.com/cdn/scripts/overlay-widget.js'></script>
<script>
  kofiWidgetOverlay.draw('taddlhub', {
    'type': 'floating-chat',
    'floating-chat.donateButton.text': 'Support me',
    'floating-chat.donateButton.background-color': '#323842',
    'floating-chat.donateButton.text-color': '#fff'
  });
</script>

</body>
</html>
