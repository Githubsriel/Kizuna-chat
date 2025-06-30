<?php
include 'main.php';

$stmt = $con->prepare("
    SELECT 
        ct.id, 
        u.username AS user_name,
        m.username AS moderator_name,
        ct.amount, 
        ct.reason, 
        ct.created_at
    FROM coin_transactions ct
    LEFT JOIN accounts u ON ct.user_id = u.id
    LEFT JOIN accounts m ON ct.moderator_id = m.id
    ORDER BY ct.created_at DESC
");
$stmt->execute();
$result = $stmt->get_result();
$transactions = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<?=template_admin_header('Coin Transactions')?>


<h2>💰 Coin Transaktionsverlauf</h2>

<div class="content-block">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Benutzer</th>
                <th>Moderator</th>
                <th>Betrag</th>
                <th>Grund</th>
                <th>Datum</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($transactions) === 0): ?>
            <tr><td colspan="6" style="text-align:center;">Keine Transaktionen gefunden.</td></tr>
        <?php else: ?>
            <?php foreach ($transactions as $tx): ?>
            <tr>
                <td><?=htmlspecialchars($tx['id'])?></td>
                <td><?=htmlspecialchars($tx['user_name'])?></td>
                <td><?=htmlspecialchars($tx['moderator_name'])?></td>
                <td><?=($tx['amount'] > 0 ? '+' : '') . htmlspecialchars($tx['amount'])?> 🪙</td>
                <td><?=htmlspecialchars($tx['reason'])?></td>
                <td><?=date('d.m.Y H:i', strtotime($tx['created_at']))?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?=template_admin_footer()?>
