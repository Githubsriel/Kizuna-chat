// Get canvas and context
const canvas = document.getElementById('chatCanvas');
const ctx = canvas.getContext('2d');

// Avatar dimensions and text offset
const avatarWidth = 50;
const avatarHeight = 50;
const usernameOffset = 15; // Pixels below the avatar for the username

// Current user details from session (set in PHP)
const myUserId = '<?= $_SESSION["id"] ?>';
const myUsername = '<?= $_SESSION["name"] ?>';
const myAvatarImagePath = '<?= isset($_SESSION["profile_pic"]) ? $_SESSION["profile_pic"] : "server/uploads/avatars/default_avatar.png" ?>';

// Global objects to store avatar data and image cache
let avatars = {}; // Format: { user_id: { x, y, avatar_image, username } }
let avatarImages = {}; // Cache for loaded images

// Object to track the current user's avatar state
let myAvatar = {
  x: 100,
  y: 100,
  isDragging: false
};

// Draw all avatars and usernames on the canvas
function drawCanvas() {
  ctx.clearRect(0, 0, canvas.width, canvas.height);
  ctx.font = "12px Arial";
  ctx.fillStyle = "#000";

  for (const userId in avatars) {
    const av = avatars[userId];

    // If the image is not cached, load it and attach error handler
    if (!avatarImages[av.avatar_image]) {
      avatarImages[av.avatar_image] = new Image();
      avatarImages[av.avatar_image].src = av.avatar_image;
      avatarImages[av.avatar_image].onload = drawCanvas;
      avatarImages[av.avatar_image].onerror = function() {
        console.error("Error loading image:", av.avatar_image);
      };
    }

    // Draw avatar image if loaded and valid
    const img = avatarImages[av.avatar_image];
    if (img.complete && img.naturalWidth > 0) {
      try {
        ctx.drawImage(img, av.x, av.y, avatarWidth, avatarHeight);
      } catch (error) {
        console.error("Error drawing image:", av.avatar_image, error);
      }
    } else {
      // Optionally, you can log if the image isn't ready or is broken
      // console.error("Image not ready or broken:", av.avatar_image);
    }
    
    // Use fallback if the username is missing (should not occur now)
    const displayUsername = (av.username && av.username.trim().length > 0) ? av.username : myUsername;
    
    // Draw the username centered below the avatar
    const textWidth = ctx.measureText(displayUsername).width;
    const textX = av.x + (avatarWidth / 2) - (textWidth / 2);
    const textY = av.y + avatarHeight + usernameOffset;
    ctx.fillText(displayUsername, textX, textY);
  }
}

// Fetch avatars from the server via get_avatars.php
function fetchAvatars() {
  fetch('get_avatars.php')
    .then(response => response.json())
    .then(data => {
      avatars = {};
      data.forEach(av => {
        avatars[av.user_id] = {
          x: parseInt(av.x),
          y: parseInt(av.y),
          avatar_image: av.avatar_image,
          username: av.username
        };
      });
      // Ensure current user's avatar is updated with local drag data
      if (avatars[myUserId]) {
        avatars[myUserId].x = myAvatar.x;
        avatars[myUserId].y = myAvatar.y;
      } else {
        avatars[myUserId] = {
          x: myAvatar.x,
          y: myAvatar.y,
          avatar_image: myAvatarImagePath,
          username: myUsername
        };
      }
      drawCanvas();
    })
    .catch(err => console.error(err));
}

// Polling: update avatars every 1000 ms
setInterval(fetchAvatars, 1000);
fetchAvatars(); // Initial fetch

// Event listeners for drag & drop (only for current user's avatar)
canvas.addEventListener('mousedown', (e) => {
  const rect = canvas.getBoundingClientRect();
  const mouseX = e.clientX - rect.left;
  const mouseY = e.clientY - rect.top;
  console.log("mousedown at:", mouseX, mouseY, "current myAvatar:", myAvatar);
  
  // Check if the click is within the current user's avatar area using local myAvatar
  if (mouseX >= myAvatar.x && mouseX <= myAvatar.x + avatarWidth &&
      mouseY >= myAvatar.y && mouseY <= myAvatar.y + avatarHeight) {
    myAvatar.isDragging = true;
    console.log("Dragging started");
  }
});

canvas.addEventListener('mousemove', (e) => {
  if (myAvatar.isDragging) {
    const rect = canvas.getBoundingClientRect();
    myAvatar.x = e.clientX - rect.left - (avatarWidth / 2);
    myAvatar.y = e.clientY - rect.top - (avatarHeight / 2);
    console.log("Dragging, new coordinates:", myAvatar.x, myAvatar.y);
    
    // Update current user's data in the avatars object
    avatars[myUserId] = {
      x: myAvatar.x,
      y: myAvatar.y,
      avatar_image: myAvatarImagePath,
      username: myUsername
    };
    drawCanvas();
    updateAvatarPosition(myAvatar.x, myAvatar.y);
  }
});

canvas.addEventListener('mouseup', () => {
  if (myAvatar.isDragging) {
    myAvatar.isDragging = false;
    console.log("Dragging ended");
  }
});

canvas.addEventListener('mouseleave', () => {
  if (myAvatar.isDragging) {
    myAvatar.isDragging = false;
    console.log("Dragging ended (mouseleave)");
  }
});

// Send updated avatar position via AJAX (update_avatar.php)
function updateAvatarPosition(x, y) {
  const formData = new FormData();
  formData.append('x', x);
  formData.append('y', y);
  
  fetch('update_avatar.php', {
    method: 'POST',
    body: formData
  })
    .then(response => response.json())
    .then(data => {
      if (data.status !== 'success') {
        console.error('Error updating position:', data.message);
      }
    })
    .catch(err => console.error(err));
}
