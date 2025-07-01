<?php
// chat.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'server/main.php';
check_loggedin($con);

/* -----------------------------
   Dynamic Room Selection
------------------------------ */
$rooms = [];
$stmt = $con->prepare("SELECT id, name, page_background, canvas_background FROM rooms WHERE is_active = 1 ORDER BY name ASC");
$stmt->execute();
$resultRooms = $stmt->get_result();
while ($roomRow = $resultRooms->fetch_assoc()) {
    $rooms[$roomRow['id']] = $roomRow;
}
$stmt->close();

if (!isset($_GET['room']) || !is_numeric($_GET['room'])) {
    header("Location: chat?room=1");
    exit;
}
$roomId = intval($_GET['room']);
if (!isset($rooms[$roomId])) {
    $roomId = key($rooms);
}
$roomName         = $rooms[$roomId]['name'];
$pageBackground   = $rooms[$roomId]['page_background'];
$canvasBackground = $rooms[$roomId]['canvas_background'];

/* Update session and chat avatars to reflect the active room */
$_SESSION['current_room'] = $roomId;
$stmt = $con->prepare("UPDATE chat_avatars SET room_id = ? WHERE user_id = ?");
$stmt->bind_param("ii", $roomId, $_SESSION['id']);
$stmt->execute();
$stmt->close();

// --------------------------------------------------------------------
// Immediate ban check
// --------------------------------------------------------------------
$stmt = $con->prepare("SELECT banned FROM accounts WHERE id = ?");
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$stmt->bind_result($banned);
$stmt->fetch();
$stmt->close();
if ($banned == 1) {
    die("Your account has been banned. You are not allowed to access the chat.");
}

// --------------------------------------------------------------------
// Reset AFK status on load
// --------------------------------------------------------------------
$stmt = $con->prepare("UPDATE accounts SET afk = 0 WHERE id = ?");
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$stmt->close();

// --------------------------------------------------------------------
// Determine user's active avatar image (fallback if none is active)
// --------------------------------------------------------------------
$defaultAvatar = "server/uploads/avatars/default_avatar.png";
$activeAvatar = $defaultAvatar;
$stmt = $con->prepare("SELECT avatar_image FROM user_avatars WHERE user_id = ? AND is_active = 1 LIMIT 1");
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->bind_result($avatarFromDb);
    $stmt->fetch();
    if (!empty($avatarFromDb)) {
        $activeAvatar = $avatarFromDb;
    }
}
$stmt->close();

