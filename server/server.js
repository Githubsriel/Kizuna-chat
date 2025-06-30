const express = require('express');
const http = require('http');
const socketIo = require('socket.io');

const app = express();
const server = http.createServer(app);
const io = socketIo(server);

// Statische Dateien aus dem "public"-Ordner bereitstellen
app.use(express.static('public'));

io.on('connection', (socket) => {
  console.log('Neuer Client verbunden:', socket.id);

  // Empfang und Weiterleitung von Avatar-Bewegungen
  socket.on('avatarMove', (data) => {
    socket.broadcast.emit('avatarMove', data);
  });

  socket.on('disconnect', () => {
    console.log('Client disconnected', socket.id);
  });
});

const port = process.env.PORT || 3000;
server.listen(port, () => {
  console.log(`Server läuft auf Port ${port}`);
});
