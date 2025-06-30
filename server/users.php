<?php
include 'main.php';

if (!isset($_SESSION['loggedin'])) {
    header('Location: index.php');
    exit;
}

// Get all users except the logged-in user, including title and role
$stmt = $con->prepare('SELECT id, username, title, profile_pic, role FROM accounts WHERE id != ?');
$stmt->bind_param('i', $_SESSION['id']);
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();




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
?>

<!DOCTYPE html>
<html lang="en">
<head>


    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,minimum-scale=1">
    <title>User Liste</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.1/css/all.css">
</head>
<body>



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





<h2 style="text-align: center; color: white;">All Users</h2>

<div class="search-bar">
    <input type="text" id="searchInput" placeholder="Search users by name or title...">
</div>

<div class="user-list" id="userList">
    <?php foreach ($users as $user): ?>
        <div class="user-card" data-username="<?=htmlspecialchars(strtolower($user['username']))?>" data-title="<?=htmlspecialchars(strtolower($user['title']))?>">
            <img src="<?=!empty($user['profile_pic']) ? 'uploads/' . basename($user['profile_pic']) : 'uploads/default.png'?>" alt="Profile Picture">
            <p class="user-title"><?=!empty($user['title']) ? htmlspecialchars($user['title']) : ''?></p>
            <p class="username"><?=htmlspecialchars($user['username'])?></p>
            <?php
				$roleClass = strtolower($user['role']);
				?>
			<span class="badge <?=$roleClass?>"><?=htmlspecialchars($user['role'])?></span>

            <a href="view_profile.php?id=<?=htmlspecialchars($user['id'])?>" class="view-profile-button">View Profile</a>
        </div>
    <?php endforeach; ?>
</div>



<!-- Simple search filtering script -->
<script>
document.getElementById("searchInput").addEventListener("input", function () {
    const search = this.value.toLowerCase();
    const cards = document.querySelectorAll(".user-card");

    cards.forEach(card => {
        const username = card.dataset.username;
        const title = card.dataset.title;

        if (username.includes(search) || title.includes(search)) {
            card.style.display = "block";
        } else {
            card.style.display = "none";
        }
    });
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