// --------------------------------------------------------------------
// Upsert user into chat_avatars (preserving old x,y if available)
// --------------------------------------------------------------------
$userId   = $_SESSION['id'];
$username = $_SESSION['name'];
$oldX = 100; $oldY = 100;
$stmt = $con->prepare("SELECT x, y FROM chat_avatars WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($dbX, $dbY);
if ($stmt->fetch()) {
    $oldX = $dbX;
    $oldY = $dbY;
}
$stmt->close();
$stmt = $con->prepare("
    INSERT INTO chat_avatars (user_id, username, avatar_image, x, y, room_id)
    VALUES (?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        username     = VALUES(username),
        avatar_image = VALUES(avatar_image),
        x            = VALUES(x),
        y            = VALUES(y),
        room_id      = VALUES(room_id)
");
$stmt->bind_param("issiii", $userId, $username, $activeAvatar, $oldX, $oldY, $roomId);
$stmt->execute();
$stmt->close();
$_SESSION['active_avatar'] = $activeAvatar;

// --------------------------------------------------------------------
// Pre-fill public chat log with the last 20 minutes of messages for the active room
// --------------------------------------------------------------------
$stmt = $con->prepare("
    SELECT c.id, c.message, c.created_at, c.sender_id, a.username, c.type
    FROM chat_messages c
    JOIN accounts a ON c.sender_id = a.id
    WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 20 MINUTE)
      AND c.room_id = ?
    ORDER BY c.created_at ASC
");
$stmt->bind_param("i", $roomId);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <!-- Google Tag Manager -->

  <!-- End Google Tag Manager -->
  
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($roomName) ?></title>
  <meta name="description" content="Verbinde dich mit anderen Anime-Enthusiasten im Kizuna Chat, ...">
  <meta name="keywords" content="Anime Chat, Grafischer Chat, Anime Community, Manga, Anime Fans, Online Chat, Sozial, Kizuna Chat">
  <meta name="author" content="Kizuna Chat">
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://kizuna-chat.de/">
  <meta property="og:title" content="Kizuna Chat - <?= htmlspecialchars($roomName) ?>">
  <meta property="og:description" content="Verbinde dich mit anderen Anime-Enthusiasten im Kizuna Chat, ...">
  <meta property="og:image" content="https://kizuna-chat.de/server/img/kizuna_chat.png">
  <meta property="twitter:card" content="summary_large_image">
  <meta property="twitter:url" content="https://kizuna-chat.de/">
  <meta property="twitter:title" content="Kizuna Chat - <?= htmlspecialchars($roomName) ?>">
  <meta property="twitter:description" content="Verbinde dich mit anderen Anime-Enthusiasten im Kizuna Chat, ...">
  <meta property="twitter:image" content="https://kizuna-chat.de/server/img/kizuna_chat.png">
  <meta name="robots" content="index, follow">
  
  <link rel="icon" href="/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="chat.css">
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.1/css/all.css">
  <link rel="apple-touch-icon" href="server/img/apple_favicon.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@simonwep/pickr/dist/themes/classic.min.css"/>
  
  <script src="https://cdn.jsdelivr.net/npm/@simonwep/pickr"></script>
  <script src="https://cdn.jsdelivr.net/npm/konva@8.3.11/konva.min.js"></script>
  <style>
    #konvaContainer {
       position: relative;
       z-index: 1000;
       background-image: url('<?= htmlspecialchars($canvasBackground) ?>');
       background-size: cover;
    }
    .dm-tabs {
      display: flex;
      gap: 10px;
      margin-bottom: 10px;
    }
    .dm-tab {
      padding: 5px 10px;
      cursor: pointer;
      background-color: #eee;
      border: none;
      border-radius: 4px;
    }
    .dm-tab.active {
      background-color: #ccc;
      font-weight: bold;
    }
  </style>
  
  <!-- Helper function for bubble colour conversion -->
  <script>
  function hexToRgba(hex, alpha) {
    var c;
    if(/^#([A-Fa-f0-9]{3}){1,2}$/.test(hex)){
       c = hex.substring(1).split('');
       if(c.length== 3){
         c = [c[0], c[0], c[1], c[1], c[2], c[2]];
       }
       c = '0x' + c.join('');
       return 'rgba('+[(c>>16)&255, (c>>8)&255, c&255].join(',')+','+alpha+')';
    }
    return hex;
  }
  </script>
  
  <!-- Set current bubble color from session (defaulting to white) -->
  <script>
    var currentBubbleColor = <?= json_encode(isset($_SESSION['bubble_color']) ? $_SESSION['bubble_color'] : '#ffffff') ?>;
  </script>
  
</head>
<body style="background-image: url('<?= htmlspecialchars($pageBackground) ?>'); background-size: cover;">
  <noscript>

  </noscript>
  
  <div id="topbar">
    <div class="wrapper" style="display: flex; align-items: center; justify-content: space-between">
      <div class="room-info">
        <h2><?= htmlspecialchars($roomName) ?></h2>
      </div>
    </div>
  </div>

  <div id="notificationContainer" class="notification-container"></div>
  
  <nav class="navtop">
    <div class="wrapper">
      <h1>
        <img src="server/img/chinochat.png" alt="Chinochat Icon" class="nav-icon">
        Kizuna Chat
      </h1>
      <a href="index" target="_blank"><i class="fas fa-home"></i>Home</a>
      <a href="profile" target="_blank"><i class="fas fa-user-circle"></i>Profil</a>
      <a href="marketplace" target="_blank"><i class="fas fa-store"></i>Marktplatz</a>
      <?php if ($_SESSION['role'] == 'Admin'): ?>
        <a href="server/admin/index" target="_blank"><i class="fas fa-user-cog"></i>Admin</a>
      <?php endif; ?>
      <a href="server/logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
      <button id="openSkinboxBtn">Manage Avatar</button>
      <button id="settingsBtn" class="dark-mode-btn">⚙️ Einstellungen</button>
      <div id="settingsPopup" class="gift-popup" style="display:none;">
          <button id="darkModeToggle" class="dark-mode-btn" type="button">Dark Mode</button>
          <h3>Nachrichten Lautstärke</h3>
          <input type="range" id="volumeSlider" min="0" max="1" step="0.01">
          <p><span id="volumeValue">100</span>%</p>

          <!-- Chat bubble colour settings using Pickr -->
          <div class="bubble-color-section">
            <label for="bubbleColorPicker"><strong>Sprechblasen-Farbe:</strong></label>
            <div class="color-preview-box">
              <div id="bubbleColorPicker"></div>
              <span id="currentBubbleColorLabel">#ffffff</span>
              <button onclick="saveBubbleColor()">Speichern</button>
            </div>
          </div>
      </div>
    </div>
    <div class="coinblock">
      <p><strong>Deine Münzen:</strong> <span id="coinAmount">...</span> 🪙</p>
    </div>
  </nav>
  
  <div id="dmSidebar" class="wrapper">
    <div class="dm-tabs">
      <button id="tabOnline" class="dm-tab active">Online Users</button>
      <button id="tabRooms" class="dm-tab">Rooms</button>
    </div>
    <div id="dmContent">
      <div id="onlineUsers">
        <h3>Online Users</h3>
        <div id="groupedUserList">
          <p>Loading online users...</p>
        </div>
      </div>
      <div id="roomListContent" style="display: none;">
        <h3>Join a different room:</h3>
        <ul id="roomListContainer">
          <?php foreach ($rooms as $id => $room): ?>
            <li><a href="chat.php?room=<?= $id ?>"><?= htmlspecialchars($room['name']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
  
  <div class="chat-wrapper">
    <div class="graphical-area">
      <div id="konvaContainer"></div>
      <div class="message-input">
        <form id="chatForm" action="javascript:void(0);">
          <input type="hidden" name="room" value="<?= htmlspecialchars($roomId) ?>">
          <input type="text" id="messageInput" name="message" placeholder="Type your message here" required>
          <button type="submit">Send</button>
          <input type="file" id="publicImageInput" accept="image/*" style="display:none;">
          <button type="button" id="publicImageBtn">Image</button>
          <button id="afkToggleBtn">AFK</button>
        </form>
      </div>
    </div>
  </div>
  
  <div id="chatLogContainer">
    <div id="chatLogHeader">Chat Log</div>
    <div id="chatLog" class="chat-log">
      <?php while ($row = $result->fetch_assoc()): ?>
        <div class="chat-message <?= ($row['sender_id'] == $_SESSION['id']) ? 'sent' : 'received' ?>" data-msgid="<?= htmlspecialchars($row['id']) ?>">
          <?php if ($row['type'] === 'image'): ?>
            <img src="server/get_public_image.php?file=<?= urlencode($row['message']) ?>" style="max-width:80%; max-height:200px; border:1px solid #ccc; border-radius:10px;">
          <?php else: ?>
            <strong><?= htmlspecialchars($row['username']) ?>:</strong>
            <?= htmlspecialchars($row['message']) ?>
            <div class="timestamp"><?= date('F j, Y, g:i a', strtotime($row['created_at'])) ?></div>
          <?php endif; ?>
        </div>
      <?php endwhile; ?>
    </div>
  </div>
  
  <div id="skinboxPopup">
    <div id="skinboxHeader">
      Avatar Manager
      <span class="closePopup" id="closeSkinboxBtn">&times;</span>
    </div>
    <div id="skinboxContainer"></div>
    <input type="file" id="avatarAutoInput" accept="image/png" multiple>
  </div>
  
  <div id="privateChatsContainer"></div>
  
  <!-- ===================== JavaScript Section ===================== -->
  <script>
    // Basic setup variables
    var currentUserId = <?= json_encode($_SESSION["id"]) ?>;
    var currentUsername = <?= json_encode($_SESSION["name"]) ?>;
    var currentAvatarPath = <?= json_encode($activeAvatar) ?>;
    var notificationSound = new Audio('server/sound/notification.mp3');
    var currentRoom = <?= json_encode($roomId) ?>;
    
    // Konva setup for graphical avatar area
    var fixedWidth = 1200, fixedHeight = 720;
    var stage = new Konva.Stage({
      container: 'konvaContainer',
      width: fixedWidth,
      height: fixedHeight
    });
    var layer = new Konva.Layer();
    stage.add(layer);
    var bubbleLayer = new Konva.Layer();
    stage.add(bubbleLayer);
    bubbleLayer.moveToTop();
    
    var currentUserGroup = null;
    var avatarGroups = {};
    var AVATAR_SIZE = 200;
    var bubbledMessages = {};
    var chatWindowMessageCounts = {};
    var REMOTE_AVATAR_TWEEN_DURATION = 1.0;
    var LOCAL_AVATAR_TWEEN_DURATION = 1.0;
    
    // Modern chat bubble: show bubble for current user using currentBubbleColor
    function showSpeechBubble(message, durationSeconds) {
      if (!currentUserGroup || !message) return;
      var userPos = currentUserGroup.getAbsolutePosition();
      var bubbleX = userPos.x + AVATAR_SIZE + 10;
      var bubbleY = userPos.y - 20;
      var bubbleGroup = new Konva.Group({ x: bubbleX, y: bubbleY });
      var bubbleText = new Konva.Text({
        text: message,
        fontSize: 16,
        fontFamily: 'Calibri',
        fill: 'black',
        padding: 10,
        align: 'center'
      });
      var textWidth = bubbleText.getTextWidth();
      var textHeight = bubbleText.getTextHeight();
      var bubbleWidth = textWidth + 20;
      var bubbleHeight = textHeight + 20;
      var bubbleRect = new Konva.Rect({
        width: bubbleWidth,
        height: bubbleHeight,
        fill: hexToRgba(currentBubbleColor, 0.7),
        stroke: 'black',
        strokeWidth: 2,
        cornerRadius: 10
      });
      var tail = new Konva.Path({
        data: 'M0,0 L-10,10 L0,20 Z',
        fill: hexToRgba(currentBubbleColor, 0.7),
        stroke: 'black',
        strokeWidth: 2
      });
      tail.position({ x: 0, y: bubbleHeight - 10 });
      bubbleGroup.add(bubbleRect);
      bubbleGroup.add(bubbleText);
      bubbleGroup.add(tail);
      bubbleLayer.add(bubbleGroup);
      bubbleLayer.batchDraw();
      setTimeout(function() {
        bubbleGroup.destroy();
        bubbleLayer.batchDraw();
      }, durationSeconds * 1000);
    }
    
    // Modern chat bubble: show bubble for other users using their saved bubble_color
    function showSpeechBubbleForUser(userId, message, durationSeconds) {
      var userGroup = avatarGroups[userId];
      if (!userGroup || !message) return;
      var bubbleColor = userGroup.bubble_color || '#ffffff';
      var userPos = userGroup.getAbsolutePosition();
      var bubbleX = userPos.x + AVATAR_SIZE + 10;
      var bubbleY = userPos.y - 20;
      var bubbleGroup = new Konva.Group({ x: bubbleX, y: bubbleY });
      var bubbleText = new Konva.Text({
        text: message,
        fontSize: 16,
        fontFamily: 'Calibri',
        fill: 'black',
        padding: 10,
        align: 'center'
      });
      var textWidth = bubbleText.getTextWidth();
      var textHeight = bubbleText.getTextHeight();
      var bubbleWidth = textWidth + 20;
      var bubbleHeight = textHeight + 20;
      var bubbleRect = new Konva.Rect({
        width: bubbleWidth,
        height: bubbleHeight,
        fill: hexToRgba(bubbleColor, 0.7),
        stroke: 'black',
        strokeWidth: 2,
        cornerRadius: 10
      });
      var tail = new Konva.Path({
        data: 'M0,0 L-10,10 L0,20 Z',
        fill: hexToRgba(bubbleColor, 0.7),
        stroke: 'black',
        strokeWidth: 2
      });
      tail.position({ x: 0, y: bubbleHeight - 10 });
      bubbleGroup.add(bubbleRect);
      bubbleGroup.add(bubbleText);
      bubbleGroup.add(tail);
      bubbleLayer.add(bubbleGroup);
      bubbleLayer.batchDraw();
      setTimeout(function() {
        bubbleGroup.destroy();
        bubbleLayer.batchDraw();
      }, durationSeconds * 1000);
    }
    
    // When creating an avatar group, also save the user's bubble color (if provided)
    function createAvatarGroup(user) {
      var group = new Konva.Group({ x: user.x, y: user.y, draggable: false });
      avatarGroups[user.user_id] = group;
      if (user.user_id == currentUserId) {
        currentUserGroup = group;
      }
      group.bubble_color = user.bubble_color || '#ffffff';
      var avatarImage = new Konva.Image({ image: null, width: AVATAR_SIZE, height: AVATAR_SIZE });
      group.avatarImage = avatarImage;
      var overlayHeight = 30;
      var overlay = new Konva.Rect({ x: 0, y: AVATAR_SIZE - overlayHeight, width: AVATAR_SIZE, height: overlayHeight, fill: 'rgba(0,0,0,0.6)' });
      var usernameText = new Konva.Text({
        text: user.username,
        fontSize: 16,
        fontFamily: 'Calibri',
        fill: 'white',
        x: 0,
        y: AVATAR_SIZE - overlayHeight + 5,
        width: AVATAR_SIZE,
        align: 'center'
      });
      group.add(avatarImage);
      group.add(overlay);
      group.add(usernameText);
      var imgObj = new Image();
      imgObj.onload = function() { avatarImage.image(imgObj); layer.draw(); };
      var avatarSrc = (user.avatar_image && user.avatar_image.trim().length > 0) ? user.avatar_image : currentAvatarPath;
      avatarSrc += '?t=' + new Date().getTime();
      imgObj.src = avatarSrc;
      return group;
    }
    
    function clampPosition(x, y) {
      var clampedX = Math.max(0, Math.min(x, stage.width() - AVATAR_SIZE));
      var clampedY = Math.max(0, Math.min(y, stage.height() - AVATAR_SIZE));
      return { x: clampedX, y: clampedY };
    }
    
    function updateAvatarPosition(x, y) {
      var fd = new FormData();
      fd.append('x', x);
      fd.append('y', y);
      fd.append('action', 'update_avatar');
      fetch('server.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(r => r.json())
      .then(data => { if (data.status !== 'success') console.error('Error updating avatar position:', data.message); })
      .catch(err => console.error(err));
    }
    
    // Modified fetchAvatars: Merge avatar data with bubble colors from get_bubble.php
    function fetchAvatars() {
      Promise.all([
        fetch('server.php?action=get_avatars').then(r => r.json()),
        fetch('server.php?action=get_bubble_colors').then(r => r.json())
      ]).then(([avatarData, bubbleData]) => {
          var defaultAvatar = "server/uploads/avatars/default_avatar.png";
          if (bubbleData.status === 'success') {
            avatarData.forEach(user => {
              user.bubble_color = bubbleData.colors[user.user_id] || '#ffffff';
            });
          }
          // Ensure the current user is in the avatar array
          if (!avatarData.some(u => u.user_id == currentUserId)) {
            avatarData.push({ 
              user_id: currentUserId, 
              username: currentUsername, 
              x: 100, 
              y: 100, 
              avatar_image: defaultAvatar, 
              afk: 0,
              bubble_color: currentBubbleColor
            });
          }
          avatarData.forEach(user => {
            var targetX = parseInt(user.x, 10) || 0;
            var targetY = parseInt(user.y, 10) || 0;
            var newSrc = (user.avatar_image && user.avatar_image.trim().length > 0) ? user.avatar_image : defaultAvatar;
            newSrc += '?t=' + new Date().getTime();
            if (avatarGroups[user.user_id]) {
              var group = avatarGroups[user.user_id];
              if (user.user_id != currentUserId && (Math.abs(group.x() - targetX) > 1 || Math.abs(group.y() - targetY) > 1)) {
                new Konva.Tween({ node: group, duration: REMOTE_AVATAR_TWEEN_DURATION, x: targetX, y: targetY, easing: Konva.Easings.EaseInOut }).play();
              }
              var imgObj2 = new Image();
              imgObj2.onload = function() { group.avatarImage.image(imgObj2); layer.draw(); };
              imgObj2.src = newSrc;
              showAFKIndicatorForUser(group, user.afk == 1);
            } else {
              user.avatar_image = newSrc;
              user.x = targetX;
              user.y = targetY;
              var group = createAvatarGroup(user);
              avatarGroups[user.user_id] = group;
              layer.add(group);
              showAFKIndicatorForUser(group, user.afk == 1);
            }
          });
          for (var id in avatarGroups) {
            if (!avatarData.find(u => u.user_id == id)) {
              avatarGroups[id].destroy();
              delete avatarGroups[id];
            }
          }
          layer.batchDraw();
      })
      .catch(err => console.error(err));
    }
    setInterval(fetchAvatars, 1000);
    fetchAvatars();
    
    function showAFKIndicatorForUser(group, isAfk) {
      if (isAfk) {
        group.opacity(0.5);
        if (!group.afkCloud) {
          group.afkCloud = new Konva.Group({ x: 40, y: -60 });
          var cloudPath = new Konva.Path({
            data: 'M40,20 C35,5 15,5 10,20 C-5,20 -5,40 10,40 C10,55 35,55 40,40 C55,40 55,20 40,20 Z',
            fill: 'rgba(255,255,255,0.9)',
            stroke: '#555',
            strokeWidth: 3,
            scaleX: 1.5,
            scaleY: 1.5
          });
          var afkText = new Konva.Text({
            text: 'AFK',
            fontSize: 24,
            fontFamily: 'Calibri',
            fill: 'black',
            x: 10,
            y: 30,
            width: 60,
            align: 'center'
          });
          group.afkCloud.add(cloudPath);
          group.afkCloud.add(afkText);
          group.add(group.afkCloud);
        }
        group.afkCloud.visible(true);
      } else {
        group.opacity(1);
        if (group.afkCloud) {
          group.afkCloud.visible(false);
        }
      }
    }
    
    stage.on('click', function(e) {
      if (e.target === stage && currentUserGroup) {
        var pos = stage.getPointerPosition();
        var newX = pos.x - AVATAR_SIZE / 2;
        var newY = pos.y - AVATAR_SIZE / 2;
        var clamped = clampPosition(newX, newY);
        new Konva.Tween({ node: currentUserGroup, duration: LOCAL_AVATAR_TWEEN_DURATION, x: clamped.x, y: clamped.y, easing: Konva.Easings.EaseInOut }).play();
        updateAvatarPosition(clamped.x, clamped.y);
      }
    });
    
    window.addEventListener('keydown', function(e) {
      if (!currentUserGroup) return;
      var step = 1;
      var oldX = currentUserGroup.x();
      var oldY = currentUserGroup.y();
      var newX = oldX;
      var newY = oldY;
      switch(e.key) {
        case 'ArrowUp':    newY -= step; break;
        case 'ArrowDown':  newY += step; break;
        case 'ArrowLeft':  newX -= step; break;
        case 'ArrowRight': newX += step; break;
        default: return;
      }
      e.preventDefault();
      var clamped = clampPosition(newX, newY);
      currentUserGroup.position({ x: clamped.x, y: clamped.y });
      layer.batchDraw();
      updateAvatarPosition(clamped.x, clamped.y);
    });
    
    function resizeStage() {
      var container = document.getElementById('konvaContainer');
      var containerWidth = container.offsetWidth;
      var containerHeight = container.offsetHeight;
      var scaleX = containerWidth / fixedWidth;
      var scaleY = containerHeight / fixedHeight;
      var scale = Math.min(scaleX, scaleY);
      stage.scale({ x: scale, y: scale });
      stage.draw();
    }
    window.addEventListener('resize', resizeStage);
    resizeStage();
  </script>
  
<!-- ===================== PUBLIC CHAT SCRIPT ===================== -->
<script>
  if (typeof lastActivityTime === 'undefined') {
    var lastActivityTime = Date.now();
  }
  let lastSeenId = 0;
  let displayedBubbleMessages = {};

  document.getElementById('chatForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const form = document.getElementById('chatForm');
    const fd = new FormData(form);
    const message = fd.get('message').trim();
    if (!message) return;

    // Handle AFK command
    if (message.toLowerCase().startsWith('/afk')) {
      const afkMsg = message.length > 4 ? message.substring(5).trim() : '';
      const afkFd = new FormData();
      afkFd.append('afk_message', afkMsg);
      fetch('server/toggle_afk.php', {
        method: 'POST',
        body: afkFd,
        credentials: 'same-origin'
      })
      .then(r => r.json())
      .then(data => {
        if (data.status === 'success') {
          showNotification(
            data.afk == 1
              ? `Du bist jetzt AFK${data.afk_message ? `: ${data.afk_message}` : ''}`
              : 'Du bist nicht mehr AFK.',
            'success'
          );
          document.getElementById('afkToggleBtn').textContent = data.afk == 1 ? 'Return' : 'AFK';
          form.reset();
        } else {
          showNotification('AFK-Status konnte nicht geändert werden.', 'error');
        }
      })
      .catch(err => {
        console.error('AFK toggle failed:', err);
        showNotification('AFK-Toggle fehlgeschlagen.', 'error');
      });
      return;
    }

    // Standard message handling
    fd.append('action', 'send_message');
    lastActivityTime = Date.now();
    fetch('server.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(r => r.json())
      .then(data => {
        if (data.status === 'success') {
          form.reset();
          fetchMessages();

          // XP + coin ping
          const fdActivity = new FormData();
          fdActivity.append('action', 'chat_activity_and_coins');
          return fetch('server.php', { method: 'POST', body: fdActivity, credentials: 'same-origin' });
        } else {
          console.error('Error sending message:', data.message);
        }
      })
      .then(r => r ? r.json() : null)
      .then(data => {
        if (data) {
          if (data.status === 'level_up') {
            showLevelUpNotification(data.new_level);
          } else if (data.status === 'exp_gained') {
            showExpGainedNotification(data.exp_gained, data.exp_to_next);
          }
        }
      })
      .catch(err => console.error(err));
  });

  function fetchMessages() {
    fetch('server.php?action=get_messages&room=' + currentRoom)
      .then(r => r.json())
      .then(messages => {
        const newMessages = messages.filter(m => m.id > lastSeenId);
        if (newMessages.length === 0) return;
        const chatContainer = document.getElementById('chatLog');
        const nearBottom = (chatContainer.scrollTop + chatContainer.clientHeight) >= (chatContainer.scrollHeight - 20);
        let soundPlayed = false;
        newMessages.forEach(msg => {
          if (!soundPlayed && msg.sender_id != currentUserId) {
            notificationSound.play().catch(err => console.warn('Sound error:', err));
            soundPlayed = true;
          }
        });
        let markup = "";
        newMessages.forEach(msg => {
          markup += buildMessageHTML(msg);
        });
        chatContainer.insertAdjacentHTML('beforeend', markup);
        lastSeenId = newMessages[newMessages.length - 1].id;
        processMessageBubbles(newMessages);
        if (nearBottom) {
          chatContainer.scrollTop = chatContainer.scrollHeight;
        }
      })
      .catch(err => console.error(err));
  }

  function buildMessageHTML(msg) {
    const cssClass = (msg.sender_id == currentUserId) ? 'sent' : 'received';
    let html = `<div class="chat-message ${cssClass}" data-msgid="${msg.id}">`;
    if (msg.type === 'image' && msg.image_url) {
      html += `<strong>${escapeHtml(msg.username)}:</strong><br>`;
      html += `<img src="server/get_public_image.php?file=${encodeURIComponent(msg.message)}"
                 style="max-width:80%; max-height:200px; border:1px solid #ccc; border-radius:10px;">`;
    } else {
      html += `<strong>${escapeHtml(msg.username)}:</strong> ${escapeHtml(msg.message)}`;
    }
    html += `<div class="timestamp">${escapeHtml(msg.created_at)}</div>`;
    html += `</div>`;
    return html;
  }

  function processMessageBubbles(newMessages) {
    newMessages.forEach(msg => {
      if (msg.type === 'image') return;
      const messageTime = new Date(msg.created_at);
      const now = new Date();
      const ageInSeconds = (now - messageTime) / 1000;
      if (ageInSeconds <= 10 && !displayedBubbleMessages[msg.id]) {
        displayedBubbleMessages[msg.id] = true;
        if (msg.sender_id == currentUserId) {
          showSpeechBubble(msg.message, 3);
        } else if (avatarGroups[msg.sender_id]) {
          showSpeechBubbleForUser(msg.sender_id, msg.message, 3);
        }
      }
    });
  }

  function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/[&<>"']/g, function (m) {
      switch (m) {
        case '&': return '&amp;';
        case '<': return '&lt;';
        case '>': return '&gt;';
        case '"': return '&quot;';
        case "'": return '&#039;';
      }
    });
  }

  document.getElementById('publicImageBtn').addEventListener('click', function () {
    document.getElementById('publicImageInput').click();
  });

  document.getElementById('publicImageInput').addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;
    const maxSize = 10 * 1024 * 1024;
    if (file.size > maxSize) {
      alert('Image file too large. Maximum allowed size is 10MB.');
      return;
    }
    const fd = new FormData();
    fd.append('image', file);
    fd.append('room', currentRoom);
    fd.append('action', 'send_public_image');
    fetch('server.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(r => r.json())
      .then(data => {
        if (data.status === 'success') {
          fetchMessages();
        } else {
          alert('Error sending image: ' + data.message);
        }
      })
      .catch(err => console.error(err));
  });

  setInterval(fetchMessages, 2000);
  fetchMessages();
</script>
  
  <!-- ===================== Activity / EXP / AFK SCRIPT (Merged) ===================== -->
  <script>
    if (typeof lastActivityTime === 'undefined') {
      var lastActivityTime = Date.now();
    }
    
    function pingActivityAndCoins() {
      console.log("[XP/Coins] Pinging server...");
      fetch('server.php?action=chat_activity_and_coins', {
        method: 'GET',
        credentials: 'same-origin'
      })
      .then(response => response.text())
      .then(text => {
        console.log("[XP/Coins] Raw response:", text);
        try {
          const data = JSON.parse(text);
          console.log("[XP/Coins] Parsed response:", data);
          if (data.status === 'level_up') {
            showLevelUpNotification(data.new_level);
          } else if (data.status === 'exp_gained') {
            showExpGainedNotification(data.exp_gained, data.exp_to_next);
          }
        } catch (e) {
          console.error("[XP/Coins] JSON parse error:", e);
        }
      })
      .catch(err => console.error("[XP/Coins] Fetch failed:", err));
    }
    
    function trackUserActivity() {
      const now = Date.now();
      if (now - lastActivityTime > 60000) {
        pingActivityAndCoins();
        lastActivityTime = now;
      }
    }
    
    document.addEventListener('mousemove', trackUserActivity);
    document.addEventListener('keypress', trackUserActivity);
    window.addEventListener('scroll', trackUserActivity);
    
    const chatForm = document.getElementById('chatForm');
    if (chatForm) {
      chatForm.addEventListener('submit', function () {
        lastActivityTime = Date.now();
        pingActivityAndCoins();
      });
    }
    
    setInterval(trackUserActivity, 60000);
  </script>
  
  <!-- ===================== DM Sidebar Tab Switch Script ===================== -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const tabOnline = document.getElementById('tabOnline');
      const tabRooms = document.getElementById('tabRooms');
      const onlineSection = document.getElementById('onlineUsers');
      const roomSection = document.getElementById('roomListContent');
      function setActiveTab(tabName) {
        if (tabName === 'rooms') {
          tabOnline.classList.remove('active');
          tabRooms.classList.add('active');
          onlineSection.style.display = 'none';
          roomSection.style.display = 'block';
          localStorage.setItem('lastDmTab', 'rooms');
        } else {
          tabOnline.classList.add('active');
          tabRooms.classList.remove('active');
          onlineSection.style.display = 'block';
          roomSection.style.display = 'none';
          localStorage.setItem('lastDmTab', 'online');
        }
      }
      tabOnline.addEventListener('click', function() { setActiveTab('online'); });
      tabRooms.addEventListener('click', function() { setActiveTab('rooms'); });
      const lastTab = localStorage.getItem('lastDmTab') || 'online';
      setActiveTab(lastTab);
    });
  </script>
  
  <!-- ===================== SKINBOX SCRIPT ===================== -->
  <script>
    const skinboxContainer = document.getElementById('skinboxContainer');
    const avatarAutoInput = document.getElementById('avatarAutoInput');
    function loadUserAvatars() {
      fetch('server.php?action=get_user_avatars')
      .then(r => r.json())
      .then(avatars => {
        skinboxContainer.innerHTML = '';
        let activeAvatarId = null;
        avatars.forEach(avatar => {
          if (avatar.is_active) activeAvatarId = avatar.id;
          const wrapper = document.createElement('div');
          wrapper.style.position = 'relative';
          wrapper.style.display = 'inline-block';
          const img = document.createElement('img');
          img.src = avatar.avatar_image;
          img.alt = 'User Avatar';
          img.title = 'Click to select';
          img.dataset.avatarId = avatar.id;
          img.addEventListener('click', () => setActiveAvatar(avatar.id));
          const deleteBtn = document.createElement('button');
          deleteBtn.innerHTML = '&times;';
          deleteBtn.className = 'delete-avatar-btn';
          deleteBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (confirm('Are you sure you want to delete this avatar?')) {
              deleteAvatar(avatar.id, wrapper);
            }
          });
          wrapper.appendChild(img);
          wrapper.appendChild(deleteBtn);
          skinboxContainer.appendChild(wrapper);
        });
        if (activeAvatarId !== null) updateSelectedAvatarUI(activeAvatarId);
      })
      .catch(err => console.error("Error loading avatars:", err));
    }
    
    function setActiveAvatar(avatarId) {
      const fd = new FormData();
      fd.append('action', 'set_active_avatar');
      fd.append('avatar_id', avatarId);
      updateSelectedAvatarUI(avatarId);
      fetch('server.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(r => r.json())
      .then(data => {
        if (data.status === 'success') {
          showNotification('Avatar updated successfully.', 'success');
          updateMainSiteAvatar(data.new_avatar_url);
        } else {
          showNotification(data.message, 'error');
        }
      })
      .catch(err => {
        console.error("Error setting active avatar:", err);
        showNotification('Failed to set avatar.', 'error');
      });
    }
    
    function deleteAvatar(avatarId, elementWrapper) {
      const fd = new FormData();
      fd.append('action', 'delete_avatar');
      fd.append('avatar_id', avatarId);
      fetch('server.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(r => r.json())
      .then(data => {
        if (data.status === 'success') {
          showNotification('Avatar deleted successfully.', 'success');
          elementWrapper.remove();
        } else {
          showNotification(data.message, 'error');
        }
      })
      .catch(err => {
        console.error("Error deleting avatar:", err);
        showNotification('Failed to delete avatar.', 'error');
      });
    }
    
    function updateSelectedAvatarUI(selectedAvatarId) {
      skinboxContainer.querySelectorAll('img').forEach(img => {
        img.classList.toggle('selected-skin', img.dataset.avatarId == selectedAvatarId);
      });
    }
    
    function updateMainSiteAvatar(url) {
      const mainAvatar = document.getElementById('mainUserAvatar');
      if (mainAvatar && url) mainAvatar.src = url;
    }
    
    avatarAutoInput.addEventListener('change', function() {
      Array.from(this.files).forEach(file => {
        const fd = new FormData();
        fd.append('action', 'upload_avatar');
        fd.append('avatar', file);
        fetch('server.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.text())
        .then(text => {
          try {
            const data = JSON.parse(text);
            if (data.status === 'success') {
              showNotification(data.message, 'success');
              loadUserAvatars();
            } else {
              showNotification(data.message, 'error');
            }
          } catch (err) {
            console.error("Invalid JSON from server:", text);
            showNotification('Upload succeeded but response was malformed.', 'error');
          }
        })
        .catch(err => {
          console.error("Error uploading avatar:", err);
          showNotification('Upload failed.', 'error');
        });
      });
      avatarAutoInput.value = '';
    });
    
    function showNotification(message, type) {
      const container = document.getElementById('notificationContainer');
      if (!container) {
        console.error('Notification container missing.');
        return;
      }
      const notif = document.createElement('div');
      notif.className = `notification ${type}`;
      notif.textContent = message;
      container.appendChild(notif);
      setTimeout(() => notif.classList.add('show'), 10);
      setTimeout(() => {
        notif.classList.remove('show');
        setTimeout(() => notif.remove(), 500);
      }, 5000);
    }
    
    // Dragging for skinbox popup
    const skinboxPopup = document.getElementById('skinboxPopup');
    const skinboxHeader = document.getElementById('skinboxHeader');
    skinboxHeader.onmousedown = function(event) {
      event.preventDefault();
      let shiftX = event.clientX - skinboxPopup.getBoundingClientRect().left;
      let shiftY = event.clientY - skinboxPopup.getBoundingClientRect().top;
      function moveAt(pageX, pageY) {
        skinboxPopup.style.left = pageX - shiftX + 'px';
        skinboxPopup.style.top = pageY - shiftY + 'px';
      }
      function onMouseMove(event) {
        moveAt(event.pageX, event.pageY);
      }
      document.addEventListener('mousemove', onMouseMove);
      skinboxHeader.onmouseup = function() {
        document.removeEventListener('mousemove', onMouseMove);
        skinboxHeader.onmouseup = null;
      };
    };
    skinboxHeader.ondragstart = () => false;
    loadUserAvatars();
  </script>
  
  <!-- ===================== Skinbox Popup Toggle Script ===================== -->
  <script>
    const openSkinboxButton = document.getElementById('openSkinboxBtn');
    const closeSkinboxButton = document.getElementById('closeSkinboxBtn');
    const skinboxPopupElement = document.getElementById('skinboxPopup');
    if (openSkinboxButton && skinboxPopupElement) {
      openSkinboxButton.addEventListener('click', function() {
        skinboxPopupElement.classList.add('visible');
      });
    }
    if (closeSkinboxButton && skinboxPopupElement) {
      closeSkinboxButton.addEventListener('click', function() {
        skinboxPopupElement.classList.remove('visible');
      });
    }
    if (skinboxPopupElement) {
      skinboxPopupElement.addEventListener('click', function(ev) {
        if (ev.target === skinboxPopupElement) {
          skinboxPopupElement.classList.remove('visible');
        }
      });
    }
  </script>
  
  <!-- ===================== AFK Toggle & Avatar Removal ===================== -->
  <script>
    const afkToggleBtn = document.getElementById('afkToggleBtn');
    if (afkToggleBtn) {
      afkToggleBtn.addEventListener('click', function() {
        const fd = new FormData();
        fd.append('action', 'toggle_afk');
        fetch('server.php', { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(r => r.json())
          .then(data => {
            if (data.status === 'success') {
              if (data.afk == 1) {
                console.log('You are now AFK.');
                afkToggleBtn.textContent = 'Return';
              } else {
                console.log('You are back from AFK.');
                afkToggleBtn.textContent = 'AFK';
              }
            } else {
              console.error('Error toggling AFK:', data);
            }
          })
          .catch(err => console.error('AFK toggle failed:', err));
      });
    }
  </script>
  
  <!-- ===================== Remove avatar on page unload ===================== -->
  <script>
    window.addEventListener('beforeunload', function() {
      if (navigator.sendBeacon) {
        var fd = new FormData();
        fd.append('action', 'remove_avatar');
        fd.append('user_id', currentUserId);
        navigator.sendBeacon('server.php', fd);
      } else {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'server.php', false);
        xhr.send();
      }
    });
  </script>
  
  <!-- ===================== Periodic Ban Check ===================== -->
  <script>
    function checkBanStatus() {
      fetch('server.php?action=check_ban')
      .then(r => r.json())
      .then(data => {
        if (data.banned == 1) {
          alert("Your account has been banned. Disconnecting...");
          window.location.href = "login.php";
        }
      })
      .catch(err => console.error(err));
    }
    setInterval(checkBanStatus, 5000);
  </script>
  
  <!-- ===================== Private DM UI SCRIPT ===================== -->
  <script>
    var openChats = {};
    function openChatWindow(partnerId, partnerUsername) {
      if (openChats[partnerId]) {
        openChats[partnerId].style.zIndex = 3000;
        return;
      }
      var chatWindow = document.createElement('div');
      chatWindow.className = 'private-chat-window';
      chatWindow.style.top = '100px';
      chatWindow.style.left = '100px';
      chatWindow.style.zIndex = 3000;
      var header = document.createElement('div');
      header.className = 'private-chat-header';
      header.textContent = 'Chat with ' + partnerUsername;
      var indicator = document.createElement('span');
      indicator.className = 'new-indicator';
      header.appendChild(indicator);
      var closeBtn = document.createElement('span');
      closeBtn.className = 'close-btn';
      closeBtn.textContent = '✖';
      closeBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        clearInterval(chatWindow.refreshInterval);
        chatWindow.remove();
        delete openChats[partnerId];
      });
      header.appendChild(closeBtn);
      chatWindow.appendChild(header);
      var body = document.createElement('div');
      body.className = 'private-chat-body';
      chatWindow.appendChild(body);
      var footer = document.createElement('div');
      footer.className = 'private-chat-footer';
      var input = document.createElement('input');
      input.type = 'text';
      input.placeholder = 'Type a message...';
      footer.appendChild(input);
      input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          sendBtn.click();
        }
      });
      var sendBtn = document.createElement('button');
      sendBtn.textContent = 'Send';
      sendBtn.addEventListener('click', function() {
        var message = input.value.trim();
        if (message === '') return;
        sendPrivateMessage(partnerId, message, function(success) {
          if (success) {
            input.value = '';
            loadPrivateMessages(partnerId, body, indicator);
          } else {
            alert('Error sending message.');
          }
        });
      });
      footer.appendChild(sendBtn);
      var imageInput = document.createElement('input');
      imageInput.type = 'file';
      imageInput.accept = 'image/*';
      imageInput.style.display = 'none';
      footer.appendChild(imageInput);
      var imageBtn = document.createElement('button');
      imageBtn.textContent = 'Image';
      imageBtn.addEventListener('click', function() {
        imageInput.click();
      });
      footer.appendChild(imageBtn);
      imageInput.addEventListener('change', function() {
        if (imageInput.files && imageInput.files[0]) {
          sendPrivateImage(partnerId, imageInput.files[0], function(success) {
            if (success) {
              imageInput.value = "";
              loadPrivateMessages(partnerId, body, indicator);
            } else {
              alert('Error sending image.');
            }
          });
        }
      });
      chatWindow.appendChild(footer);
      document.getElementById('privateChatsContainer').appendChild(chatWindow);
      openChats[partnerId] = chatWindow;
      dragElement(chatWindow, header);
      loadPrivateMessages(partnerId, body, indicator);
      chatWindow.refreshInterval = setInterval(function() {
        loadPrivateMessages(partnerId, body, indicator);
      }, 3000);
    }
    
    function sendPrivateMessage(receiverId, messageText, callback) {
      var fd = new FormData();
      fd.append('action', 'send_private_message');
      fd.append('receiver_id', receiverId);
      fd.append('message', messageText);
      fetch('server.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(r => r.json())
      .then(data => callback(data.status === 'success'))
      .catch(err => { console.error(err); callback(false); });
    }
    
    function sendPrivateImage(receiverId, file, callback) {
      var fd = new FormData();
      fd.append('action', 'send_private_image');
      fd.append('receiver_id', receiverId);
      fd.append('image', file);
      fetch('server.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(r => r.json())
      .then(data => callback(data.status === 'success'))
      .catch(err => { console.error(err); callback(false); });
    }
    
    function markMessagesAsRead(partnerId) {
      var params = new URLSearchParams();
      params.append('action', 'update_read_status');
      params.append('sender_id', partnerId);
      fetch('server.php', { method: 'POST', body: params, credentials: 'same-origin' })
      .then(r => r.json())
      .then(data => {
        if (data.status !== 'success') {
          console.error("Failed to mark messages as read:", data.message);
        }
      })
      .catch(err => console.error(err));
    }
    
    function loadPrivateMessages(partnerId, container, indicator) {
      var oldScrollTop = container.scrollTop;
      var oldScrollHeight = container.scrollHeight;
      var nearBottom = (container.scrollTop + container.clientHeight) >= (container.scrollHeight - 50);
      fetch('server.php?action=get_private_messages&partner_id=' + partnerId)
      .then(r => r.json())
      .then(messages => {
        var newHTML = "";
        var incomingCount = 0;
        messages.forEach(function(msg) {
          if (msg.type === 'image' && msg.image_url) {
            newHTML += '<div class="chat-message ' + (msg.sender_id == currentUserId ? 'sent' : 'received') + '">';
            newHTML += '<img src="' + msg.image_url + '" style="max-width:80%; max-height:200px; border:1px solid #ccc; border-radius:10px;">';
            newHTML += '</div>';
          } else {
            if (msg.sender_id == currentUserId) {
              newHTML += '<div class="chat-message sent">' + escapeHtml(msg.message) + '</div>';
            } else {
              newHTML += '<div class="chat-message received">' + escapeHtml(msg.message) + '</div>';
              incomingCount++;
            }
          }
        });
        if (container.innerHTML !== newHTML) {
          container.innerHTML = newHTML;
          var newScrollHeight = container.scrollHeight;
          if (nearBottom) {
            container.scrollTop = newScrollHeight;
            markMessagesAsRead(partnerId);
          } else {
            container.scrollTop = oldScrollTop + (newScrollHeight - oldScrollHeight);
          }
        }
        var lastCount = (typeof chatWindowMessageCounts[partnerId] !== "undefined") ? chatWindowMessageCounts[partnerId] : 0;
        if (incomingCount > lastCount && (container.scrollHeight - container.scrollTop - container.clientHeight) > 50) {
          indicator.style.display = 'inline-block';
          indicator.textContent = incomingCount - lastCount;
        } else {
          indicator.style.display = 'none';
        }
        chatWindowMessageCounts[partnerId] = incomingCount;
      })
      .catch(err => console.error(err));
    }
    
    function dragElement(elmnt, handle) {
      var pos = { x: 0, y: 0, mouseX: 0, mouseY: 0 };
      handle.addEventListener('mousedown', dragMouseDown);
      function dragMouseDown(e) {
        e.preventDefault();
        pos.mouseX = e.clientX;
        pos.mouseY = e.clientY;
        document.addEventListener('mousemove', elementDrag);
        document.addEventListener('mouseup', closeDragElement);
      }
      function elementDrag(e) {
        e.preventDefault();
        var dx = e.clientX - pos.mouseX;
        var dy = e.clientY - pos.mouseY;
        pos.mouseX = e.clientX;
        pos.mouseY = e.clientY;
        elmnt.style.top = (elmnt.offsetTop + dy) + "px";
        elmnt.style.left = (elmnt.offsetLeft + dx) + "px";
      }
      function closeDragElement() {
        document.removeEventListener('mouseup', closeDragElement);
        document.removeEventListener('mousemove', elementDrag);
      }
    }
    window.openChatWindow = openChatWindow;
    
    function pollNewDMs() {
      fetch('server.php?action=get_new_dms')
      .then(r => r.json())
      .then(data => {
        data.forEach(dm => {
          if (!openChats[dm.partner_id]) {
            openChatWindow(dm.partner_id, dm.partner_username);
          }
        });
      })
      .catch(err => console.error(err));
    }
    setInterval(pollNewDMs, 5000);
  </script>
  
  <!-- ===================== DM Toggle SCRIPT ===================== -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      let dmToggle = document.getElementById('dmToggle');
      if (!dmToggle) {
        dmToggle = document.createElement('div');
        dmToggle.id = 'dmToggle';
        dmToggle.innerHTML = '<i class="fas fa-bars"></i>';
        dmToggle.style.display = 'block';
        document.body.appendChild(dmToggle);
      }
      dmToggle.addEventListener('click', function() {
        const dmBlock = document.getElementById('dmSidebar');
        if (!dmBlock) return;
        if (dmBlock.classList.contains('closed')) {
          dmBlock.classList.remove('closed');
          document.body.classList.remove('dm-closed');
          document.body.classList.add('dm-open');
        } else {
          dmBlock.classList.add('closed');
          document.body.classList.remove('dm-open');
          document.body.classList.add('dm-closed');
        }
      });
    });
  </script>
  
  <!-- ===================== Adjust Chat Layout SCRIPT ===================== -->
  <script>
    function adjustChatLayout() {
      var topbarHeight = document.getElementById('topbar').offsetHeight;
      var navHeight = document.querySelector('.navtop').offsetHeight;
      var extraOffset = 50;
      var availableHeight = window.innerHeight - topbarHeight - navHeight - extraOffset;
      var canvasHeight = availableHeight * 0.5;
      document.getElementById('konvaContainer').style.height = canvasHeight + 'px';
    }
    window.addEventListener('resize', adjustChatLayout);
    adjustChatLayout();
  </script>
  
  <!-- ===================== Initialize Draggable Chat Log SCRIPT ===================== -->
  <script>
    window.addEventListener('load', function() {
      dragElement(document.getElementById('chatLogContainer'), document.getElementById('chatLogHeader'));
    });
  </script>
  
  <!-- ===================== Dark Mode Toggle SCRIPT ===================== -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const body = document.body;
      const darkModeBtn = document.getElementById('darkModeToggle');
      if (localStorage.getItem('darkMode') === 'enabled') {
        body.classList.add('dark-mode');
        darkModeBtn.textContent = 'Light Mode';
      }
      darkModeBtn.addEventListener('click', function() {
        body.classList.toggle('dark-mode');
        if (body.classList.contains('dark-mode')) {
          localStorage.setItem('darkMode', 'enabled');
          darkModeBtn.textContent = 'Light Mode';
        } else {
          localStorage.setItem('darkMode', 'disabled');
          darkModeBtn.textContent = 'Dark Mode';
        }
      });
    });
  </script>
  
  <!-- ===================== Online Users (DM) SCRIPT ===================== -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const updateInterval = 20000;
      function fetchOnlineUsers() {
        fetch('server.php?action=get_online_users')
        .then(r => r.json())
        .then(users => {
          const list = document.getElementById('groupedUserList');
          list.innerHTML = '';
          if (!users || users.length === 0) {
            list.innerHTML = '<li class="loading-message">No other users currently online.</li>';
            return;
          }
          const grouped = {};
          users.forEach(user => {
            const roomName = user.room_name || 'Unknown Room';
            if (!grouped[roomName]) grouped[roomName] = [];
            grouped[roomName].push(user);
          });
          for (const room in grouped) {
            const roomGroup = document.createElement('div');
            roomGroup.classList.add('room-group');
            const roomTitle = document.createElement('div');
            roomTitle.classList.add('room-title');
            roomTitle.textContent = room;
            roomGroup.appendChild(roomTitle);
            grouped[room].forEach(user => {
              const userCard = document.createElement('div');
              userCard.classList.add('room-user');
              userCard.innerHTML = `
                <img src="${escapeHtml(user.profile_pic_url)}" alt="Profile Picture">
                <a class="dm-username" href="view_profile?id=${user.id}" target="_blank">${escapeHtml(user.username)}</a>
                <button onclick="giftAvatar(${user.id})">Gift Avatar</button>
                <button onclick='openChatWindow(${user.id}, ${JSON.stringify(user.username)})'>Chat</button>
              `;
              roomGroup.appendChild(userCard);
            });
            list.appendChild(roomGroup);
          }
        })
        .catch(err => {
          console.error('Error fetching online users:', err);
          const fallback = document.getElementById('groupedUserList');
          if (fallback) {
            fallback.innerHTML = '<li class="loading-message">Error loading user list.</li>';
          }
        });
      }
      function escapeHtml(unsafe) {
        if (typeof unsafe !== 'string') return unsafe;
        return unsafe.replace(/&/g, "&amp;")
                     .replace(/</g, "&lt;")
                     .replace(/>/g, "&gt;")
                     .replace(/"/g, "&quot;")
                     .replace(/'/g, "&#039;");
      }
      fetchOnlineUsers();
      setInterval(fetchOnlineUsers, updateInterval);
    });
  </script>
  
  <!-- ===================== Send Avatar Gift SCRIPT ===================== -->
  <script>
    function giftAvatar(receiverId) {
      fetch('server.php?action=get_user_avatars')
      .then(res => res.json())
      .then(avatars => {
        if (avatars.length === 0) {
          showNotification('You have no avatars to gift.', 'error');
          return;
        }
        const popup = document.createElement('div');
        popup.className = 'avatar-select-popup';
        popup.innerHTML = `
          <h4>Select Avatar to Gift</h4>
          <div class="avatars-container"></div>
          <button onclick="this.parentElement.remove()">Cancel</button>
        `;
        const avatarsContainer = popup.querySelector('.avatars-container');
        avatars.forEach(avatar => {
          const img = document.createElement('img');
          img.src = avatar.avatar_image;
          img.className = 'avatar-option';
          img.onclick = () => confirmGiftAvatar(receiverId, avatar.avatar_image, popup);
          avatarsContainer.appendChild(img);
        });
        document.body.appendChild(popup);
      })
      .catch(err => {
        console.error(err);
        showNotification('Failed to load avatars.', 'error');
      });
    }
    
    function confirmGiftAvatar(receiverId, avatarImage, popupElement) {
      if (!confirm('Are you sure you want to gift this avatar?')) return;
      const fd = new FormData();
      fd.append('action', 'send_avatar_gift');
      fd.append('receiver_id', receiverId);
      fd.append('avatar_image', avatarImage);
      fetch('server.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(res => res.json())
      .then(data => {
        showNotification(data.message, data.status);
        popupElement.remove();
      })
      .catch(err => {
        console.error(err);
        showNotification('Failed to send avatar gift.', 'error');
      });
    }
    
    function checkAvatarGifts() {
      fetch('server.php?action=check_avatar_gifts')
      .then(r => r.json())
      .then(gifts => {
        gifts.forEach(gift => {
          if (!document.getElementById('gift-' + gift.id)) {
            showAvatarGiftPopup(gift);
          }
        });
      })
      .catch(err => console.error(err));
    }
    setInterval(checkAvatarGifts, 10000);
    
    function showAvatarGiftPopup(gift) {
      const popup = document.createElement('div');
      popup.id = 'gift-' + gift.id;
      popup.className = 'gift-popup';
      popup.innerHTML = `
        <p><strong>${gift.username}</strong> wants to gift you an avatar.</p>
        <img src="${gift.avatar_image}" style="width:100px;height:100px;border-radius:10px;">
        <button onclick="respondAvatarGift(${gift.id}, 'accept', this.parentElement)">Accept</button>
        <button onclick="respondAvatarGift(${gift.id}, 'decline', this.parentElement)">Decline</button>
      `;
      document.body.appendChild(popup);
    }
    
    function respondAvatarGift(giftId, response, popupElement) {
      const fd = new FormData();
      fd.append('action', 'respond_avatar_gift');
      fd.append('gift_id', giftId);
      fd.append('response', response);
      fetch('server.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(r => r.json())
      .then(data => {
        showNotification(data.message, data.status);
        popupElement.remove();
        if (response === 'accept') loadUserAvatars();
      })
      .catch(err => {
        console.error(err);
        showNotification('Error responding to gift.', 'error');
      });
    }
  </script>
  
  <!-- ===================== Initial Setup for lastSeenId ===================== -->
  <script>
    (function() {
      var messages = document.querySelectorAll('#chatLog .chat-message');
      var maxId = 0;
      messages.forEach(function(el) {
        var id = parseInt(el.getAttribute('data-msgid'));
        if (id > maxId) maxId = id;
      });
      lastSeenId = maxId;
    })();
  </script>
  
  <script>
    function fetchCoinBalance() {
      const url = `server.php?action=get_coin_balance&ts=${new Date().getTime()}`;
      fetch(url)
      .then(response => response.json())
      .then(data => {
        console.log("Fetched coin balance:", data);
        if (data.coins !== undefined) {
          document.getElementById('coinAmount').textContent = data.coins;
        }
      })
      .catch(error => console.error("Error fetching coin balance:", error));
    }
    
    document.addEventListener('DOMContentLoaded', function() {
      fetchCoinBalance();
      setInterval(fetchCoinBalance, 10000);
    });
  </script>
  
  <script>
    function refreshRoomList() {
      fetch('server.php?action=get_rooms')
      .then(r => r.json())
      .then(rooms => {
        const roomList = document.getElementById('roomListContainer');
        roomList.innerHTML = '';
        rooms.forEach(room => {
          const li = document.createElement('li');
          li.innerHTML = `<a href="chat.php?room=${room.id}">${escapeHtml(room.name)}</a>`;
          roomList.appendChild(li);
        });
      })
      .catch(err => {
        console.error('Error loading rooms:', err);
      });
    }
    refreshRoomList();
    setInterval(refreshRoomList, 30000);
  </script>

  <script>
    // Toggle settings popup display when settings button is clicked
    document.getElementById('settingsBtn').addEventListener('click', function() {
      const popup = document.getElementById('settingsPopup');
      popup.style.display = (popup.style.display === 'none' || popup.style.display === '') ? 'block' : 'none';
    });
    
    const volumeSlider = document.getElementById('volumeSlider');
    const volumeValue = document.getElementById('volumeValue');
    var savedVolume = localStorage.getItem('chatVolume');
    if (savedVolume !== null) {
      savedVolume = parseFloat(savedVolume);
      volumeSlider.value = savedVolume;
      notificationSound.volume = savedVolume;
      volumeValue.textContent = Math.round(savedVolume * 100);
    } else {
      volumeSlider.value = notificationSound.volume;
      volumeValue.textContent = Math.round(notificationSound.volume * 100);
    }
    volumeSlider.addEventListener('input', function() {
      const volume = parseFloat(this.value);
      notificationSound.volume = volume;
      volumeValue.textContent = Math.round(volume * 100);
      localStorage.setItem('chatVolume', volume);
    });
    
    // Function to save the user's chat bubble colour
    function saveBubbleColor() {
      // Use the currentBubbleColor set by the Pickr callback.
      const color = currentBubbleColor;
      const fd = new FormData();
      fd.append('color', color);
      fetch('server.php?action=set_bubble_color', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      })
      .then(r => r.json())
      .then(data => {
        if (data.status === 'success') {
          showNotification('Sprechblasenfarbe gespeichert!', 'success');
        } else {
          showNotification(data.message || 'Fehler beim Speichern.', 'error');
        }
      })
      .catch(err => {
        console.error('Error saving bubble color:', err);
        showNotification('Fehler beim Speichern.', 'error');
      });
    }
  </script>
  
<?php
// Function to retrieve the user's IP address
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ipList[0]);
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}
if (isset($_SESSION['id'])) {
    $userIP = getUserIP();
    $stmt = $con->prepare("UPDATE accounts SET ip_address = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("si", $userIP, $_SESSION['id']);
        $stmt->execute();
        $stmt->close();
    } else {
        error_log("Prepare failed: (" . $con->errno . ") " . $con->error);
    }
}
?>
  <footer id="footer">
    <div class="wrapper">
      <div class="coinblock">
        <a href="regeln">Regeln</a>
        <a href="handbuch">Handbuch</a>
        <a href="impressum">Impressum</a>
      </div>
      <p>&copy; 2025 Kizuna-Chat</p>
    </div>
  </footer>
  
