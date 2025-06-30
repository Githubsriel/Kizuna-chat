<?php
session_start();
include 'main.php';
$page = "Moderator Room Logs";

// Only allow moderators or admins to view this page
if (!isset($_SESSION['role']) || 
   ($_SESSION['role'] !== 'Moderator' && $_SESSION['role'] !== 'Admin')) {
    die("Insufficient permissions.");
}

// Query active rooms
$rooms = [];
$stmt = $con->prepare("SELECT id, name FROM rooms WHERE is_active = 1 ORDER BY name ASC");
$stmt->execute();
$result = $stmt->get_result();
while ($room = $result->fetch_assoc()) {
    $rooms[] = $room;
}
$stmt->close();
?>

<style>
/* Override main's left padding on the moderator logs page */
main {
  padding-left: 250px !important;
}

/* Moderator container settings */
.moderator-container {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
  width: 100%;
  margin: 30px 0; /* Only vertical margins; no left margin */
}

/* Fixed width for the room list (sidebar for logs) */
.moderator-room-list {
  flex: 0 0 300px;    /* Fixed width of 300px */
  min-width: 300px;
  background-color: #fff;
  padding: 15px;
  border: 1px solid #dedee0;
  border-radius: 8px;
}

.moderator-room-list h2 {
  margin-top: 0;
  font-size: 18px;
  color: #394352;
}

.moderator-room-list ul {
  list-style: none;
  padding: 0;
  margin: 0;
}

.moderator-room-list li {
  padding: 10px;
  margin: 5px 0;
  border: 1px solid #eee;
  border-radius: 4px;
  cursor: pointer;
  transition: background 0.3s ease;
}

.moderator-room-list li:hover {
  background-color: #f0f0f0;
}

.moderator-room-list li.selected {
  background-color: #d0e9c6;
}

/* Log display fills the rest of the space */
.moderator-log-display {
  flex: 1;
  background-color: #fff;
  padding: 15px;
  padding-left: 350px;
  border: 1px solid #dedee0;
  border-radius: 8px;
}

.moderator-log-display h2 {
  margin-top: 0;
  font-size: 18px;
  color: #394352;
}

/* Responsive stacking on narrow screens */
@media (max-width: 768px) {
  .moderator-container {
    flex-direction: column;
  }
  .moderator-room-list,
  .moderator-log-display {
    width: 100%;
    min-width: auto;
    margin-bottom: 20px;
  }
}

</style>


<?=template_admin_header($page)?>
<!-- No changes in the header; main's left padding has been overridden in CSS for this page -->
<div class="moderator-container">
  <!-- Room List Section -->
  <aside class="moderator-room-list">
    <h2>Rooms</h2>
    <?php if (empty($rooms)): ?>
      <p>No active rooms available.</p>
    <?php else: ?>
      <ul id="roomList">
        <?php foreach ($rooms as $room): ?>
          <li class="room-item" data-room-id="<?= $room['id'] ?>">
            <?= htmlspecialchars($room['name']) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </aside>
  <!-- Log Display Section -->
  <section class="moderator-log-display">
    <h2>Message Log</h2>
    <div id="logContent">
      <p>Select a room to view the full logs.</p>
    </div>
  </section>
</div>

<script>
  // When the DOM is loaded, add click listeners to each room item
  document.addEventListener('DOMContentLoaded', function() {
      var roomItems = document.querySelectorAll('.room-item');
      var logContent = document.getElementById('logContent');

      roomItems.forEach(function(item) {
          item.addEventListener('click', function() {
              // Remove "selected" class from all items and add it to the clicked one
              roomItems.forEach(function(el) {
                  el.classList.remove('selected');
              });
              this.classList.add('selected');

              var roomId = this.getAttribute('data-room-id');
              // Fetch the message log for the selected room via AJAX
              fetch('get_room_log.php?room=' + roomId)
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    // Clear the log content area
                    logContent.innerHTML = '';
                    if(data.error) {
                        logContent.innerHTML = '<p>' + data.error + '</p>';
                    } else if(data.length === 0) {
                        logContent.innerHTML = '<p>No logs available for this room.</p>';
                    } else {
                        // Build and display the log entries
                        data.forEach(function(msg) {
                            var entry = document.createElement('div');
                            entry.className = 'log-entry';
                            entry.innerHTML = '<strong>' + msg.username + '</strong> (' + msg.created_at + '): ' + msg.message;
                            logContent.appendChild(entry);
                        });
                    }
                })
                .catch(function(error) {
                    logContent.innerHTML = '<p>Error loading logs.</p>';
                    console.error('Error fetching logs:', error);
                });
          });
      });
  });
</script>
<?=template_admin_footer()?>
