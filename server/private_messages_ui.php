<?php
// private_messages_ui.php
session_start();
include 'server/main.php';
if (!isset($_SESSION['id'])) {
    die("Not logged in.");
}
$currentUserId = $_SESSION['id'];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Private Messages</title>
      <style>
    /* Modal Popup for Skinbox */
    #skinboxPopup {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.5);
      display: none;
      justify-content: center;
      align-items: center;
      z-index: 1000;
    }
    #skinbox {
      background: #fff;
      padding: 20px;
      border-radius: 5px;
      max-width: 500px;
      width: 90%;
      position: relative;
    }
    .closePopup {
      position: absolute;
      top: 10px;
      right: 15px;
      cursor: pointer;
      font-size: 20px;
    }
    /* Direct Messages (DM) block styling */
    #dmUsers {
      background: #f1f1f1;
      border: 1px solid #ddd;
      border-radius: 5px;
      padding: 15px;
      margin: 15px;
      width: 300px;
      float: left;
      font-family: Arial, sans-serif;
    }
    #dmUsers h3 {
      margin-top: 0;
      font-size: 1.2em;
      color: #333;
    }
    #dmUsers ul {
      list-style: none;
      padding: 0;
      margin: 0;
    }
    #dmUsers li {
      margin-bottom: 10px;
      padding: 5px;
      border-bottom: 1px solid #ddd;
    }
    #dmUsers li:last-child {
      border-bottom: none;
    }
    #dmUsers button {
      background: #3498db;
      border: none;
      color: #fff;
      padding: 5px 10px;
      border-radius: 3px;
      cursor: pointer;
      margin-left: 10px;
      font-size: 0.9em;
    }
    /* Private Chat Window styling */
    .private-chat-window {
      position: absolute;
      width: 300px;
      height: 400px;
      background: #f9f9f9;
      border: 1px solid #ccc;
      box-shadow: 2px 2px 5px rgba(0,0,0,0.3);
      border-radius: 5px;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      resize: both;
      pointer-events: auto;
    }
    .private-chat-header {
      background: #3498db;
      color: #fff;
      padding: 5px 10px;
      cursor: move;
      flex-shrink: 0;
      position: relative;
    }
    .private-chat-header .close-btn {
      position: absolute;
      right: 10px;
      top: 5px;
      cursor: pointer;
    }
    .private-chat-body {
      flex-grow: 1;
      padding: 10px;
      overflow-y: auto;
      background: #fff;
    }
    .private-chat-footer {
      padding: 5px;
      flex-shrink: 0;
      border-top: 1px solid #ccc;
      background: #eee;
    }
    .private-chat-footer input[type="text"] {
      width: calc(100% - 60px);
      padding: 5px;
    }
    .private-chat-footer button {
      width: 50px;
      padding: 5px;
    }
    .chat-message {
      margin-bottom: 10px;
      word-wrap: break-word;
      font-family: Arial, sans-serif;
      font-size: 0.9em;
    }
    .chat-message.sent {
      text-align: right;
    }
    .chat-message.received {
      text-align: left;
    }
    /* Clear floats for layout */
    .clearfix::after {
      content: "";
      clear: both;
      display: table;
    }
  </style>
</head>
<body>
  <!-- Direct Messages (DM) Block -->
  <div id="dmUsers" class="clearfix">
    <h3>Direct Messages</h3>
    <ul>
      <?php foreach ($dm_users as $user): ?>
        <li>
          <?= htmlspecialchars($user['username']) ?>
          <button onclick="openChatWindow(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?>')">Chat</button>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <!-- Container for Private Chat Windows -->
  <div id="privateChatsContainer" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none;"></div>

    <script>
            var openChats = {};
