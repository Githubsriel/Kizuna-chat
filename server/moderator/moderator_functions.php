<?php
// moderator_functions.php

function log_moderator_action($con, $moderator_id, $account_id, $action) {
    try {
        $stmt = $con->prepare('INSERT INTO moderation_logs 
                              (moderator_id, account_id, action) 
                              VALUES (?, ?, ?)');
        $stmt->bind_param('iis', $moderator_id, $account_id, $action);
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Moderation log error: " . $e->getMessage());
        return false;
    }
}

function get_moderation_logs($con, $limit = 50) {
    $stmt = $con->prepare('SELECT m.*, a1.username as moderator_name, a2.username as account_name
                          FROM moderation_logs m
                          JOIN accounts a1 ON m.moderator_id = a1.id
                          JOIN accounts a2 ON m.account_id = a2.id
                          ORDER BY m.created_at DESC
                          LIMIT ?');
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}