<!-- ===================== Activity / EXP / AFK SCRIPT (Merged) ===================== -->
<script>
  // Notification Functions
  function showLevelUpNotification(newLevel) {
    const notif = document.createElement('div');
    notif.className = 'level-up-notification';
    notif.innerHTML = `
      <div class="level-up-content">
        <div class="level-up-icon">🎉</div>
        <div class="level-up-text">
          <h3>Level Up!</h3>
          <p>You've reached Level ${newLevel}!</p>
        </div>
      </div>
    `;
    document.body.appendChild(notif);
    setTimeout(() => notif.classList.add('show'), 100);
    setTimeout(() => {
      notif.classList.remove('show');
      setTimeout(() => notif.remove(), 500);
    }, 5000);
  }

  function showExpGainedNotification(expGained, expToNext) {
    const notif = document.createElement('div');
    notif.className = 'exp-notification';
    notif.innerHTML = `<div class="exp-content">+${expGained} EXP (${expToNext} to next level)</div>`;
    document.body.appendChild(notif);
    setTimeout(() => notif.classList.add('show'), 100);
    setTimeout(() => {
      notif.classList.remove('show');
      setTimeout(() => notif.remove(), 300);
    }, 3000);
  }

  if (typeof lastActivityTime === 'undefined') {
    var lastActivityTime = Date.now();
  }

  function pingActivityAndCoins() {
    console.log("[XP/Coins] Pinging server...");
    fetch('server.php?action=chat_activity_and_coins', {
      method: 'GET',
      credentials: 'same-origin'
    })
    .then(response => response.text())
    .then(text => {
      console.log("[XP/Coins] Raw response:", text);
      try {
        const data = JSON.parse(text);
        console.log("[XP/Coins] Parsed response:", data);
        if (data.status === 'level_up') {
          showLevelUpNotification(data.new_level);
        } else if (data.status === 'exp_gained') {
          showExpGainedNotification(data.exp_gained, data.exp_to_next);
        }
      } catch (e) {
        console.error("[XP/Coins] JSON parse error:", e);
      }
    })
    .catch(err => {
      console.error("[XP/Coins] Fetch failed:", err);
    });
  }

  function trackUserActivity() {
    const now = Date.now();
    if (now - lastActivityTime > 60000) { // every 60 seconds
      pingActivityAndCoins();
      lastActivityTime = now;
    }
  }

  document.addEventListener('mousemove', trackUserActivity);
  document.addEventListener('keypress', trackUserActivity);
  window.addEventListener('scroll', trackUserActivity);

  const chatFormElem = document.getElementById('chatForm');
  if (chatFormElem) {
    chatFormElem.addEventListener('submit', function () {
      lastActivityTime = Date.now();
      pingActivityAndCoins();
    });
  }

  setInterval(trackUserActivity, 60000);
