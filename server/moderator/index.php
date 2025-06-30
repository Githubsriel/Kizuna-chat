<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'main.php';
// Query to get all accounts from the database, including profile_pic, title, created_at, banned state, and banned_ip
$stmt = $con->prepare('SELECT id, username, password, email, activation_code, role, title, profile_pic, created_at, banned, banned_ip FROM accounts');
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($id, $username, $password, $email, $activation_code, $role, $title, $profile_pic, $created_at, $banned, $banned_ip);
?>

<?=template_moderator_header('Accounts')?>

<h2>Accounts</h2>



<div class="content-block">
    <div class="table">
        <table>
            <thead>
                <tr>
                    <td>#</td>
                    <td>Profile</td>
                    <td>Username</td>
                    <td class="responsive-hidden">Title</td>
                    <td class="responsive-hidden">Role</td>
                    <td class="responsive-hidden">Email</td>
                    <td class="responsive-hidden">Activation Code</td>
                    <td class="responsive-hidden">Banned</td>
                    <td class="responsive-hidden">Banned IP</td>
                    <td class="responsive-hidden">Created At</td>
                </tr>
            </thead>
            <tbody>
                <?php if ($stmt->num_rows == 0): ?>
                <tr>
                    <td colspan="10" style="text-align:center;">There are no accounts</td>
                </tr>
                <?php else: ?>
                <?php while ($stmt->fetch()): ?>
                <tr class="details" onclick="location.href='account.php?id=<?=$id?>'">
                    <td><?=$id?></td>
                    <td>
                        <img src="<?=!empty($profile_pic) ? '../uploads/' . basename($profile_pic) : '../uploads/default.png'?>" 
                             width="40" height="40" alt="Profile Picture" style="border-radius: 50%;">
                    </td>
                    <td><?=$username?></td>
                    <td class="responsive-hidden"><?=!empty($title) ? $title : 'None'?></td>
                    <td class="responsive-hidden"><?=$role?></td>
                    <td class="responsive-hidden"><?=$email?></td>
                    <td class="responsive-hidden"><?=$activation_code?></td>
                    <td class="responsive-hidden"><?=($banned == 1) ? 'Yes' : 'No'?></td>
                    <td class="responsive-hidden"><?=!empty($banned_ip) ? $banned_ip : 'N/A'?></td>
                    <td class="responsive-hidden"><?=date('F j, Y', strtotime($created_at))?></td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?=template_moderator_footer()?>
