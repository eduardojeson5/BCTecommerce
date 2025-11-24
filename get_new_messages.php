<?php
include 'INCLUDES/connect.php';
if (session_status() == PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$refund_id = (int)($_GET['refund_id'] ?? 0);
$last_check = (int)($_GET['last_check'] ?? 0);

header('Content-Type: application/json');

if ($refund_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid refund ID']);
    exit;
}

// Verify user owns this refund
$chk = $conn->prepare("SELECT id FROM refund WHERE id = ? AND user_id = ?");
$chk->execute([$refund_id, $user_id]);

if ($chk->rowCount() === 0) {
    echo json_encode(['success' => false, 'message' => 'Refund not found']);
    exit;
}

// Get new messages since last check (convert milliseconds to seconds)
$since_timestamp = $last_check > 0 ? $last_check / 1000 : 0;

$stmt = $conn->prepare("
    SELECT rc.*, 
           u.name as user_name,
           a.name as admin_name
    FROM refund_conversations rc
    LEFT JOIN users u ON rc.user_id = u.ID AND rc.is_admin_reply = 0
    LEFT JOIN admin a ON rc.admin_id = a.ID AND rc.is_admin_reply = 1
    WHERE rc.refund_id = ? AND UNIX_TIMESTAMP(rc.created_at) > ?
    ORDER BY rc.created_at ASC
");
$stmt->execute([$refund_id, $since_timestamp]);

$newMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format messages for frontend
$formattedMessages = [];
foreach ($newMessages as $message) {
    $formattedMessages[] = [
        'id' => $message['id'],
        'message' => $message['message'],
        'media_files' => $message['media_files'],
        'is_admin_reply' => $message['is_admin_reply'],
        'created_at' => $message['created_at'],
        'user_name' => $message['user_name'],
        'admin_name' => $message['admin_name']
    ];
}

echo json_encode([
    'success' => true,
    'newMessages' => $formattedMessages,
    'count' => count($formattedMessages)
]);
?>