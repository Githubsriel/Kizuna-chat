<?php
include_once 'main.php';

$userExp = $_SESSION['exp'] ?? 0;
$progressData = getLevelProgress($userExp);

header('Content-Type: application/json');
echo json_encode($progressData);
?>
