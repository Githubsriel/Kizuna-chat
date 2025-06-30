<?php
include 'main.php';

if (!isset($_SESSION['loggedin'])) {
    header('Location: ../index.php');
    exit;
}

if (isset($_GET['action'], $_GET['id'])) {
    $action = $_GET['action'];
    $request_id = (int)$_GET['id'];

    if ($action === "accept" || $action === "decline") {
        $message = respond_to_request($con, $request_id, $action);
        header("Location: ../profile.php?msg=" . urlencode($message));
        exit;
    }
}