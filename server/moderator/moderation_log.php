<?php
include_once 'main.php';
include_once 'moderator_functions.php';

// RBAC check
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'Moderator') {
    header('Location: login.php');
    exit;
}

$logs = get_moderation_logs($con, 100);

echo template_moderator_header('Moderation Log');
?>
<h2>Moderation Log</h2>
<table class="moderation-log">
    <thead>
        <tr>
            <th>Date</th>
            <th>Moderator</th>
            <th>Account</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($logs as $log): ?>
        <tr>
            <td><?=date('Y-m-d H:i', strtotime($log['created_at']))?></td>
            <td><?=htmlspecialchars($log['moderator_name'])?></td>
            <td><?=htmlspecialchars($log['account_name'])?></td>
            <td><?=htmlspecialchars($log['action'])?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?=template_moderator_footer()?>