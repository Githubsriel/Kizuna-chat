<?php
session_start();
include_once 'main.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fetch roles
$roles = [];
$stmt = $con->prepare('SELECT DISTINCT role FROM accounts');
$stmt->execute();
$stmt->bind_result($role_name);
while ($stmt->fetch()) {
    $roles[] = $role_name;
}
$stmt->close();

// Fetch titles
$titles = [];
$stmt = $con->prepare('SELECT name FROM titles');
$stmt->execute();
$stmt->bind_result($title_name);
while ($stmt->fetch()) {
    $titles[] = $title_name;
}
$stmt->close();

// Set default account fields, including ip_address
$account = [
    'username'        => '', 
    'password'        => '', 
    'email'           => '', 
    'activation_code' => '', 
    'rememberme'      => '',
    'role'            => 'Member', 
    'title'           => '', 
    'profile_pic'     => '', 
    'created_at'      => '',
    'banned'          => 0, 
    'banned_ip'       => '', 
    'ip_address'      => '',  // new field for IP address
    'level'           => 1, 
    'exp'             => 0, 
    'coins'           => 0
];

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $stmt = $con->prepare('
        SELECT username, password, email, activation_code, rememberme, role, title, profile_pic, created_at, banned, banned_ip, ip_address, level, exp, coins 
        FROM accounts 
        WHERE id = ?
    ');
    $stmt->bind_param('i', $_GET['id']);
    $stmt->execute();
    $stmt->bind_result(
        $account['username'], 
        $account['password'], 
        $account['email'], 
        $account['activation_code'], 
        $account['rememberme'], 
        $account['role'], 
        $account['title'], 
        $account['profile_pic'], 
        $account['created_at'], 
        $account['banned'], 
        $account['banned_ip'], 
        $account['ip_address'],  // binding the ip_address
        $account['level'], 
        $account['exp'], 
        $account['coins']
    );
    $stmt->fetch();
    $stmt->close();

    $account['profile_pic'] = !empty($account['profile_pic']) 
        ? '../uploads/' . basename($account['profile_pic']) 
        : '../uploads/default.png';
    $page = 'Edit';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

if (isset($_POST['delete'])) {
    // If this account appears as a moderator in moderation_logs, set moderator_id to NULL.
    $stmt = $con->prepare("UPDATE moderation_logs SET moderator_id = NULL WHERE moderator_id = ?");
    $stmt->bind_param("i", $_GET['id']);
    $stmt->execute();
    $stmt->close();

    // (Optional) If the account is referenced by account_id in moderation_logs,
    // and you want to set those to NULL as well (assuming the column allows NULL):
    $stmt = $con->prepare("UPDATE moderation_logs SET account_id = NULL WHERE account_id = ?");
    $stmt->bind_param("i", $_GET['id']);
    $stmt->execute();
    $stmt->close();

    // Delete related user avatars
    $stmt = $con->prepare("DELETE FROM user_avatars WHERE user_id = ?");
    $stmt->bind_param("i", $_GET['id']);
    $stmt->execute();
    $stmt->close();

    // Finally, delete the account itself
    $stmt = $con->prepare("DELETE FROM accounts WHERE id = ?");
    $stmt->bind_param("i", $_GET['id']);
    $stmt->execute();
    $stmt->close();

    header('Location: index.php');
    exit;
}


        if (isset($_POST['ban'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
            $stmt = $con->prepare('UPDATE accounts SET banned = 1, banned_ip = ? WHERE id = ?');
            $stmt->bind_param('si', $ip, $_GET['id']);
            $stmt->execute();
            $stmt->close();
            header('Location: index.php');
            exit;
        }

        if (isset($_POST['unban'])) {
            $stmt = $con->prepare('UPDATE accounts SET banned = 0, banned_ip = "" WHERE id = ?');
            $stmt->bind_param('i', $_GET['id']);
            $stmt->execute();
            $stmt->close();
            header('Location: index.php');
            exit;
        }

        // "Add Title" functionality removed

        // Add/remove coins
        if (isset($_POST['coin_change']) && is_numeric($_POST['coin_change'])) {
            $change = (int)$_POST['coin_change'];
            $admin_id = $_SESSION['id'];
            $reason = $_POST['coin_reason'] ?? 'Admin update';

            $stmt = $con->prepare('UPDATE accounts SET coins = coins + ? WHERE id = ?');
            $stmt->bind_param('ii', $change, $_GET['id']);
            $stmt->execute();
            $stmt->close();

            $stmt = $con->prepare('INSERT INTO coin_transactions (user_id, moderator_id, amount, reason, created_at) VALUES (?, ?, ?, ?, NOW())');
            $stmt->bind_param('iiis', $_GET['id'], $admin_id, $change, $reason);
            $stmt->execute();
            $stmt->close();

            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }

        // Regular account update – now check for the "save" button instead of "submit"
        if (isset($_POST['save'])) {
            $update_fields = [];
            $params = [];
            $types = "";

            $password = $account['password'];
            if (!empty($_POST['password']) && $_POST['password'] !== $account['password']) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $update_fields[] = "password = ?";
                $params[] = $password;
                $types .= "s";
            }

            foreach (['username', 'email', 'role', 'title'] as $field) {
                if (isset($_POST[$field]) && $_POST[$field] !== $account[$field]) {
                    $update_fields[] = "$field = ?";
                    $params[] = $_POST[$field];
                    $types .= "s";
                }
            }

            if (isset($_POST['exp']) && $_POST['exp'] != $account['exp']) {
                $exp = (int)$_POST['exp'];
                $level = calculateLevel($exp);
                $update_fields[] = "exp = ?";
                $update_fields[] = "level = ?";
                $params[] = $exp;
                $params[] = $level;
                $types .= "ii";
            }

            if (!empty($_FILES['profile_pic']['name'])) {
                $target_dir = "../uploads/";
                $target_file = $target_dir . basename($_FILES['profile_pic']['name']);
                if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_file)) {
                    $update_fields[] = "profile_pic = ?";
                    $params[] = basename($target_file);
                    $types .= "s";
                }
            }

            if (count($update_fields) > 0) {
                $query = "UPDATE accounts SET " . implode(", ", $update_fields) . " WHERE id = ?";
                $params[] = $_GET['id'];
                $types .= "i";

                $stmt = $con->prepare($query);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $stmt->close();
            }

            header('Location: index.php');
            exit;
        }
    }

} else {
    // Account creation
    $page = 'Create';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $con->prepare('
            INSERT INTO accounts (username, password, email, activation_code, rememberme, role, title, profile_pic, level, exp, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 0, NOW())
        ');
        $stmt->bind_param('ssssssss', $_POST['username'], $password, $_POST['email'], $_POST['activation_code'], $_POST['rememberme'], $_POST['role'], $_POST['title'], $_POST['profile_pic']);
        $stmt->execute();
        $stmt->close();
        header('Location: index.php');
        exit;
    }
}
?>

