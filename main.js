const socket = io();
const canvas = document.getElementById('chatCanvas');
const ctx = canvas.getContext('2d');

// Einfacher Avatar (als Rechteck) zur Demo
let avatar = {
  x: 100,
  y: 100,
  width: 50,
  height: 50,
  isDragging: false,
};

function drawAvatar() {
  ctx.clearRect(0, 0, canvas.width, canvas.height);
  ctx.fillStyle = 'blue';
  ctx.fillRect(avatar.x, avatar.y, avatar.width, avatar.height);
}

drawAvatar();

canvas.addEventListener('mousedown', (e) => {
  const rect = canvas.getBoundingClientRect();
  const mouseX = e.clientX - rect.left;
  const mouseY = e.clientY - rect.top;
  // Überprüfen, ob der Klick im Avatar-Bereich liegt
  if (
    mouseX > avatar.x && mouseX < avatar.x + avatar.width &&
    mouseY > avatar.y && mouseY < avatar.y + avatar.height
  ) {
    avatar.isDragging = true;
  }
});

canvas.addEventListener('mousemove', (e) => {
  if (avatar.isDragging) {
    const rect = canvas.getBoundingClientRect();
    avatar.x = e.clientX - rect.left - avatar.width / 2;
    avatar.y = e.clientY - rect.top - avatar.height / 2;
    drawAvatar();
    // Position des Avatars an andere Clients senden
    socket.emit('avatarMove', { x: avatar.x, y: avatar.y });
  }
});

canvas.addEventListener('mouseup', () => {
  avatar.isDragging = false;
});

// Empfang von Bewegungsdaten anderer Benutzer
socket.on('avatarMove', (data) => {
  // Hier können Sie Logik einbauen, um die Avatare anderer Benutzer anzuzeigen und zu aktualisieren.
  console.log('Avatar bewegt:', data);
});