</script>
<script>
// Ensure the currentBubbleColor variable is defined (it was set earlier in the code)
var currentBubbleColor = <?= json_encode(isset($_SESSION['bubble_color']) ? $_SESSION['bubble_color'] : '#ffffff') ?>;

// Initialize Pickr on the div container you just added
const pickr = Pickr.create({
    el: '#bubbleColorPicker',
    theme: 'classic', // Options: 'classic', 'monolith', or 'nano'
    default: currentBubbleColor,
    swatches: [
      '#F44336',
      '#E91E63',
      '#9C27B0',
      '#673AB7',
      '#3F51B5',
      '#2196F3',
      '#03A9F4',
      '#00BCD4',
      '#009688',
      '#4CAF50',
      '#8BC34A',
      '#CDDC39',
      '#FFEB3B',
      '#FFC107'
    ],
    components: {
        // Main components
        preview: true,
        opacity: true,
        hue: true,
  
        // Interaction options
        interaction: {
            hex: true,
            rgba: true,
            hsla: true,
            input: true,
            clear: false,
            save: true
        }
    }
});

// When the "save" button of Pickr is clicked, update the label and call saveBubbleColor()
pickr.on('save', (color, instance) => {
  const hexColor = color.toHEXA().toString();
  // Update the current bubble color globally and show it in the label
  currentBubbleColor = hexColor;
  document.getElementById('currentBubbleColorLabel').textContent = hexColor;
  // Optionally, automatically save the color (or let the user click the "Speichern" button)
  saveBubbleColor();
  pickr.hide();
});
</script>

</body>
</html>
