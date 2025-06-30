<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'main.php';
check_loggedin($con);
$msg = '';

// Fetch user details
$stmt = $con->prepare('SELECT password, email, activation_code, role, title, profile_pic FROM accounts WHERE id = ?');
$stmt->bind_param('i', $_SESSION['id']);
$stmt->execute();
$stmt->bind_result($password, $email, $activation_code, $role, $title, $profile_pic);
$stmt->fetch();
$stmt->close();

$_SESSION['profile_pic'] = !empty($profile_pic) ? $profile_pic : 'uploads/default.png';
$_SESSION['title'] = !empty($title) ? $title : 'No Title';

// Fetch accepted friends (two-way relationship)
$stmt = $con->prepare("
    SELECT a.id, a.username, a.profile_pic FROM accounts a
    JOIN friends f ON (f.sender_id = a.id OR f.receiver_id = a.id)
    WHERE (f.sender_id = ? OR f.receiver_id = ?) AND f.status = 'accepted' AND a.id != ?
");
$stmt->bind_param('iii', $_SESSION['id'], $_SESSION['id'], $_SESSION['id']);
$stmt->execute();
$friends_result = $stmt->get_result();
$friends = $friends_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Pending requests sent by user
$stmt = $con->prepare("
    SELECT a.id, a.username FROM accounts a
    JOIN friends f ON f.receiver_id = a.id
    WHERE f.sender_id = ? AND f.status = 'pending'
");
$stmt->bind_param('i', $_SESSION['id']);
$stmt->execute();
$pending_result = $stmt->get_result();
$pending_requests = $pending_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Incoming friend requests
$stmt = $con->prepare("
    SELECT f.id as request_id, a.id as user_id, a.username, a.profile_pic FROM accounts a
    JOIN friends f ON f.sender_id = a.id
    WHERE f.receiver_id = ? AND f.status = 'pending'
");
$stmt->bind_param('i', $_SESSION['id']);
$stmt->execute();
$incoming_result = $stmt->get_result();
$incoming_requests = $incoming_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();


// Handle profile update
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

    if (empty($msg)) {
        $stmt = $con->prepare('SELECT * FROM accounts WHERE (username = ? OR email = ?) AND username != ? AND email != ?');
        $stmt->bind_param('ssss', $_POST['username'], $_POST['email'], $_SESSION['name'], $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $msg = 'Account already exists with that username and/or email!';
        } else {
            $stmt->close();
            $uniqid_val = account_activation && $email != $_POST['email'] ? uniqid() : $activation_code;
            $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : $password;
            
            // Handle profile picture upload (if provided)
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['size'] > 0) {
                if ($_FILES['profile_pic']['error'] !== UPLOAD_ERR_OK) {
                    $msg = 'Upload error: ' . $_FILES['profile_pic']['error'];
                } else {
                    $target_dir = "uploads/";
                    $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                    if (in_array($ext, $allowed)) {
                        $target_file = $target_dir . uniqid('profile_', true) . '.' . $ext;
                        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_file)) {
                            $new_profile_pic = $target_file;
                            $_SESSION['profile_pic'] = $new_profile_pic;
                        } else {
                            $msg = 'Failed to move uploaded file.';
                        }
                    } else {
                        $msg = 'Invalid file type. Only JPG, JPEG, PNG, and GIF files are allowed.';
                    }
                }
            } else {
                // No new file uploaded: retain current profile picture
                $new_profile_pic = $_SESSION['profile_pic'];
            }
            
            // Update account details (title update removed)
            $stmt = $con->prepare('UPDATE accounts SET username = ?, password = ?, email = ?, activation_code = ?, profile_pic = ? WHERE id = ?');
            $stmt->bind_param('sssssi', $_POST['username'], $password, $_POST['email'], $uniqid_val, $new_profile_pic, $_SESSION['id']);
            $stmt->execute();
            $stmt->close();
            $_SESSION['name'] = $_POST['username'];

            if (account_activation && $email != $_POST['email']) {
                send_activation_email($_POST['email'], $uniqid_val);
                unset($_SESSION['loggedin']);
                $msg = 'You have changed your email address, you need to re-activate your account!';
            } else {
                header('Location: profile.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-TZMCFGDZ');</script>
    <!-- End Google Tag Manager -->

    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,minimum-scale=1">
    <title>Dein Profil</title>
    <link href="style.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.1/css/all.css">
</head>
<body class="loggedin">

<!-- Google Tag Manager (noscript) -->
<noscript>
  <iframe src="https://www.googletagmanager.com/ns.html?id=GTM-TZMCFGDZ"
  height="0" width="0" style="display:none;visibility:hidden"></iframe>
</noscript>
<!-- End Google Tag Manager (noscript) -->

<nav class="navtop">
    <div>
        <h1>Anime Chat</h1>
        <a href="../index.php"><i class="fas fa-home"></i>Home</a>
        <a href="profile.php"><i class="fas fa-user-circle"></i>Profil</a>
        <a href="users.php"><i class="fas fa-user-circle"></i>User Liste</a>
        <?php if ($_SESSION['role'] == 'Admin'): ?>
        <a href="admin/index.php" target="_blank"><i class="fas fa-user-cog"></i>Admin</a>
        <?php endif; ?>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
    </div>
</nav>

<!-- Friend Overview Block -->
<div class="content">
    <h2>Friend Overview</h2>
    <div class="block">
        <h3>Your Friends</h3>
        <ul class="friend-list">
            <?php foreach ($friends as $friend): ?>
                <li>
                    <img src="<?=htmlspecialchars(!empty($friend['profile_pic']) ? $friend['profile_pic'] : 'uploads/default.png')?>" width="40" height="40" style="border-radius: 50%;">
                    <?=htmlspecialchars($friend['username'])?>
                </li>
            <?php endforeach; ?>
            <?php if (empty($friends)) echo '<li>No friends yet.</li>'; ?>
        </ul>

        <button onclick="toggleSection('pending')">Show Pending Requests</button>
        <div id="pending" class="hidden friend-section">
            <h4>Pending Requests</h4>
            <ul>
                <?php foreach ($pending_requests as $pending): ?>
                    <li><?=htmlspecialchars($pending['username'])?> (waiting for response)</li>
                <?php endforeach; ?>
                <?php if (empty($pending_requests)) echo '<li>No pending requests.</li>'; ?>
            </ul>
        </div>

        <button onclick="toggleSection('incoming')">Show Incoming Requests</button>
        <div id="incoming" class="hidden friend-section">
            <h4>Incoming Requests</h4>
            <ul>
                <?php foreach ($incoming_requests as $incoming): ?>
                    <li>
                        <img src="<?=htmlspecialchars(!empty($incoming['profile_pic']) ? $incoming['profile_pic'] : 'uploads/default.png')?>" width="40" height="40" style="border-radius: 50%; vertical-align: middle;">
                        <?=htmlspecialchars($incoming['username'])?>
                        <form style="display:inline;" method="post" action="friend_action.php">
                            <input type="hidden" name="request_id" value="<?=$incoming['request_id']?>">
                            <button type="submit" name="action" value="accept">Accept</button>
                            <button type="submit" name="action" value="decline">Decline</button>
                        </form>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($incoming_requests)) echo '<li>No incoming requests.</li>'; ?>
            </ul>
        </div>
    </div>
</div>

<!-- Profile Details Block -->
<div class="profile-container">
    <img src="<?=htmlspecialchars($_SESSION['profile_pic'])?>" alt="Profile Picture">
    <h2><?=htmlspecialchars($_SESSION['name'])?></h2>
    <p class="user-title"><?=htmlspecialchars($_SESSION['title'])?></p>
    <?php
    $roleClass = strtolower($role);
    echo '<span class="badge ' . $roleClass . '">' . htmlspecialchars($role) . '</span>';
    ?>
    <p style="margin-top: 15px; color: #333;"><strong>Email:</strong> <?=htmlspecialchars($email)?></p>
    <?php 
    // Check friendship status with the viewed profile (if not viewing own profile)
    if (isset($_GET['id']) && (int)$_GET['id'] !== $_SESSION['id']) {
        $friendship_status = 'none'; // 'none', 'pending', 'accepted'
        $stmt = $con->prepare('
            SELECT status FROM friends
            WHERE 
                (sender_id = ? AND receiver_id = ?)
             OR (sender_id = ? AND receiver_id = ?)
            LIMIT 1
        ');
        $stmt->bind_param('iiii', $_SESSION['id'], $_GET['id'], $_GET['id'], $_SESSION['id']);
        $stmt->execute();
        $stmt->bind_result($friend_status);
        if ($stmt->fetch()) {
            $friendship_status = $friend_status;
        }
        $stmt->close();
        if ($friendship_status === 'none'): ?>
            <a class="profile-btn" href="add_friend.php?receiver_id=<?= (int)$_GET['id'] ?>">Send Friend Request</a>
        <?php elseif ($friendship_status === 'pending'): ?>
            <p style="margin-top: 10px;"><em>Friend request pending</em></p>
        <?php elseif ($friendship_status === 'accepted'): ?>
            <form method="post" onsubmit="return confirm('Are you sure you want to unfriend this user?');" action="unfriend.php" style="margin-top: 10px;">
                <input type="hidden" name="unfriend_id" value="<?= (int)$_GET['id'] ?>">
                <button type="submit" class="profile-btn" style="background-color: #e74c3c;">Unfriend</button>
            </form>
        <?php endif; 
    } 
    ?>
    <!-- Re-add Edit Details button -->
    <a class="profile-btn" href="profile.php?action=edit">Edit Details</a>
    <br><a href="users.php">Back to Users</a>
</div>

<?php if (isset($_GET['action']) && $_GET['action'] == 'edit'): ?>
<div class="content profile">
    <h2>Edit Profile</h2>
    <div class="block">
        <form action="profile.php?action=edit" method="post" enctype="multipart/form-data">
            <label for="username">Username</label>
            <input type="text" value="<?=htmlspecialchars($_SESSION['name'])?>" name="username" id="username" placeholder="Username">
            <label for="password">Password</label>
            <input type="password" name="password" id="password" placeholder="Password">
            <label for="cpassword">Confirm Password</label>
            <input type="password" name="cpassword" id="cpassword" placeholder="Confirm Password">
            <label for="email">Email</label>
            <input type="email" value="<?=htmlspecialchars($email)?>" name="email" id="email" placeholder="Email">
            <label for="profile_pic">Profile Picture</label>
            <input type="file" name="profile_pic" id="profile_pic">
            <div style="margin-top: 10px;">
                <img id="previewImage" style="max-width:100%; display:none; border-radius: 10px;">
            </div>
            <canvas id="croppedCanvas" style="display: none;"></canvas>
            <br>
            <input class="profile-btn" type="submit" value="Save">
            <p><?=$msg?></p>
        </form>
    </div>
</div>
<?php endif; ?>

<script src='https://storage.ko-fi.com/cdn/scripts/overlay-widget.js'></script>
<script>
  kofiWidgetOverlay.draw('taddlhub', {
    'type': 'floating-chat',
    'floating-chat.donateButton.text': 'Support me',
    'floating-chat.donateButton.background-color': '#323842',
    'floating-chat.donateButton.text-color': '#fff'
  });
</script>

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

            if (cropper) cropper.destroy(); // destroy previous instance if any
            cropper = new Cropper(preview, {
                aspectRatio: 1,
                viewMode: 1,
                autoCropArea: 1,
                movable: true,
                zoomable: true,
                scalable: false,
                cropBoxResizable: true
            });
        };
        reader.readAsDataURL(file);
    }
});

// On form submit, replace file upload with the cropped image
document.querySelector('form').addEventListener('submit', function (e) {
    if (cropper) {
        e.preventDefault();
        cropper.getCroppedCanvas({
            width: 300,
            height: 300
        }).toBlob(function (blob) {
            const form = e.target;
            const fileInput = document.getElementById('profile_pic');
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(new File([blob], 'cropped.png', {type: 'image/png'}));
            fileInput.files = dataTransfer.files;
            form.submit(); // Now submit the form with the cropped image
        }, 'image/png');
    }
});
</script>

<script>
function toggleSection(id) {
    const el = document.getElementById(id);
    if (el.classList.contains('hidden')) {
        el.classList.remove('hidden');
    } else {
        el.classList.add('hidden');
    }
}
</script>

</body>
</html>
