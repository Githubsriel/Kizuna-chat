<?php
include 'main.php';

// Get account info and check if user is logged-in...
$stmt = $con->prepare('SELECT password, email, role, username FROM accounts WHERE id = ?');
// Get the account info using the logged-in session ID
$stmt->bind_param('i', $_SESSION['id']);
$stmt->execute();
$stmt->bind_result($password, $email, $role, $username);
$stmt->fetch();
$stmt->close();
// Check if the user is an admin...
if ($role != 'Admin') {
    exit('You do not have permission to access this page!');
}

// Use the template header from your admin main.php
$template_title = "Room Management";
template_admin_header($template_title);

// -----------------------------------------
// Process GET actions (delete)
// -----------------------------------------
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    if ($action == 'delete' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $stmt = $con->prepare("DELETE FROM rooms WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo "<p class='success'>Room deleted successfully.</p>";
        } else {
            echo "<p class='error'>Error deleting room.</p>";
        }
        $stmt->close();
    }
}

// -----------------------------------------
// Process form submission for saving room
// (For both adding and updating)
// -----------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'save') {
    $roomName = trim($_POST['name']);
    $pageBackground = trim($_POST['page_background']);
    $canvasBackground = trim($_POST['canvas_background']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
      
    if (isset($_POST['id']) && !empty($_POST['id'])) {
        // Update existing room
        $id = intval($_POST['id']);
        // Note the binding: "sssii" for string, string, string, integer, integer.
        $stmt = $con->prepare("UPDATE rooms SET name = ?, page_background = ?, canvas_background = ?, is_active = ? WHERE id = ?");
        $stmt->bind_param("sssii", $roomName, $pageBackground, $canvasBackground, $is_active, $id);
        if ($stmt->execute()) {
            echo "<p class='success'>Room updated successfully.</p>";
        } else {
            echo "<p class='error'>Error updating room.</p>";
        }
        $stmt->close();
    } else {
        // Insert new room
        $stmt = $con->prepare("INSERT INTO rooms (name, page_background, canvas_background, is_active) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $roomName, $pageBackground, $canvasBackground, $is_active);
        if ($stmt->execute()) {
            echo "<p class='success'>New room added successfully.</p>";
        } else {
            echo "<p class='error'>Error adding new room.</p>";
        }
        $stmt->close();
    }
}


// -----------------------------------------
// If editing, load the room data
// -----------------------------------------
$editing = false;
$editRoom = [
    'id' => '',
    'name' => '',
    'page_background' => '',
    'canvas_background' => '',
    'is_active' => 1
];
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $editing = true;
    $id = intval($_GET['id']);
    $stmt = $con->prepare("SELECT id, name, page_background, canvas_background, is_active FROM rooms WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($editRoom['id'], $editRoom['name'], $editRoom['page_background'], $editRoom['canvas_background'], $editRoom['is_active']);
    $stmt->fetch();
    $stmt->close();
}
?>

<!-- Form for adding or editing a room -->
<h2><?php echo $editing ? "Edit Room" : "Add New Room"; ?></h2>
<form method="post" action="rooms.php">
    <?php if ($editing): ?>
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($editRoom['id']); ?>">
    <?php endif; ?>
    <input type="hidden" name="action" value="save">
      
    <label for="name">Room Name:</label>
    <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($editRoom['name']); ?>" required>
    <br>
      
    <label for="page_background">Page Background URL:</label>
    <input type="text" name="page_background" id="page_background" value="<?php echo htmlspecialchars($editRoom['page_background']); ?>" placeholder="e.g., server/img/bg_page.jpg or https://example.com/page_bg.png" required>
    <br>
      
    <label for="canvas_background">Canvas Background URL:</label>
    <input type="text" name="canvas_background" id="canvas_background" value="<?php echo htmlspecialchars($editRoom['canvas_background']); ?>" placeholder="e.g., server/img/bg_canvas.jpg or https://example.com/canvas_bg.png" required>
    <br>
      
    <label for="is_active">Active:</label>
    <input type="checkbox" name="is_active" id="is_active" <?php if ($editRoom['is_active']) echo 'checked'; ?>>
    <br>
      
    <button type="submit"><?php echo $editing ? "Update Room" : "Add Room"; ?></button>
    <?php if ($editing): ?>
        <a href="rooms.php">Cancel</a>
    <?php endif; ?>
</form>
  
<hr>
  
<!-- List of existing rooms -->
<h2>Existing Rooms</h2>
<table border="1" cellpadding="8">
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Page Background URL</th>
        <th>Canvas Background URL</th>
        <th>Active</th>
        <th>Actions</th>
    </tr>
    <?php
    $result = $con->query("SELECT id, name, page_background, canvas_background, is_active FROM rooms ORDER BY name ASC");
    while ($row = $result->fetch_assoc()):
    ?>
    <tr>
        <td><?php echo htmlspecialchars($row['id']); ?></td>
        <td><?php echo htmlspecialchars($row['name']); ?></td>
        <td><?php echo htmlspecialchars($row['page_background']); ?></td>
        <td><?php echo htmlspecialchars($row['canvas_background']); ?></td>
        <td><?php echo $row['is_active'] ? "Yes" : "No"; ?></td>
        <td>
            <a href="rooms.php?action=edit&id=<?php echo $row['id']; ?>">Edit</a>
            <a href="rooms.php?action=delete&id=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to delete this room?');">Delete</a>
        </td>
    </tr>
    <?php endwhile; ?>
</table>
  
<?php
template_admin_footer();
?>