<?=template_admin_header($page . ' Account')?>

<div class="admin-form">
    <h2><?=$page?> Account</h2>

    <div class="content-block">
    <form method="post" enctype="multipart/form-data">
        <!-- Username Field -->
        <div class="field-row">
            <label>Username</label>
            <div class="static-data"><?=htmlspecialchars($account['username'])?></div>
            <div class="editable-data">
                <input type="text" name="username" value="<?=htmlspecialchars($account['username'])?>" required>
            </div>
        </div>

        <!-- Password Field -->
        <div class="field-row">
            <label>Password</label>
            <div class="static-data"><?=htmlspecialchars($account['password'])?></div>
            <div class="editable-data">
                <input type="text" name="password" value="<?=htmlspecialchars($account['password'])?>" required>
            </div>
        </div>

        <!-- Email Field -->
        <div class="field-row">
            <label>Email</label>
            <div class="static-data"><?=htmlspecialchars($account['email'])?></div>
            <div class="editable-data">
                <input type="email" name="email" value="<?=htmlspecialchars($account['email'])?>" required>
            </div>
        </div>

        <!-- Role Field -->
        <div class="field-row">
            <label>Role</label>
            <div class="static-data"><?=$account['role']?></div>
            <div class="editable-data">
                <select name="role">
                    <?php foreach ($roles as $role): ?>
                        <option value="<?=$role?>"<?=$role == $account['role'] ? ' selected' : ''?>><?=$role?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Title Field -->
        <div class="field-row">
            <label>Title</label>
            <div class="static-data"><?=!empty($account['title']) ? $account['title'] : 'None'?></div>
            <div class="editable-data">
                <select name="title">
                    <option value="">None</option>
                    <?php foreach ($titles as $title): ?>
                        <option value="<?=$title?>"<?=$title == $account['title'] ? ' selected' : ''?>><?=$title?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- EXP Field -->
        <div class="field-row">
            <label>EXP</label>
            <div class="static-data"><?=$account['exp']?></div>
            <div class="editable-data">
                <input type="number" name="exp" value="<?=$account['exp']?>" min="0">
            </div>
        </div>

        <!-- Level Field (Static Only) -->
        <div class="field-row">
            <label>Level</label>
            <div class="static-data" style="grid-column: 1 / span 2;"><?=calculateLevel($account['exp'])?></div>
        </div>

        <!-- Coins Field (Static Only) -->
        <div class="field-row">
            <label>Coins</label>
            <div class="static-data" style="grid-column: 1 / span 2;"><?=$account['coins']?> 🪙</div>
        </div>

        <!-- IP Address (Static Only) -->
        <div class="field-row">
            <label>IP Address</label>
            <div class="static-data" style="grid-column: 1 / span 2;"><?=htmlspecialchars($account['ip_address'])?></div>
        </div>

        <!-- Coin Adjustment -->
        <div class="field-row">
            <label>Add or Remove Coins</label>
            <div class="static-data">Current Adjustment:</div>
            <div class="editable-data">
                <input type="number" name="coin_change" placeholder="+/- amount" step="1">
                <input type="text" name="coin_reason" placeholder="Reason for change (optional)">
            </div>
        </div>

        <!-- Profile Picture -->
        <div class="field-row">
            <label>Profile Picture</label>
            <div class="static-data">
                <img src="<?=htmlspecialchars($account['profile_pic'])?>" width="80" height="80" style="border-radius:50%;">
            </div>
            <div class="editable-data">
                <input type="file" name="profile_pic">
            </div>
        </div>

        <!-- Account Created Date (Static Only) -->
        <div class="field-row">
            <label>Account Created</label>
            <div class="static-data" style="grid-column: 1 / span 2;">
                <strong><?=!empty($account['created_at']) ? date('F j, Y', strtotime($account['created_at'])) : 'Unknown'?></strong>
            </div>
        </div>

        <!-- Submit Buttons -->
        <div class="button-row">
            <input type="submit" name="save" value="Save" class="btn-save">
            <?php if ($page == 'Edit'): ?>
                <?php if ($account['banned']): ?>
                    <input type="submit" name="unban" value="Unban" class="btn-unban">
                <?php else: ?>
                    <input type="submit" name="ban" value="Ban" class="btn-ban">
                <?php endif; ?>
                <input type="submit" name="delete" value="Delete" class="btn-delete" id="deleteBtn">
            <?php endif; ?>
        </div>
    </form>
    </div>
