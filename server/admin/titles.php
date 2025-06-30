<?php
include_once 'main.php';
check_loggedin($con, '../index.php');

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add a new title
    if (isset($_POST['add_title']) && !empty($_POST['new_title'])) {
        $new_title = trim($_POST['new_title']);
        $stmt = $con->prepare('INSERT IGNORE INTO titles (name) VALUES (?)');
        $stmt->bind_param('s', $new_title);
        $stmt->execute();
        $stmt->close();
        header('Location: titles.php');
        exit;
    }
    // Delete a title
    if (isset($_POST['delete_title']) && !empty($_POST['title_id'])) {
        $title_id = $_POST['title_id'];
        $stmt = $con->prepare('DELETE FROM titles WHERE id = ?');
        $stmt->bind_param('i', $title_id);
        $stmt->execute();
        $stmt->close();
        header('Location: titles.php');
        exit;
    }
}

// Retrieve all titles from the database
$stmt = $con->prepare('SELECT id, name FROM titles');
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($id, $name);
$titles = [];
while ($stmt->fetch()) {
    $titles[] = ['id' => $id, 'name' => $name];
}
$stmt->close();
?>

<?=template_admin_header('Manage Titles')?>

<h2>Manage Titles</h2>

<div class="content-block">
    <h3>Add New Title</h3>
    <form action="titles.php" method="post">
        <input type="text" name="new_title" placeholder="Enter new title" required>
        <input type="submit" name="add_title" value="Add Title">
    </form>
</div>

<div class="content-block">
    <h3>Existing Titles</h3>
    <?php if (empty($titles)): ?>
        <p>No titles found.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($titles as $title): ?>
            <tr>
                <td><?=$title['id']?></td>
                <td><?=$title['name']?></td>
                <td>
                    <!-- Delete title form -->
                    <form action="titles.php" method="post" style="display:inline;">
                        <input type="hidden" name="title_id" value="<?=$title['id']?>">
                        <input type="submit" name="delete_title" value="Delete" onclick="return confirm('Are you sure you want to delete this title?')">
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?=template_admin_footer()?>
