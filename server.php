<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'server/main.php';
if (session_status() == PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'send_message':
        include 'send_message.php';
        break;
    case 'get_messages':
        include 'get_messages.php';
        break;
    case 'chat_activity_and_coins':
        include 'server/chat_activity_and_coins.php';
        break;
    case 'update_avatar':
        include 'update_avatar.php';
        break;
    case 'get_avatars':
        include 'get_avatars.php';
        break;
    case 'get_user_avatars':
        include 'get_user_avatars.php';
        break;
    case 'set_active_avatar':
        include 'set_active_avatar.php';
        break;
    case 'delete_avatar':
        include 'delete_avatar.php';
        break;
    case 'upload_avatar':
        include 'upload_avatar.php';
        break;
    case 'toggle_afk':
        include 'server/toggle_afk.php';
        break;
    case 'remove_avatar':
        include 'remove_avatar.php';
        break;
    case 'send_public_image':
        include 'server/send_public_image.php';
        break;
    case 'get_public_image':
        include 'get_public_image.php';
        break;
    case 'get_new_dms':
        include 'server/get_new_dms.php';
        break;
    case 'send_private_message':
        include 'server/send_private_message.php';
        break;
    case 'send_private_image':
        include 'server/send_private_image.php';
        break;
    case 'get_private_messages':
        include 'server/get_private_messages.php';
        break;
    case 'update_read_status':
        include 'server/update_read_status.php';
        break;
    case 'get_online_users':
        include 'server/get_online_users.php';
        break;
    case 'get_rooms':
        include 'server/get_rooms.php';
        break;
    case 'send_avatar_gift':
        include 'server/send_avatar_gift.php';
        break;
    case 'check_avatar_gifts':
        include 'server/check_avatar_gifts.php';
        break;
    case 'respond_avatar_gift':
        include 'server/respond_avatar_gift.php';
        break;
    case 'get_coin_balance':
        include 'server/get_coin_balance.php';
        break;
    case 'check_ban':
        include 'server/check_ban.php';
        break;
    case 'set_bubble_color':
        include 'server/set_bubble_color.php';
        break;
    case 'get_bubble_colors':
        include 'server/get_bubble_colors.php';
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
        break;
}
?>
