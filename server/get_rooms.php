<?php
include_once 'main.php';
// No additional session_start() call if main.php already handles it.

header('Content-Type: application/json');

$rooms = [];

$stmt = $con->prepare("
    SELECT id, name, page_background, canvas_background
    FROM rooms
    WHERE is_active = 1
    ORDER BY name ASC
");

if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();

    while ($room = $result->fetch_assoc()) {
        $rooms[] = [
            'id' => $room['id'],
            'name' => $room['name'],
            'page_background' => $room['page_background'] ?? null,
            'canvas_background' => $room['canvas_background'] ?? null
        ];
    }

    $stmt->close();
} else {
    error_log("DB Prepare Error (get_rooms): " . $con->error);
}

echo json_encode($rooms);
exit;
?>
