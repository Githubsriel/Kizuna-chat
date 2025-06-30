<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'server/main.php';
check_loggedin($con);
$msg = '';

// -----------------------------------------------------
// Fetch user details (including new columns, exp added, and last_activity)
// -----------------------------------------------------
$stmt = $con->prepare('
    SELECT 
        password, 
        email, 
        activation_code, 
        role, 
        title, 
        profile_pic,
        bio,
        favorite_anime,
        cover_photo,
        last_activity,
        level,
        exp
    FROM accounts
    WHERE id = ?
');
$stmt->bind_param('i', $_SESSION['id']);
$stmt->execute();
$stmt->bind_result(
    $password, 
    $email, 
    $activation_code, 
    $role, 
    $title, 
    $profile_pic,
    $bio,
    $favorite_anime,
    $cover_photo,
    $last_activity,
    $level,
    $exp
);
$stmt->fetch();
$stmt->close();

// Save level and XP into the session
$_SESSION['level'] = $level ?? 1;
$_SESSION['exp'] = $exp ?? 0;

$profile_pic    = $profile_pic    ?? '/server/uploads/default.png'; // Add a leading slash if needed
$bio            = $bio            ?? '';
$favorite_anime = $favorite_anime ?? '';
$cover_photo    = $cover_photo    ?? '';

$_SESSION['profile_pic']    = !empty($profile_pic) ? $profile_pic : '/server/uploads/default.png';
$_SESSION['title']          = !empty($title) ? $title : 'No Title';
$_SESSION['bio']            = $bio;
$_SESSION['favorite_anime'] = $favorite_anime;
$_SESSION['cover_photo']    = $cover_photo;

// Compute XP progress data using the XP system functions
$progressData = getLevelProgress($exp);

// -----------------------------------------------------
// Fetch friends & requests
// -----------------------------------------------------
$stmt = $con->prepare("
    SELECT a.id, a.username, a.profile_pic 
    FROM accounts a
    JOIN friends f ON (f.sender_id = a.id OR f.receiver_id = a.id)
    WHERE (f.sender_id = ? OR f.receiver_id = ?) 
      AND f.status = 'accepted' 
      AND a.id != ?
");
$stmt->bind_param('iii', $_SESSION['id'], $_SESSION['id'], $_SESSION['id']);
$stmt->execute();
$friends_result = $stmt->get_result();
$friends = $friends_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $con->prepare("
    SELECT a.id, a.username 
    FROM accounts a
    JOIN friends f ON f.receiver_id = a.id
    WHERE f.sender_id = ? 
      AND f.status = 'pending'
");
$stmt->bind_param('i', $_SESSION['id']);
$stmt->execute();
$pending_result = $stmt->get_result();
$pending_requests = $pending_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $con->prepare("
    SELECT f.id AS request_id, a.id AS user_id, a.username, a.profile_pic 
    FROM accounts a
    JOIN friends f ON f.sender_id = a.id
    WHERE f.receiver_id = ? 
      AND f.status = 'pending'
");
$stmt->bind_param('i', $_SESSION['id']);
$stmt->execute();
$incoming_result = $stmt->get_result();
$incoming_requests = $incoming_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// -----------------------------------------------------
// Handle profile update (including new fields)
// -----------------------------------------------------
if (isset($_POST['username'], $_POST['password'], $_POST['cpassword'], $_POST['email'])) {
    if (empty($_POST['username']) || empty($_POST['email'])) {
        $msg = 'The input fields must not be empty!';
    } else if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $msg = 'Please provide a valid email address!';
    } else if (!preg_match('/^[a-zA-Z0-9üöäÜÖÄ_-]+$/', $_POST['username'])) {
        $msg = 'Username must contain only letters, numbers, ü, ö, ä, _ or -!';
    } else if (!empty($_POST['password']) && (strlen($_POST['password']) > 20 || strlen($_POST['password']) < 5)) {
        $msg = 'Password must be between 5 and 20 characters long!';
    } else if ($_POST['cpassword'] != $_POST['password']) {
        $msg = 'Passwords do not match!';
    }

    // Retrieve and trim bio; check for character limit (250 characters)
    $new_bio = isset($_POST['bio']) ? trim($_POST['bio']) : '';
    if (strlen($new_bio) > 250) {
        $msg = 'Bio cannot exceed 250 characters.';
    }
    
    if (empty($msg)) {
        $stmt = $con->prepare('SELECT * FROM accounts WHERE (username = ? OR email = ?) AND username != ? AND email != ?');
        $stmt->bind_param('ssss', $_POST['username'], $_POST['email'], $_SESSION['name'], $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $msg = 'Account already exists with that username and/or email!';
        } else {
            $stmt->close();

            $uniqid_val = (defined('account_activation') && account_activation && $email != $_POST['email']) 
                ? uniqid() 
                : $activation_code;

            $password = !empty($_POST['password']) 
                ? password_hash($_POST['password'], PASSWORD_DEFAULT) 
                : $password;

            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['size'] > 0) {
                if ($_FILES['profile_pic']['error'] !== UPLOAD_ERR_OK) {
                    $msg = 'Upload error (profile_pic): ' . $_FILES['profile_pic']['error'];
                } else {
                    $target_dir = "server/uploads/";
                    $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
                    $allowed = ['jpg','jpeg','png','gif'];
                    if (in_array($ext, $allowed)) {
                        $target_file = $target_dir . uniqid('profile_', true) . '.' . $ext;
                        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_file)) {
                            $new_profile_pic = $target_file;
                            $_SESSION['profile_pic'] = $new_profile_pic;
                        } else {
                            $msg = 'Failed to move uploaded file (profile_pic).';
                        }
                    } else {
                        $msg = 'Invalid file type for profile picture. Allowed: JPG, JPEG, PNG, GIF';
                    }
                }
            } else {
                $new_profile_pic = $_SESSION['profile_pic'];
            }

            if (isset($_FILES['cover_photo']) && $_FILES['cover_photo']['size'] > 0) {
                if ($_FILES['cover_photo']['error'] !== UPLOAD_ERR_OK) {
                    $msg = 'Upload error (cover_photo): ' . $_FILES['cover_photo']['error'];
                } else {
                    $target_dir = "server/uploads/";
                    $ext = strtolower(pathinfo($_FILES['cover_photo']['name'], PATHINFO_EXTENSION));
                    $allowed = ['jpg','jpeg','png','gif'];
                    if (in_array($ext, $allowed)) {
                        $target_file = $target_dir . uniqid('cover_', true) . '.' . $ext;
                        if (move_uploaded_file($_FILES['cover_photo']['tmp_name'], $target_file)) {
                            $new_cover_photo = $target_file;
                            $_SESSION['cover_photo'] = $new_cover_photo;
                        } else {
                            $msg = 'Failed to move uploaded file (cover_photo).';
                        }
                    } else {
                        $msg = 'Invalid file type for cover photo. Allowed: JPG, JPEG, PNG, GIF';
                    }
                }
            } else {
                $new_cover_photo = $_SESSION['cover_photo'];
            }

            $new_favorite_anime = isset($_POST['favorite_anime']) ? trim($_POST['favorite_anime']) : '';

            if (empty($msg)) {
                $stmt_update = $con->prepare('
                    UPDATE accounts
                    SET
                        username = ?,
                        password = ?,
                        email = ?,
                        activation_code = ?,
                        profile_pic = ?,
                        bio = ?,
                        favorite_anime = ?,
                        cover_photo = ?
                    WHERE id = ?
                ');
                $stmt_update->bind_param(
                    'ssssssssi',
                    $_POST['username'],
                    $password,
                    $_POST['email'],
                    $uniqid_val,
                    $new_profile_pic,
                    $new_bio,
                    $new_favorite_anime,
                    $new_cover_photo,
                    $_SESSION['id']
                );
                $stmt_update->execute();
                $stmt_update->close();

                $_SESSION['name']           = $_POST['username'];
                $_SESSION['bio']            = $new_bio;
                $_SESSION['favorite_anime'] = $new_favorite_anime;

                if (defined('account_activation') && account_activation && $email != $_POST['email']) {
                    send_activation_email($_POST['email'], $uniqid_val);
                    unset($_SESSION['loggedin']);
                    $msg = 'You have changed your email address, please re-activate your account!';
                } else {
                    header('Location: profile.php');
                    exit;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>

<!-- Google Tag Manager -->

<!-- End Google Tag Manager -->

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,minimum-scale=1">
    <title>Kizuna Chat - Dein Profil</title>
    <link href="style.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.1/css/all.css">
	<link rel="icon" href="favicon.ico" type="image/x-icon">
	<link rel="apple-touch-icon" href="server/img/apple_favicon.png">
    <!-- CropperJS for optional image cropping (profile picture) -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

    <style>
    /* XP Progress styles */
    .xp-bar-container {
        background: #ccc;
        border-radius: 10px;
        overflow: hidden;
        max-width: 400px;
        margin-top: 10px;
    }
    .xp-bar {
        height: 20px;
        border-radius: 10px;
    }
    </style>
</head>
<body class="loggedin">

<!-- Google Tag Manager (noscript) -->

<!-- End Google Tag Manager (noscript) -->

<!-- Topbar / Header -->
<div id="topbar">
    <div class="wrapper" style="display: flex; align-items: center; justify-content: space-between">
        <!-- ... your custom topbar content ... -->
    </div>
</div>

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
        <h1><img src="server/img/chinochat.png" alt="Chinochat Icon" class="nav-icon"> Kizuna Chat</h1>
        <a href="index"><i class="fas fa-home"></i>Home</a>
        <a href="profile"><i class="fas fa-user-circle"></i>Profil</a>
		<a href="marketplace"><i class="fas fa-store"></i>Marktplatz</a>
        <?php if ($_SESSION['role'] == 'Admin'): ?>
            <a href="server/admin/index" target="_blank"><i class="fas fa-user-cog"></i>Admin</a>
        <?php endif; ?>
        <a href="server/logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
        <button id="darkModeToggle" class="dark-mode-btn" type="button">Dark Mode</button>
    </div>
</nav>

<!-- Main layout: left sidebar (friends) and right profile content -->
<div class="profile-layout">
    <!-- Friend Sidebar -->
    <div class="friend-sidebar">
        <h2>Friend Overview</h2>
        <div class="block">
            <h3>Your Friends</h3>
            <ul class="friend-list">
                <?php if (!empty($friends)): ?>
                    <?php foreach ($friends as $friend): ?>
                        <li>
                            <a href="view_profile?id=<?= (int)$friend['id'] ?>">
                                <img src="<?=htmlspecialchars($friend['profile_pic'] ?? '/server/uploads/default.png') ?>"
                                     width="40" height="40" style="border-radius: 50%;">
                                <?=htmlspecialchars($friend['username'] ?? '')?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>No friends yet.</li>
                <?php endif; ?>
            </ul>
            <button id="openFriendPopup" class="friend-request-btn">
                Friend Requests
                <?php if (count($incoming_requests) > 0): ?>
                    <span class="indicator"><?= count($incoming_requests) ?></span>
                <?php endif; ?>
            </button>
        </div>
    </div>

    <!-- Profile Content -->
    <div class="profile-content">
        <!-- Profile Header with Cover Photo and Overlapping Profile Picture -->
        <div class="profile-header">
            <?php if (!empty($_SESSION['cover_photo'])): ?>
                <div class="cover-photo" style="background-image: url('<?=htmlspecialchars($_SESSION['cover_photo'])?>');"></div>
            <?php endif; ?>
            <img class="profile-pic" src="<?=htmlspecialchars($_SESSION['profile_pic'] ?? '/server/uploads/default.png')?>" alt="Profile Picture">
        </div>

        <!-- Profile Details Block -->
        <div class="profile-container">
            <h2><?=htmlspecialchars($_SESSION['name'] ?? '')?></h2>
            <p class="user-title"><?=htmlspecialchars($_SESSION['title'] ?? '')?></p>
            <?php
            $roleClass = strtolower($role ?? 'User');
            echo '<span class="badge ' . $roleClass . '">' . htmlspecialchars($role ?? 'User') . '</span>';
            ?>
            <p style="margin-top: 10px; color: #333;">
                <strong>Level:</strong> 
                <span class="level-badge" data-level="<?= htmlspecialchars($level ?? 1) ?>">
                    <?= htmlspecialchars($level ?? 1) ?>
                </span>
            </p>
            <!-- XP Progress Display -->
            <p style="margin-top: 10px; color: #333;">
                <strong>XP Fortschritt:</strong>
                Du hast <?= htmlspecialchars($progressData['aktuelle_xp']) ?> XP in diesem Level,
                es fehlen <strong><?= htmlspecialchars($progressData['noch_benoetigte_xp']) ?></strong> XP,
                um das nächste Level zu erreichen (<?= htmlspecialchars($progressData['fortschritt']) ?>% abgeschlossen).
            </p>
            <div class="xp-bar-container">
                <div class="xp-bar" style="width: <?= htmlspecialchars($progressData['fortschritt']) ?>%; background: #4caf50;"></div>
            </div>
            <p style="margin-top: 15px; color: #333;">
                <strong>Email:</strong> <?=htmlspecialchars($email ?? '')?>
            </p>
            <!-- New: Last Online / Activity Display -->
            <p style="margin-top: 10px; color: #333;">
                <strong>Zuletzt online:</strong> <?= date('Y.m.d H.i', strtotime($last_activity)) ?>
            </p>

            <?php if (!isset($_GET['id']) || (int)$_GET['id'] === $_SESSION['id']): ?>
                <a class="profile-btn" href="profile.php?action=edit">Edit Details</a>
            <?php endif; ?>
        </div>

        <?php if (isset($_GET['action']) && $_GET['action'] === 'edit'): ?>
        <div class="content profile">
            <h2>Edit Profile</h2>
            <div class="block modern-form">
                <form action="profile.php?action=edit" method="post" enctype="multipart/form-data">
                    <label for="username">Username</label>
                    <input type="text" 
                           value="<?=htmlspecialchars($_SESSION['name'] ?? '')?>" 
                           name="username" 
                           id="username" 
                           placeholder="Username">
                    <label for="password">New Password (optional)</label>
                    <input type="password" name="password" id="password" placeholder="Password">
                    <label for="cpassword">Confirm Password</label>
                    <input type="password" name="cpassword" id="cpassword" placeholder="Confirm Password">
                    <label for="email">Email</label>
                    <input type="email" 
                           value="<?=htmlspecialchars($email ?? '')?>" 
                           name="email" 
                           id="email" 
                           placeholder="Email">
                    <label for="bio">Bio / About Me</label>
                    <textarea name="bio" id="bio" rows="4" placeholder="Tell us about yourself..." maxlength="250"><?=htmlspecialchars($bio ?? '')?></textarea>
                    <label for="favorite_anime">Favorite Anime / Interests</label>
                    <input type="text"
                           name="favorite_anime"
                           id="favorite_anime"
                           value="<?=htmlspecialchars($favorite_anime ?? '')?>"
                           placeholder="e.g. Naruto, One Piece, Attack on Titan">
                    <label for="profile_pic">Profile Picture</label>
                    <input type="file" name="profile_pic" id="profile_pic">
                    <img id="previewImage" style="max-width:100%; display:none; border-radius: 10px; margin-top: 0.5rem;">
                    <label for="cover_photo">Cover Photo</label>
                    <input type="file" name="cover_photo" id="cover_photo">
                    <input class="profile-btn" type="submit" value="Save Changes">
                    <?php if (!empty($msg)): ?>
                        <p class="msg"><?=htmlspecialchars($msg)?></p>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Friend Requests Modal Popup -->
<div id="friendPopup" class="modal">
    <div class="modal-content">
        <span class="close" id="closeFriendPopup">&times;</span>
        <h2>Friend Requests
            <?php if(count($incoming_requests) > 0): ?>
                <span class="indicator"><?= count($incoming_requests) ?></span>
            <?php endif; ?>
        </h2>
        <div class="friend-requests">
            <h3>Pending Requests (Sent by You)</h3>
            <ul>
                <?php if (!empty($pending_requests)): ?>
                    <?php foreach ($pending_requests as $pending): ?>
                        <li><?=htmlspecialchars($pending['username'] ?? '')?> (waiting for response)</li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>No pending requests.</li>
                <?php endif; ?>
            </ul>
            <h3>Incoming Requests</h3>
            <ul>
                <?php if (!empty($incoming_requests)): ?>
                    <?php foreach ($incoming_requests as $incoming): ?>
                        <li>
                            <img src="<?=htmlspecialchars($incoming['profile_pic'] ?? '/server/uploads/default.png') ?>"
                                 width="40" height="40" style="border-radius: 50%; vertical-align: middle;">
                            <?=htmlspecialchars($incoming['username'] ?? '')?>
                            <form style="display:inline;" method="post" action="server/friend_action.php">
                                <input type="hidden" name="request_id" value="<?=htmlspecialchars($incoming['request_id'] ?? '')?>">
                                <button type="submit" name="action" value="accept" class="profile-btn" style="padding: 0.3rem 0.6rem; font-size: 0.9rem;">Accept</button>
                                <button type="submit" name="action" value="decline" class="profile-btn" style="padding: 0.3rem 0.6rem; font-size: 0.9rem;">Decline</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>No incoming requests.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
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

<!-- Friend Requests Popup Script -->
<script>
var modal = document.getElementById("friendPopup");
var btn = document.getElementById("openFriendPopup");
var span = document.getElementById("closeFriendPopup");

btn.onclick = function() {
    modal.style.display = "block";
}
span.onclick = function() {
    modal.style.display = "none";
}
window.onclick = function(event) {
    if (event.target == modal) {
        modal.style.display = "none";
    }
}
</script>

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

<!-- CropperJS (Profile Pic) -->
<script>
let cropper;
document.getElementById('profile_pic').addEventListener('change', function (e) {
    const file = e.target.files[0];
    const preview = document.getElementById('previewImage');
    const reader = new FileReader();

    if (file && file.type.match('image.*')) {
        reader.onload = function (event) {
            preview.src = event.target.result;
            preview.style.display = 'block';

            if (cropper) cropper.destroy();
            cropper = new Cropper(preview, {
                aspectRatio: 1,
                viewMode: 1,
                autoCropArea: 1
            });
        };
        reader.readAsDataURL(file);
    }
});
</script>

</body>
</html>