function openChatWindow(partnerId, partnerUsername) {
  if (openChats[partnerId]) {
    openChats[partnerId].style.zIndex = Date.now();
    return;
  }
  var chatWindow = document.createElement('div');
  chatWindow.className = 'private-chat-window';
  chatWindow.style.top = '100px';
  chatWindow.style.left = '100px';
  chatWindow.style.zIndex = Date.now();
  
  var header = document.createElement('div');
  header.className = 'private-chat-header';
  header.textContent = 'Chat with ' + partnerUsername;
  
  var closeBtn = document.createElement('span');
  closeBtn.className = 'close-btn';
  closeBtn.textContent = '✖';
  closeBtn.onclick = function() {
    clearInterval(chatWindow.refreshInterval);
    chatWindow.remove();
    delete openChats[partnerId];
  };
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
  
  var sendBtn = document.createElement('button');
  sendBtn.textContent = 'Send';
  sendBtn.onclick = function() {
    var message = input.value.trim();
    if (message === '') return;
    sendPrivateMessage(partnerId, message, function(success) {
      if (success) {
        input.value = '';
        loadPrivateMessages(partnerId, body);
      } else {
        alert('Error sending message.');
      }
    });
  };
  footer.appendChild(sendBtn);
  
  // Create hidden file input for image messages.
  var imageInput = document.createElement('input');
  imageInput.type = 'file';
  imageInput.accept = 'image/*';
  imageInput.style.display = 'none';
  footer.appendChild(imageInput);
  
  // Add an "Image" button.
  var imageBtn = document.createElement('button');
  imageBtn.textContent = 'Image';
  imageBtn.onclick = function() {
    imageInput.click();
  };
  footer.appendChild(imageBtn);
  
  // When an image is selected, send it.
  imageInput.onchange = function() {
    if (imageInput.files && imageInput.files[0]) {
      sendPrivateImage(partnerId, imageInput.files[0], function(success) {
        if (success) {
          loadPrivateMessages(partnerId, body);
        } else {
          alert('Error sending image.');
        }
      });
    }
  };
  
  chatWindow.appendChild(footer);
  
  document.getElementById('privateChatsContainer').appendChild(chatWindow);
  openChats[partnerId] = chatWindow;
  
  dragElement(chatWindow, header);
  loadPrivateMessages(partnerId, body);
  chatWindow.refreshInterval = setInterval(function() {
    loadPrivateMessages(partnerId, body);
  }, 3000);
}

function sendPrivateMessage(receiverId, messageText, callback) {
  var formData = new FormData();
  formData.append('receiver_id', receiverId);
  formData.append('message', messageText);
  fetch('send_private_message.php', {
      method: 'POST',
      body: formData
  })
  .then(response => response.json())
  .then(data => callback(data.status === 'success'))
  .catch(err => {
      console.error(err);
      callback(false);
  });
}

function sendPrivateImage(receiverId, file, callback) {
  var formData = new FormData();
  formData.append('receiver_id', receiverId);
  formData.append('image', file);
  fetch('send_private_image.php', {
      method: 'POST',
      body: formData
  })
  .then(response => response.json())
  .then(data => callback(data.status === 'success'))
  .catch(err => {
      console.error(err);
      callback(false);
  });
}

function loadPrivateMessages(partnerId, container) {
  fetch('get_private_messages.php?partner_id=' + partnerId)
  .then(response => response.json())
  .then(messages => {
    container.innerHTML = '';
    messages.forEach(function(msg) {
      var msgDiv = document.createElement('div');
      msgDiv.className = 'chat-message';
      if (msg.type === 'image' && msg.image_url) {
        // Display the image.
        var img = document.createElement('img');
        img.src = msg.image_url;
        img.style.maxWidth = "100%";
        img.style.border = "1px solid #ccc";
        if (msg.sender_id == currentUserId) {
          msgDiv.classList.add('sent');
        } else {
          msgDiv.classList.add('received');
        }
        msgDiv.appendChild(img);
      } else {
        // Text message.
        if (msg.sender_id == currentUserId) {
          msgDiv.classList.add('sent');
          msgDiv.textContent = "You: " + msg.message;
        } else {
          msgDiv.classList.add('received');
          msgDiv.textContent = (msg.username ? msg.username : "Partner") + ": " + msg.message;
        }
      }
      container.appendChild(msgDiv);
    });
    container.scrollTop = container.scrollHeight;
  })
  .catch(err => console.error(err));
}

function dragElement(elmnt, handle) {
  var pos1 = 0, pos2 = 0, pos3 = 0, pos4 = 0;
  handle.onmousedown = dragMouseDown;
  function dragMouseDown(e) {
    e = e || window.event;
    e.preventDefault();
    pos3 = e.clientX;
    pos4 = e.clientY;
    document.onmouseup = closeDragElement;
    document.onmousemove = elementDrag;
  }
  function elementDrag(e) {
    e = e || window.event;
    e.preventDefault();
    pos1 = pos3 - e.clientX;
    pos2 = pos4 - e.clientY;
    pos3 = e.clientX;
    pos4 = e.clientY;
    elmnt.style.top = (elmnt.offsetTop - pos2) + "px";
    elmnt.style.left = (elmnt.offsetLeft - pos1) + "px";
  }
  function closeDragElement() {
    document.onmouseup = null;
    document.onmousemove = null;
  }
}

window.openChatWindow = openChatWindow;
    </script>
</body>
</html>
