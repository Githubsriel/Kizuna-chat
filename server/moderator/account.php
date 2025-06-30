<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include_once 'main.php'; // Includes session_start(), $con, template functions etc.
include_once 'moderator_functions.php';

// When performing actions:
if (isset($_POST['ban'])) {
    // ... existing ban code ...
    log_moderator_action($con, $_SESSION['id'], $account_id, "Banned account");
}

if (isset($_POST['adjust_exp'])) {
    // ... existing EXP adjustment code ...
    log_moderator_action($con, $_SESSION['id'], $account_id, 
                        "Adjusted EXP to {$_POST['exp']} (New level: $new_level)");
}
// --- RBAC Check: Moderator Access Only ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['role'])) {
    header('Location: /login.php');
    exit;
}

$loggedInUserRole = $_SESSION['role'];

if ($loggedInUserRole !== 'Moderator') {
    exit('Access Denied: This page is for moderators only.');
}
// --- End RBAC Check ---

// --- Initialize account data structure ---
$account = [
    'username' => '',
    'password' => '',
    'email' => '',
    'activation_code' => '',
    'rememberme' => '',
    'role' => 'Member',
    'title' => '',
    'exp' => 0,
    'level' => 1,
    'profile_pic' => '',
    'created_at' => '',
    'banned' => 0,
    'banned_ip' => ''
];
$page = 'Moderate';

// --- Fetch Account Data ---
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $account_id = $_GET['id'];
    $account['id'] = $account_id;

    // Fetch account data including EXP and level
    $stmt = $con->prepare('SELECT username, email, role, title, profile_pic, created_at, banned, banned_ip, level, exp FROM accounts WHERE id = ?');
    if (!$stmt) die("Prepare failed: (" . $con->errno . ") " . $con->error);

    $stmt->bind_param('i', $account_id);
    $stmt->execute();
    $stmt->bind_result(
        $account['username'], $account['email'], $account['role'],
        $account['title'], $account['profile_pic_filename'],
        $account['created_at'], $account['banned'], $account['banned_ip'],
        $account['level'], $account['exp']
    );
    $fetch_success = $stmt->fetch();
    $stmt->close();

    if (!$fetch_success) {
        exit('Account not found.');
    }

    $account['profile_pic'] = !empty($account['profile_pic_filename']) ? '../uploads/' . basename($account['profile_pic_filename']) : '../uploads/default.png';

    // --- Moderator POST Request Handling ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Ban the account
        if (isset($_POST['ban'])) {
            $ip_to_store = ''; // Moderators don't store IP on ban
            $stmt = $con->prepare('UPDATE accounts SET banned = 1, banned_ip = ? WHERE id = ?');
            if (!$stmt) die("Prepare failed: (" . $con->errno . ") " . $con->error);
            $stmt->bind_param('si', $ip_to_store, $account_id);
            if (!$stmt->execute()) die("Ban execute failed: (" . $stmt->errno . ") " . $stmt->error);
            $stmt->close();
            header('Location: index.php?success=banned&id=' . $account_id);
            exit;
        }

        // Unban the account
        if (isset($_POST['unban'])) {
            $stmt = $con->prepare('UPDATE accounts SET banned = 0, banned_ip = "" WHERE id = ?');
            if (!$stmt) die("Prepare failed: (" . $con->errno . ") " . $con->error);
            $stmt->bind_param('i', $account_id);
            if (!$stmt->execute()) die("Unban execute failed: (" . $stmt->errno . ") " . $stmt->error);
            $stmt->close();
            header('Location: index.php?success=unbanned&id=' . $account_id);
            exit;
        }

        // Handle EXP adjustment
        if (isset($_POST['adjust_exp'])) {
            $new_exp = (int)$_POST['exp'];
            $new_level = calculateLevel($new_exp);
            
            $stmt = $con->prepare('UPDATE accounts SET exp = ?, level = ? WHERE id = ?');
            if (!$stmt) die("Prepare failed: (" . $con->errno . ") " . $con->error);
            $stmt->bind_param('iii', $new_exp, $new_level, $account_id);
            if (!$stmt->execute()) die("EXP update failed: (" . $stmt->errno . ") " . $stmt->error);
            $stmt->close();
            
            // Add moderation log entry
            $moderator_id = $_SESSION['id'];
            $action = "Adjusted EXP to $new_exp (Level $new_level)";
            $log_stmt = $con->prepare('INSERT INTO moderation_logs (moderator_id, account_id, action) VALUES (?, ?, ?)');
            $log_stmt->bind_param('iis', $moderator_id, $account_id, $action);
            $log_stmt->execute();
            $log_stmt->close();
            
            header('Location: account.php?id=' . $account_id . '&success=exp_updated');
            exit;
        }
    }
} else {
    exit('No account ID specified.');
}
?>

<?=template_moderator_header($page . ' Account')?>

<h2><?=$page?> Account: <?=htmlspecialchars($account['username'])?></h2>

<div class="content-block">
    <form action="account.php?id=<?=$account['id']?>" method="post" class="form responsive-width-100">
        <div class="account-info-section">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="<?=htmlspecialchars($account['username'])?>" readonly>

            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?=htmlspecialchars($account['email'])?>" readonly>

            <label for="role">Role</label>
            <input type="text" id="role" name="role" value="<?=htmlspecialchars($account['role'])?>" readonly>

            <label for="title">Title</label>
            <input type="text" id="title" name="title" value="<?=htmlspecialchars($account['title'] ?: 'None')?>" readonly>
        </div>

        <div class="exp-section">
            <label for="exp">Experience Points (EXP)</label>
            <input type="number" id="exp" name="exp" value="<?=htmlspecialchars($account['exp'])?>" min="0">
            
            <label for="level">Current Level</label>
            <input type="text" id="level" value="<?=htmlspecialchars($account['level'])?> (<?=expToNextLevel($account['exp'])?> EXP to next level)" readonly>
            
            <input type="submit" name="adjust_exp" value="Update EXP" class="exp-btn">
        </div>

        <div class="profile-pic-section">
            <label>Profile Picture</label>
            <?php if (!empty($account['profile_pic'])): ?>
                <img src="<?=htmlspecialchars($account['profile_pic'])?>?t=<?=time()?>" alt="Profile Pic" width="80" height="80" style="display: block; margin-top: 5px; border-radius: 50%;">
            <?php else: ?>
                <p>Default picture used.</p>
            <?php endif; ?>
        </div>

        <div class="account-meta-section">
            <label>Account Created</label>
            <p><strong><?=!empty($account['created_at']) ? date('F j, Y H:i:s', strtotime($account['created_at'])) : 'Unknown'?></strong></p>

            <label>Banned Status</label>
            <p><strong><?= $account['banned'] ? 'Yes' . (!empty($account['banned_ip']) ? ' (Details Stored)' : '') : 'No' ?></strong></p>
        </div>

        <div class="moderation-actions">
            <?php if ($account['banned'] == 1): ?>
                <input type="submit" name="unban" value="Unban User" class="unban">
            <?php else: ?>
                <input type="submit" name="ban" value="Ban User" class="ban">
            <?php endif; ?>
        </div>
    </form>
</div>

<?=template_moderator_footer()?>