</div>

<!-- Modal Confirmation Popup for Deletion -->
<div id="deleteConfirmationModal" class="modal">
    <div class="modal-content">
        <h3>Confirm Account Deletion</h3>
        <p>Are you sure you want to delete this account? This action cannot be undone.</p>
        <button id="confirmDelete" class="btn-delete">Yes, Delete</button>
        <button id="cancelDelete" class="btn-save">Cancel</button>
    </div>
</div>

<script>
// Set up the delete button to trigger the confirmation modal
document.getElementById('deleteBtn').addEventListener('click', function(e) {
    e.preventDefault();
    document.getElementById('deleteConfirmationModal').style.display = 'flex';
});

document.getElementById('cancelDelete').addEventListener('click', function() {
    document.getElementById('deleteConfirmationModal').style.display = 'none';
});

// When confirmed, add a hidden input named "delete" then submit the form
document.getElementById('confirmDelete').addEventListener('click', function() {
    document.getElementById('deleteConfirmationModal').style.display = 'none';
    var form = document.querySelector('form');
    var hiddenInput = document.createElement("input");
    hiddenInput.type = "hidden";
    hiddenInput.name = "delete";
    hiddenInput.value = "1";
    form.appendChild(hiddenInput);
    form.submit();
});
</script>

<?=template_admin_footer()?>
