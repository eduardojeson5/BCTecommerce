<?php
include 'INCLUDES/connect.php';
if (session_status() == PHP_SESSION_NONE) session_start();

// require login
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$user_id = $_SESSION['user_id'];

/* Parse total_products into array of items */
function parseProductsFromTotal($total_products) {
    $items = [];
    if (empty($total_products)) return $items;
    
    $entries = array_filter(array_map('trim', explode(' - ', $total_products)));
    foreach ($entries as $entry) {
        if (empty($entry)) continue;
        $matches = [];
        if (preg_match('/(.*?)\s*\(?(\d+)?\s*x?\s*(\d+)?\)?$/', $entry, $matches)) {
            $name = trim($matches[1]);
            $qty = isset($matches[3]) ? (int)$matches[3] : (isset($matches[2]) ? (int)$matches[2] : 1);
            $items[] = ['raw_name' => $name, 'quantity' => $qty];
        } else {
            $items[] = ['raw_name' => trim($entry), 'quantity' => 1];
        }
    }
    return $items;
}

/* Find product by name in DB */
function findProductByName($conn, $raw_name) {
    if (trim($raw_name) === '') return null;
    $stmt = $conn->prepare("SELECT ID, name FROM products WHERE name LIKE ? LIMIT 1");
    $stmt->execute(["%$raw_name%"]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Helper function to generate message HTML
function generateMessageHTML($msg) {
    $messageClass = $msg['is_admin_reply'] ? 'admin-message' : 'user-message';
    $sender = $msg['is_admin_reply'] ? 
        ('Support Team' . ($msg['admin_name'] ? ' (' . $msg['admin_name'] . ')' : '')) : 
        'You';
    
    $html = '<div class="message '.$messageClass.'">';
    $html .= '<div class="message-sender">'.$sender.'</div>';
    
    // Display message text
    if(!empty(trim($msg['message']))) {
        $html .= '<div class="message-content">'.nl2br(htmlspecialchars($msg['message'])).'</div>';
    }
    
    // Display media files
    if(!empty($msg['media_files'])) {
        $media_files = json_decode($msg['media_files'], true);
        if(is_array($media_files) && count($media_files) > 0) {
            $html .= '<div class="media-attachments">';
            foreach($media_files as $file_data) {
                if (is_array($file_data)) {
                    $file_name = $file_data['name'] ?? 'file';
                    $file_path = $file_data['path'] ?? '';
                } else {
                    $file_name = basename($file_data);
                    $file_path = $file_data;
                }
                
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $is_image = in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                $is_video = in_array($file_ext, ['mp4', 'avi', 'mov', 'wmv', 'flv']);
                
                $html .= '<div class="media-attachment">';
                if($is_image) {
                    $html .= '<img src="uploads/refund_conversations/'.$file_path.'" 
                           alt="'.htmlspecialchars($file_name).'"
                           onclick="openMediaModal(\'uploads/refund_conversations/'.$file_path.'\', \'image\')">';
                    $html .= '<div style="font-size: 0.7rem; color: #666; margin-top: 5px;">'.htmlspecialchars($file_name).'</div>';
                } elseif($is_video) {
                    $html .= '<video controls>';
                    $html .= '<source src="uploads/refund_conversations/'.$file_path.'" type="video/'.$file_ext.'">';
                    $html .= 'Your browser does not support the video tag.';
                    $html .= '</video>';
                    $html .= '<div style="font-size: 0.7rem; color: #666; margin-top: 5px;">'.htmlspecialchars($file_name).'</div>';
                } else {
                    $html .= '<a href="uploads/refund_conversations/'.$file_path.'" 
                           download="'.htmlspecialchars($file_name).'"
                           class="file-download">';
                    $html .= '<i class="fas fa-download"></i>';
                    $html .= htmlspecialchars($file_name);
                    $html .= '</a>';
                }
                $html .= '</div>';
            }
            $html .= '</div>';
        }
    }
    
    $html .= '<div class="message-time">'.date('M d, Y h:i A', strtotime($msg['created_at'])).'</div>';
    $html .= '</div>';
    
    return $html;
}

// flash messages
$messages = $_SESSION['flash_messages'] ?? [];
unset($_SESSION['flash_messages']);

/* Handle refund submission - AJAX Version */
if (isset($_POST['submit_refund']) && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    $order_id = (int)($_POST['order_id'] ?? 0);
    $product_id = (int)($_POST['product_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    $response = ['success' => false, 'message' => ''];
    
    if (!$order_id || !$product_id || !$quantity || $reason === '') {
        $response['message'] = "Please fill all required fields!";
        echo json_encode($response);
        exit;
    }
    
    try {
        $s = $conn->prepare("SELECT ID, payment_status, total_products FROM orders WHERE ID = ? AND user_id = ?");
        $s->execute([$order_id, $user_id]);
        $order = $s->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            $response['message'] = "Order not found.";
        } elseif ($order['payment_status'] === 'pending') {
            $response['message'] = "Refund requests are not allowed for pending payment!";
        } else {
            $chk = $conn->prepare("SELECT * FROM refund WHERE order_id = ? AND product_id = ? AND user_id = ?");
            $chk->execute([$order_id, $product_id, $user_id]);
            if ($chk->rowCount() > 0) {
                $response['message'] = "You already submitted a refund request for this product!";
            } else {
                $parsed = parseProductsFromTotal($order['total_products']);
                $max_quantity = 0;
                foreach ($parsed as $p) {
                    $found = findProductByName($conn, $p['raw_name']);
                    if ($found && (int)$found['ID'] === $product_id) {
                        $max_quantity = $p['quantity'];
                        break;
                    }
                }
                if ($max_quantity === 0) {
                    $response['message'] = "Product not found in this order!";
                } elseif ($quantity > $max_quantity) {
                    $response['message'] = "You cannot refund more than $max_quantity item(s)!";
                } else {
                    $conn->beginTransaction();
                    try {
                        // Insert refund request
                        $ins = $conn->prepare("INSERT INTO refund (order_id, user_id, product_id, quantity, reason, status, requested_date) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
                        $ok = $ins->execute([$order_id, $user_id, $product_id, $quantity, $reason]);
                        
                        if ($ok) {
                            $refund_id = $conn->lastInsertId();
                            // Add initial message to conversation
                            $msg_ins = $conn->prepare("INSERT INTO refund_conversations (refund_id, user_id, message, is_admin_reply, created_at) VALUES (?, ?, ?, 0, NOW())");
                            $msg_ins->execute([$refund_id, $user_id, "Refund request submitted: " . $reason]);
                            
                            $conn->commit();
                            $response['success'] = true;
                            $response['message'] = "Refund request submitted successfully!";
                            $response['refresh'] = true;
                        } else {
                            $conn->rollBack();
                            $response['message'] = "Failed to submit refund request.";
                        }
                    } catch (Exception $e) {
                        $conn->rollBack();
                        $response['message'] = "Failed to submit refund request: " . $e->getMessage();
                    }
                }
            }
        }
    } catch (Exception $e) {
        $response['message'] = "Database error: " . $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}

// Keep original non-AJAX version as fallback
if (isset($_POST['submit_refund']) && !isset($_POST['ajax'])) {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $product_id = (int)($_POST['product_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    $flash = [];

    if (!$order_id || !$product_id || !$quantity || $reason === '') {
        $flash[] = "Please fill all required fields!";
    } else {
        $s = $conn->prepare("SELECT ID, payment_status, total_products FROM orders WHERE ID = ? AND user_id = ?");
        $s->execute([$order_id, $user_id]);
        $order = $s->fetch(PDO::FETCH_ASSOC);

        if (!$order) $flash[] = "Order not found.";
        elseif ($order['payment_status'] === 'pending') $flash[] = "Refund requests are not allowed for pending payment!";
        else {
            $chk = $conn->prepare("SELECT * FROM refund WHERE order_id = ? AND product_id = ? AND user_id = ?");
            $chk->execute([$order_id, $product_id, $user_id]);
            if ($chk->rowCount() > 0) $flash[] = "You already submitted a refund request for this product!";
            else {
                $parsed = parseProductsFromTotal($order['total_products']);
                $max_quantity = 0;
                foreach ($parsed as $p) {
                    $found = findProductByName($conn, $p['raw_name']);
                    if ($found && (int)$found['ID'] === $product_id) {
                        $max_quantity = $p['quantity'];
                        break;
                    }
                }
                if ($max_quantity === 0) $flash[] = "Product not found in this order!";
                elseif ($quantity > $max_quantity) $flash[] = "You cannot refund more than $max_quantity item(s)!";
                else {
                    $conn->beginTransaction();
                    try {
                        // Insert refund request
                        $ins = $conn->prepare("INSERT INTO refund (order_id, user_id, product_id, quantity, reason, status, requested_date) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
                        $ok = $ins->execute([$order_id, $user_id, $product_id, $quantity, $reason]);
                        
                        if ($ok) {
                            $refund_id = $conn->lastInsertId();
                            // Add initial message to conversation
                            $msg_ins = $conn->prepare("INSERT INTO refund_conversations (refund_id, user_id, message, is_admin_reply, created_at) VALUES (?, ?, ?, 0, NOW())");
                            $msg_ins->execute([$refund_id, $user_id, "Refund request submitted: " . $reason]);
                            
                            $conn->commit();
                            $flash[] = "Refund request submitted successfully!";
                        } else {
                            $conn->rollBack();
                            $flash[] = "Failed to submit refund request.";
                        }
                    } catch (Exception $e) {
                        $conn->rollBack();
                        $flash[] = "Failed to submit refund request: " . $e->getMessage();
                    }
                }
            }
        }
    }

    $_SESSION['flash_messages'] = $flash;
    header('Location: orders.php');
    exit;
}

/* Handle user message in refund conversation - AJAX Version */
if (isset($_POST['submit_refund_message']) && isset($_POST['ajax'])) {
    // Set header for JSON response
    header('Content-Type: application/json');
    
    $refund_id = (int)($_POST['refund_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    $response = ['success' => false, 'message' => '', 'html' => ''];
    
    // Handle file uploads
    $media_files = [];
    if (!empty($_FILES['media_files']['name'][0])) {
        $upload_dir = 'uploads/refund_conversations/';
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                $response['message'] = "Failed to create upload directory";
                echo json_encode($response);
                exit;
            }
        }
        
        foreach ($_FILES['media_files']['name'] as $index => $name) {
            if ($_FILES['media_files']['error'][$index] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['media_files']['tmp_name'][$index];
                $file_size = $_FILES['media_files']['size'][$index];
                $file_type = $_FILES['media_files']['type'][$index];
                
                // Validate file size (5MB max for images, 20MB for videos)
                $max_size = (strpos($file_type, 'video/') !== false) ? 20971520 : 5242880;
                if ($file_size > $max_size) {
                    $response['message'] = "File too large: " . $name . " (Max: " . ($max_size/1048576) . "MB)";
                    echo json_encode($response);
                    exit;
                }
                
                // Generate unique filename
                $file_ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $unique_name = uniqid() . '_' . time() . '.' . $file_ext;
                $file_path = $upload_dir . $unique_name;
                
                // Move uploaded file
                if (move_uploaded_file($file_tmp, $file_path)) {
                    $media_files[] = [
                        'name' => $name,
                        'path' => $unique_name
                    ];
                }
            }
        }
    }
    
    if ($refund_id > 0 && ($message !== '' || !empty($media_files))) {
        try {
            // Verify user owns this refund
            $chk = $conn->prepare("SELECT id FROM refund WHERE id = ? AND user_id = ?");
            $chk->execute([$refund_id, $user_id]);
            
            if ($chk->rowCount() > 0) {
                $media_files_json = !empty($media_files) ? json_encode($media_files) : null;
                $ins = $conn->prepare("INSERT INTO refund_conversations (refund_id, user_id, message, media_files, is_admin_reply, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
                
                if ($ins->execute([$refund_id, $user_id, $message, $media_files_json])) {
                    $response['success'] = true;
                    $response['message'] = "Message sent successfully!";
                    
                    // Get the newly inserted message to return HTML
                    $msg_id = $conn->lastInsertId();
                    $msg_stmt = $conn->prepare("
                        SELECT rc.*, u.name as user_name
                        FROM refund_conversations rc
                        LEFT JOIN users u ON rc.user_id = u.ID
                        WHERE rc.id = ?
                    ");
                    $msg_stmt->execute([$msg_id]);
                    $new_message = $msg_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($new_message) {
                        $response['html'] = generateMessageHTML($new_message);
                    }
                } else {
                    $errorInfo = $ins->errorInfo();
                    $response['message'] = "Database error: " . ($errorInfo[2] ?? 'Unknown error');
                }
            } else {
                $response['message'] = "Refund not found or you don't have permission.";
            }
        } catch (Exception $e) {
            $response['message'] = "Server error: " . $e->getMessage();
        }
    } else {
        if ($refund_id <= 0) {
            $response['message'] = "Invalid refund ID";
        } else {
            $response['message'] = "Please enter a message or attach files";
        }
    }
    
    echo json_encode($response);
    exit;
}

// Keep the original non-AJAX version as fallback
if (isset($_POST['submit_refund_message']) && !isset($_POST['ajax'])) {
    $refund_id = (int)($_POST['refund_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    
    // Handle file uploads
    $media_files = [];
    if (!empty($_FILES['media_files']['name'][0])) {
        $upload_dir = 'uploads/refund_conversations/';
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        foreach ($_FILES['media_files']['name'] as $index => $name) {
            if ($_FILES['media_files']['error'][$index] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['media_files']['tmp_name'][$index];
                $file_size = $_FILES['media_files']['size'][$index];
                $file_type = $_FILES['media_files']['type'][$index];
                
                // Validate file size (5MB max for images, 20MB for videos)
                $max_size = strpos($file_type, 'video/') !== false ? 20971520 : 5242880;
                if ($file_size > $max_size) {
                    $_SESSION['flash_messages'] = ["File too large: " . $name . " (Max: " . ($max_size/1048576) . "MB)"];
                    header('Location: orders.php');
                    exit;
                }
                
                // Generate unique filename
                $file_ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $unique_name = uniqid() . '_' . time() . '.' . $file_ext;
                $file_path = $upload_dir . $unique_name;
                
                // Move uploaded file
                if (move_uploaded_file($file_tmp, $file_path)) {
                    $media_files[] = [
                        'name' => $name,
                        'path' => $unique_name
                    ];
                }
            }
        }
    }
    
    if ($refund_id && ($message || !empty($media_files))) {
        // Verify user owns this refund
        $chk = $conn->prepare("SELECT id FROM refund WHERE id = ? AND user_id = ?");
        $chk->execute([$refund_id, $user_id]);
        
        if ($chk->rowCount() > 0) {
            $media_files_json = !empty($media_files) ? json_encode($media_files) : null;
            $ins = $conn->prepare("INSERT INTO refund_conversations (refund_id, user_id, message, media_files, is_admin_reply, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
            $ins->execute([$refund_id, $user_id, $message, $media_files_json]);
            $_SESSION['flash_messages'] = ["Message sent successfully!"];
        }
    } else {
        $_SESSION['flash_messages'] = ["Please enter a message or attach files"];
    }
    header('Location: orders.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Orders | Battlefront Computer Trading</title>

<!-- Bootstrap 5 CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<!-- Custom Styles -->
<link rel="stylesheet" href="css/styles.css">
<link rel="stylesheet" href="css/aboutStyles.css">

<style>
/* ======== PAGE HEADING ======== */
.heading {
    background-color: #222;
    color: #ffffff;
    padding: 3rem 1rem;
    margin-bottom: 2rem;
    text-align: center;
}
.heading a { 
    color: #ffffff; 
    text-decoration: underline; 
}
.heading span {
    color: #e2e6ea;
}

.refund-form { display:none; background:#f8f9fa; padding:15px; border-left:4px solid #dc3545; border-radius:8px; margin-top: 10px; }
.refund-status { margin-top:10px; padding:10px; border-radius:6px; }
.refund-pending { background:#fff3cd; }
.refund-approved { background:#d1ecf1; }
.refund-rejected { background:#f8d7da; }
.quantity-info { font-size:.9rem; color:#6c757d; margin-top:6px; }

/* Conversation Styles */
.conversation-section {
    border-top: 1px solid #dee2e6;
    padding-top: 15px;
    margin-top: 15px;
}

.conversation-container {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 15px;
    background: #f8f9fa;
    margin-bottom: 15px;
    transition: all 0.3s ease;
}

.conversation-container.hidden {
    max-height: 0;
    padding: 0;
    border: none;
    overflow: hidden;
    margin-bottom: 0;
}

.message {
    margin-bottom: 15px;
    padding: 12px 15px;
    border-radius: 10px;
    max-width: 85%;
    position: relative;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.admin-message {
    background: linear-gradient(135deg, #4361ee, #3a0ca3);
    color: white;
    margin-left: auto;
    border-bottom-right-radius: 2px;
}

.user-message {
    background: linear-gradient(135deg, #6c757d, #495057);
    color: white;
    margin-right: auto;
    border-bottom-left-radius: 2px;
}

.message-sender {
    font-weight: bold;
    font-size: 0.8rem;
    margin-bottom: 8px;
    opacity: 0.9;
}

.admin-message .message-sender {
    color: #e3f2fd;
}

.user-message .message-sender {
    color: #f5f5f5;
}

.message-content {
    margin: 8px 0;
    font-size: 0.9rem;
    line-height: 1.4;
}

.message-time {
    font-size: 0.7rem;
    opacity: 0.7;
    margin-top: 5px;
    text-align: right;
}

.conversation-form {
    margin-top: 15px;
    padding: 15px;
    background: white;
    border-radius: 10px;
    border: 1px solid #dee2e6;
    transition: all 0.3s ease;
}

.conversation-form.hidden {
    display: none;
}

.conversation-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #4361ee;
}

.conversation-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #4361ee;
    margin: 0;
}

/* Media Attachment Styles */
.media-attachment {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 8px;
    background: white;
    margin: 5px 0;
    max-width: 300px;
}

.media-attachment img,
.media-attachment video {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    max-width: 100%;
    border-radius: 4px;
}

.media-attachment img {
    cursor: pointer;
    transition: transform 0.2s ease;
}

.media-attachment img:hover {
    transform: scale(1.02);
}

.file-download {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 8px 12px;
    background: #4361ee;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-size: 0.8rem;
    transition: background 0.3s ease;
}

.file-download:hover {
    background: #3a0ca3;
    color: white;
}

.media-preview {
    margin-bottom: 10px;
    max-height: 100px;
    overflow-y: auto;
}

.media-preview-item {
    display: flex;
    align-items: center;
    margin: 5px 0;
    padding: 5px;
    background: #f8f9fa;
    border-radius: 5px;
}

.media-preview-item img {
    width: 40px;
    height: 40px;
    object-fit: cover;
    border-radius: 4px;
    margin-right: 10px;
}

.media-preview-item .file-icon {
    font-size: 20px;
    margin-right: 10px;
    color: #4361ee;
}

.media-preview-item .file-name {
    flex: 1;
    font-size: 12px;
}

.media-preview-item .remove-btn {
    background: none;
    border: none;
    color: #dc3545;
    cursor: pointer;
    padding: 2px 6px;
}

/* File Input Styling */
.file-input-wrapper {
    position: relative;
    display: inline-block;
}

.file-input-wrapper input[type="file"] {
    position: absolute;
    left: -9999px;
}

.file-input-btn {
    background: #6c757d;
    color: white;
    border: none;
    padding: 8px 12px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.8rem;
    transition: background 0.3s ease;
}

.file-input-btn:hover {
    background: #5a6268;
}

/* Scrollbar styling */
.conversation-container::-webkit-scrollbar {
    width: 8px;
}

.conversation-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.conversation-container::-webkit-scrollbar-thumb {
    background: #4361ee;
    border-radius: 4px;
}

.conversation-container::-webkit-scrollbar-thumb:hover {
    background: #3a0ca3;
}

/* Custom button colors */
.review-btn {
    background-color: #ffc107 !important;
    color: #212529 !important;
    border: none;
    padding: 8px 12px;
    border-radius: 5px;
    font-size: 14px;
    text-decoration: none;
    display: inline-block;
    text-align: center;
    transition: background-color 0.3s ease;
    margin: 2px;
    font-weight: 500;
}

.review-btn:hover {
    background-color: #e0a800 !important;
    color: #212529 !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.refund-btn {
    background-color: #dc3545 !important;
    color: white !important;
    border: none;
    padding: 8px 12px;
    border-radius: 5px;
    font-size: 14px;
    transition: background-color 0.3s ease;
    margin: 2px;
}

.refund-btn:hover {
    background-color: #c82333 !important;
    color: white !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.reviewed-btn {
    background-color: #6c757d !important;
    color: white !important;
    border: none;
    padding: 8px 12px;
    border-radius: 5px;
    font-size: 14px;
    text-decoration: none;
    display: inline-block;
    text-align: center;
    transition: background-color 0.3s ease;
    margin: 2px;
}

.reviewed-btn:hover {
    background-color: #5a6268 !important;
    color: white !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.product-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    margin: 5px 0;
}

.badge {
    margin-left: 5px;
}

.conversation-toggle {
    background: #4361ee;
    border: none;
    color: white;
    cursor: pointer;
    font-size: 0.8rem;
    padding: 6px 12px;
    border-radius: 5px;
    margin-top: 5px;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.conversation-toggle:hover {
    background: #3a0ca3;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.conversation-toggle i {
    font-size: 0.7rem;
}

.no-conversation {
    text-align: center;
    padding: 30px 20px;
    color: #6c757d;
    transition: all 0.3s ease;
}

.no-conversation.hidden {
    max-height: 0;
    padding: 0;
    overflow: hidden;
}

.no-conversation i {
    font-size: 3rem;
    margin-bottom: 15px;
    opacity: 0.5;
}

.conversation-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.conversation-badge {
    background: #28a745;
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

/* Media Modal */
.media-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
}

.media-modal-content {
    max-width: 90%;
    max-height: 90%;
    position: relative;
}

.media-modal-content img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.media-modal-close {
    position: absolute;
    top: -40px;
    right: 0;
    background: none;
    border: none;
    color: white;
    font-size: 24px;
    cursor: pointer;
}

/* New message notification */
.new-message-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #28a745;
    color: white;
    padding: 10px 15px;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    z-index: 10001;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
</style>
</head>
<body>

<?php include 'INCLUDES/user_header.php'; ?>

<!-- Page Heading -->
<div class="heading">
    <h2 class="fw-bold">My Orders</h2>
    <p>
        <a href="index.php" class="text-light text-decoration-underline">Home</a> 
        <span class="text-secondary"> / My Orders</span>
    </p>
</div>

<div class="container mb-5">
  <?php if(!empty($messages)): foreach($messages as $m): ?>
    <div class="alert <?= (stripos($m,'not')!==false || stripos($m,'failed')!==false || stripos($m,'cannot')!==false || stripos($m,'error')!==false) ? 'alert-danger':'alert-success' ?> alert-dismissible fade show">
      <?= htmlspecialchars($m) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endforeach; endif; ?>

  <?php
  try {
      // FIXED: Proper ordering - newest orders first
      $stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY placed_on DESC, ID DESC");
      $stmt->execute([$user_id]);

      if ($stmt->rowCount() == 0) {
          echo '<div class="text-center py-5 text-muted">No orders placed yet!</div>';
      } else {
          echo '<div class="row g-4">';
          while($order = $stmt->fetch(PDO::FETCH_ASSOC)) {
              $order_id = $order['ID'];
              $parsed = parseProductsFromTotal($order['total_products']);
              $order_products = [];
              
              foreach ($parsed as $p) {
                  $found = findProductByName($conn, $p['raw_name']);
                  $order_products[] = [
                      'id' => $found['ID'] ?? 0,
                      'name' => $found['name'] ?? $p['raw_name'],
                      'quantity' => $p['quantity']
                  ];
              }
              
              $rstmt = $conn->prepare("SELECT r.*, p.name AS product_name FROM refund r LEFT JOIN products p ON r.product_id = p.ID WHERE r.order_id = ? AND r.user_id = ?");
              $rstmt->execute([$order_id,$user_id]);
              $refunds = $rstmt->fetchAll(PDO::FETCH_ASSOC);
              $has_refund = count($refunds) > 0;

              echo '<div class="col-md-6 col-lg-4"><div class="card shadow-sm '.($has_refund?'border-danger':'').'"><div class="card-body">';
              echo '<h5 class="card-title mb-2">Order #'.htmlspecialchars($order_id).'</h5>';
              echo '<p class="mb-1"><strong>Placed on:</strong> '.htmlspecialchars($order['placed_on']).'</p>';
              echo '<p class="mb-1"><strong>Total price:</strong> ₱'.number_format($order['total_price'], 2).'</p>';
              echo '<p class="mb-1"><strong>Payment status:</strong> <span class="'.($order['payment_status']=='pending'?'text-danger':'text-success').'">'.htmlspecialchars($order['payment_status']).'</span></p>';
              echo '<p class="mb-1"><strong>Your orders:</strong> '.htmlspecialchars($order['total_products']).'</p>';
              
              echo '<div class="mt-3">';
              echo '<h6>Products:</h6>';
              foreach($order_products as $op) {
                  echo '<div class="product-buttons">';
                  if($op['id'] > 0) {
                      $chk = $conn->prepare("SELECT * FROM reviews WHERE product_id = ? AND user_id = ? LIMIT 1");
                      $chk->execute([$op['id'],$user_id]);
                      
                      if($chk->rowCount() > 0) {
                          echo '<a href="quick_view.php?pid='.$op['id'].'" class="btn reviewed-btn"><i class="fas fa-eye me-1"></i>View Review</a>';
                      } else {
                          echo '<a href="quick_view.php?pid='.$op['id'].'" class="btn review-btn"><i class="fas fa-star me-1"></i>Write Review</a>';
                      }
                      echo '<span class="badge bg-secondary">'.htmlspecialchars($op['quantity']).'x</span>';
                  } else {
                      echo '<span class="text-muted">'.htmlspecialchars($op['name']).'</span>';
                      echo '<span class="badge bg-secondary">'.htmlspecialchars($op['quantity']).'x</span>';
                  }
                  echo '</div>';
              }
              
              // Refund button - show only if payment is not pending and no refund exists
              if($order['payment_status'] !== 'pending' && !$has_refund) {
                  echo '<div class="mt-2">';
                  echo '<button class="btn refund-btn" onclick="toggleRefundForm('.$order_id.')"><i class="fas fa-undo me-1"></i>Request Refund</button>';
                  echo '</div>';
              }
              echo '</div>';

              // Refund form - AJAX Version
              if(!$has_refund) {
                  echo '<div id="refund-form-'.$order_id.'" class="refund-form">';
                  echo '<form method="post" id="refundForm-'.$order_id.'" onsubmit="return submitRefund('.$order_id.')">';
                  echo '<input type="hidden" name="order_id" value="'.htmlspecialchars($order_id).'">';
                  echo '<input type="hidden" name="ajax" value="1">';
                  echo '<input type="hidden" name="submit_refund" value="1">';
                  echo '<div class="mb-2"><label class="form-label">Select Product</label><select name="product_id" class="form-select" onchange="onProductChange(this, '.$order_id.')" required><option value="">-- Select a product --</option>';
                  foreach($order_products as $op) {
                      if($op['id'] > 0) {
                          echo '<option value="'.$op['id'].'" data-qty="'.$op['quantity'].'">'.htmlspecialchars($op['name']).' (Purchased: '.$op['quantity'].')</option>';
                      } else {
                          echo '<option disabled>'.htmlspecialchars($op['name']).' (Purchased: '.$op['quantity'].') - Product not found</option>';
                      }
                  }
                  echo '</select></div>';
                  echo '<div class="mb-2"><label class="form-label">Quantity to Refund</label><input type="number" name="quantity" id="quantity-'.$order_id.'" class="form-control" min="1" value="1" required>';
                  echo '<div class="quantity-info" id="qty-info-'.$order_id.'">Maximum refund quantity: <span class="text-danger">-</span></div></div>';
                  echo '<div class="mb-2"><label class="form-label">Reason</label><textarea name="reason" class="form-control" rows="3" required></textarea></div>';
                  echo '<div class="d-flex gap-2">';
                  echo '<button type="submit" name="submit_refund" class="btn btn-success" id="submitRefundBtn-'.$order_id.'">Submit Refund</button>';
                  echo '<button type="button" class="btn btn-secondary" onclick="toggleRefundForm('.$order_id.')">Cancel</button>';
                  echo '</div>';
                  echo '<div id="refundStatus-'.$order_id.'" class="mt-2"></div>';
                  echo '</form></div>';
              } else {
                  echo '<div class="mt-3"><h6>Refund Requests</h6>';
                  foreach($refunds as $rf) {
                      $refund_id = isset($rf['id']) ? $rf['id'] : (isset($rf['ID']) ? $rf['ID'] : 0);
                      
                      if ($refund_id > 0) {
                          $statusClass = 'refund-pending';
                          if(isset($rf['status'])) {
                              if($rf['status']==='approved') $statusClass='refund-approved';
                              elseif($rf['status']==='rejected') $statusClass='refund-rejected';
                          }
                          
                          echo '<div class="refund-status '.$statusClass.'">';
                          echo '<strong>'.htmlspecialchars($rf['product_name'] ?: 'Product #'.($rf['product_id'] ?? '')).'</strong><br>';
                          echo 'Status: '.ucfirst(htmlspecialchars($rf['status'] ?? 'pending')).'<br>';
                          echo 'Qty: '.(int)($rf['quantity'] ?? 0).'<br>';
                          echo 'Reason: '.htmlspecialchars($rf['reason'] ?? '').'<br>';
                          echo 'Requested: '.htmlspecialchars(date('M d, Y h:i A', strtotime($rf['requested_date'] ?? 'now'))).'<br>';
                          
                          // Get conversation messages
                          $msg_stmt = $conn->prepare("
                              SELECT rc.*, 
                                     u.name as user_name,
                                     a.name as admin_name
                              FROM refund_conversations rc
                              LEFT JOIN users u ON rc.user_id = u.ID AND rc.is_admin_reply = 0
                              LEFT JOIN admin a ON rc.admin_id = a.ID AND rc.is_admin_reply = 1
                              WHERE rc.refund_id = ?
                              ORDER BY rc.created_at ASC
                          ");
                          $msg_stmt->execute([$refund_id]);
                          $messages = $msg_stmt->fetchAll(PDO::FETCH_ASSOC);
                          
                          echo '<div class="conversation-section">';
                          echo '<div class="conversation-header">';
                          echo '<h6 class="conversation-title"><i class="fas fa-comments me-2"></i>Conversation with Support</h6>';
                          echo '<div class="conversation-actions">';
                          echo '<span class="conversation-badge">'.count($messages).' messages</span>';
                          echo '<button type="button" class="conversation-toggle" onclick="toggleConversation('.$refund_id.')" id="toggle-'.$refund_id.'">';
                          echo '<i class="fas fa-eye-slash"></i> Hide';
                          echo '</button>';
                          echo '</div>';
                          echo '</div>';
                          
                          if (count($messages) > 0) {
                              echo '<div class="conversation-container" id="conversation-'.$refund_id.'">';
                              
                              foreach ($messages as $msg) {
                                  $messageClass = $msg['is_admin_reply'] ? 'admin-message' : 'user-message';
                                  $sender = $msg['is_admin_reply'] ? 
                                      ('Support Team' . ($msg['admin_name'] ? ' (' . $msg['admin_name'] . ')' : '')) : 
                                      'You';
                                  
                                  echo '<div class="message '.$messageClass.'">';
                                  echo '<div class="message-sender">'.$sender.'</div>';
                                  
                                  // Display message text
                                  if(!empty(trim($msg['message']))) {
                                      echo '<div class="message-content">'.nl2br(htmlspecialchars($msg['message'])).'</div>';
                                  }
                                  
                                  // Display media files
                                  if(!empty($msg['media_files'])) {
                                      $media_files = json_decode($msg['media_files'], true);
                                      if(is_array($media_files) && count($media_files) > 0) {
                                          echo '<div class="media-attachments">';
                                          foreach($media_files as $file_data) {
                                              if (is_array($file_data)) {
                                                  $file_name = $file_data['name'] ?? 'file';
                                                  $file_path = $file_data['path'] ?? '';
                                              } else {
                                                  $file_name = basename($file_data);
                                                  $file_path = $file_data;
                                              }
                                              
                                              $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                                              $is_image = in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                              $is_video = in_array($file_ext, ['mp4', 'avi', 'mov', 'wmv', 'flv']);
                                              
                                              echo '<div class="media-attachment">';
                                              if($is_image) {
                                                  echo '<img src="uploads/refund_conversations/'.$file_path.'" 
                                                       alt="'.htmlspecialchars($file_name).'"
                                                       onclick="openMediaModal(\'uploads/refund_conversations/'.$file_path.'\', \'image\')">';
                                                  echo '<div style="font-size: 0.7rem; color: #666; margin-top: 5px;">'.htmlspecialchars($file_name).'</div>';
                                              } elseif($is_video) {
                                                  echo '<video controls>';
                                                      echo '<source src="uploads/refund_conversations/'.$file_path.'" type="video/'.$file_ext.'">';
                                                      echo 'Your browser does not support the video tag.';
                                                  echo '</video>';
                                                  echo '<div style="font-size: 0.7rem; color: #666; margin-top: 5px;">'.htmlspecialchars($file_name).'</div>';
                                              } else {
                                                  echo '<a href="uploads/refund_conversations/'.$file_path.'" 
                                                       download="'.htmlspecialchars($file_name).'"
                                                       class="file-download">';
                                                  echo '<i class="fas fa-download"></i>';
                                                  echo htmlspecialchars($file_name);
                                                  echo '</a>';
                                              }
                                              echo '</div>';
                                          }
                                          echo '</div>';
                                      }
                                  }
                                  
                                  echo '<div class="message-time">'.date('M d, Y h:i A', strtotime($msg['created_at'])).'</div>';
                                  echo '</div>';
                              }
                              echo '</div>'; // Close conversation-container
                          } else {
                              echo '<div class="no-conversation" id="conversation-'.$refund_id.'">';
                              echo '<i class="fas fa-comments"></i>';
                              echo '<p>No messages yet. Start the conversation with our support team!</p>';
                              echo '</div>';
                          }
                          
                          // Add message form for customer with file upload - AJAX Version
                          echo '<div class="conversation-form" id="form-'.$refund_id.'">';
                          echo '<form method="post" enctype="multipart/form-data" id="messageForm-'.$refund_id.'" onsubmit="return sendMessage('.$refund_id.')">';
                          echo '<input type="hidden" name="refund_id" value="'.$refund_id.'">';
                          echo '<input type="hidden" name="ajax" value="1">';
                          echo '<input type="hidden" name="submit_refund_message" value="1">';
                          echo '<div class="mb-3">';
                          echo '<label class="form-label fw-bold">Send a message to support:</label>';
                          echo '<textarea name="message" id="message-'.$refund_id.'" class="form-control" rows="3" placeholder="Type your message here..." required></textarea>';
                          echo '<div class="form-text">You can also attach images or videos to help explain your issue.</div>';
                          echo '</div>';
                          
                          // File attachment section
                          echo '<div class="mb-3">';
                          echo '<label class="form-label fw-bold">Attach Files (Optional):</label>';
                          echo '<div class="file-input-wrapper">';
                          echo '<input type="file" id="mediaFiles-'.$refund_id.'" name="media_files[]" multiple 
                                 accept="image/*,video/*" 
                                 style="display: none;" 
                                 onchange="previewFiles('.$refund_id.')">';
                          echo '<button type="button" class="file-input-btn" onclick="document.getElementById(\'mediaFiles-'.$refund_id.'\').click()">';
                          echo '<i class="fas fa-paperclip me-2"></i>Attach Files';
                          echo '</button>';
                          echo '</div>';
                          echo '<small class="form-text text-muted">Images (5MB max) • Videos (20MB max)</small>';
                          echo '</div>';
                          
                          // File preview area
                          echo '<div id="filePreview-'.$refund_id.'" class="media-preview"></div>';
                          
                          echo '<button type="submit" name="submit_refund_message" class="btn btn-primary" id="submitBtn-'.$refund_id.'">';
                          echo '<i class="fas fa-paper-plane me-2"></i>Send Message';
                          echo '</button>';
                          echo '<div id="messageStatus-'.$refund_id.'" class="mt-2"></div>';
                          echo '</form>';
                          echo '</div>'; // Close conversation-form
                          
                          echo '</div>'; // Close conversation-section
                          echo '</div>'; // Close refund-status
                      }
                  }
                  echo '</div>';
              }

              echo '</div></div></div>';
          }
          echo '</div>';
      }
  } catch (Exception $e) {
      echo '<div class="alert alert-danger">Error loading orders: '.htmlspecialchars($e->getMessage()).'</div>';
  }
  ?>
</div>

<?php include 'INCLUDES/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Store last check times for each conversation
const lastMessageChecks = {};

function toggleRefundForm(orderId) {
    const el = document.getElementById('refund-form-' + orderId);
    if (!el) return;
    el.style.display = (el.style.display === 'block' ? 'none' : 'block');
    if(el.style.display === 'block') {
        el.scrollIntoView({behavior:'smooth', block:'center'});
    }
}

function onProductChange(selectEl, orderId) {
    const opt = selectEl.options[selectEl.selectedIndex];
    const qtyInput = document.getElementById('quantity-'+orderId);
    const info = document.getElementById('qty-info-'+orderId);
    if(!opt || !qtyInput || !info) return;
    const maxQty = parseInt(opt.getAttribute('data-qty')) || 1;
    qtyInput.max = maxQty;
    if(parseInt(qtyInput.value) > maxQty) qtyInput.value = maxQty;
    info.innerHTML = 'Maximum refund quantity: <span class="text-danger">' + maxQty + '</span>';
}

// AJAX function to submit refund requests (without page refresh)
function submitRefund(orderId) {
    const form = document.getElementById('refundForm-' + orderId);
    const submitBtn = document.getElementById('submitRefundBtn-' + orderId);
    const statusDiv = document.getElementById('refundStatus-' + orderId);
    const refundForm = document.getElementById('refund-form-' + orderId);
    
    if (!form || !submitBtn) {
        console.error('Form or submit button not found for orderId:', orderId);
        return false;
    }
    
    // Show loading state
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
    submitBtn.disabled = true;
    statusDiv.innerHTML = '';
    
    // Create FormData object
    const formData = new FormData(form);
    
    // Send AJAX request
    fetch('orders.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Show success message
            statusDiv.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';
            
            // Hide the refund form since request was submitted
            if (refundForm) {
                refundForm.style.display = 'none';
            }
            
            // Refresh page after success
            if (data.refresh) {
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            }
        } else {
            // Show error message
            statusDiv.innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        statusDiv.innerHTML = '<div class="alert alert-danger">An error occurred while submitting the refund request. Please try again.</div>';
    })
    .finally(() => {
        // Restore button state for errors (success will handle differently)
        if (!statusDiv.innerHTML.includes('alert-success')) {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
        
        // Auto-hide error messages after 5 seconds
        setTimeout(() => {
            if (statusDiv.innerHTML.includes('alert-danger')) {
                statusDiv.innerHTML = '';
            }
        }, 5000);
    });
    
    return false; // Prevent default form submission
}

// File preview functionality
function previewFiles(refundId) {
    const fileInput = document.getElementById('mediaFiles-' + refundId);
    const preview = document.getElementById('filePreview-' + refundId);
    preview.innerHTML = '';
    
    if (fileInput.files.length > 0) {
        Array.from(fileInput.files).forEach(file => {
            const fileExt = file.name.split('.').pop().toLowerCase();
            const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileExt);
            const isVideo = ['mp4', 'avi', 'mov', 'wmv', 'flv'].includes(fileExt);
            
            const previewItem = document.createElement('div');
            previewItem.className = 'media-preview-item';
            
            if (isImage) {
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                previewItem.appendChild(img);
            } else if (isVideo) {
                const videoIcon = document.createElement('i');
                videoIcon.className = 'fas fa-video file-icon';
                previewItem.appendChild(videoIcon);
            } else {
                const fileIcon = document.createElement('i');
                fileIcon.className = 'fas fa-file file-icon';
                fileIcon.style.color = '#4361ee';
                previewItem.appendChild(fileIcon);
            }
            
            const fileName = document.createElement('span');
            fileName.className = 'file-name';
            fileName.textContent = file.name;
            previewItem.appendChild(fileName);
            
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'remove-btn';
            removeBtn.innerHTML = '<i class="fas fa-times"></i>';
            removeBtn.onclick = function() {
                previewItem.remove();
            };
            previewItem.appendChild(removeBtn);
            
            preview.appendChild(previewItem);
        });
    }
}

// Media modal for full-size images
function openMediaModal(src, type) {
    if (type !== 'image') return;
    
    const modal = document.createElement('div');
    modal.className = 'media-modal';
    modal.onclick = function(e) {
        if (e.target === modal) {
            document.body.removeChild(modal);
        }
    };
    
    const content = document.createElement('div');
    content.className = 'media-modal-content';
    
    const img = document.createElement('img');
    img.src = src;
    content.appendChild(img);
    
    const closeBtn = document.createElement('button');
    closeBtn.className = 'media-modal-close';
    closeBtn.innerHTML = '<i class="fas fa-times"></i>';
    closeBtn.onclick = function() {
        document.body.removeChild(modal);
    };
    content.appendChild(closeBtn);
    
    modal.appendChild(content);
    document.body.appendChild(modal);
}

// Toggle conversation visibility with persistent state
function toggleConversation(refundId) {
    const container = document.getElementById('conversation-' + refundId);
    const form = document.getElementById('form-' + refundId);
    const toggleBtn = document.getElementById('toggle-' + refundId);
    
    if (container && form && toggleBtn) {
        const isHidden = container.classList.contains('hidden');
        
        if (isHidden) {
            // Show conversation
            container.classList.remove('hidden');
            form.classList.remove('hidden');
            toggleBtn.innerHTML = '<i class="fas fa-eye-slash"></i> Hide';
            toggleBtn.style.background = '#4361ee';
            
            // Save state to localStorage
            localStorage.setItem('conversation-' + refundId, 'visible');
            
            // Initialize last check time
            lastMessageChecks[refundId] = Date.now();
            
            // Scroll to bottom of conversation
            setTimeout(() => {
                if (container.scrollHeight > container.clientHeight) {
                    container.scrollTop = container.scrollHeight;
                }
            }, 100);
        } else {
            // Hide conversation
            container.classList.add('hidden');
            form.classList.add('hidden');
            toggleBtn.innerHTML = '<i class="fas fa-eye"></i> Show';
            toggleBtn.style.background = '#28a745';
            
            // Save state to localStorage
            localStorage.setItem('conversation-' + refundId, 'hidden');
        }
    }
}

// Restore conversation states from localStorage on page load
function restoreConversationStates() {
    const containers = document.querySelectorAll('[id^="conversation-"]');
    containers.forEach(container => {
        const refundId = container.id.replace('conversation-', '');
        const toggleBtn = document.getElementById('toggle-' + refundId);
        const form = document.getElementById('form-' + refundId);
        const savedState = localStorage.getItem('conversation-' + refundId);
        
        if (savedState === 'hidden' && container && form && toggleBtn) {
            container.classList.add('hidden');
            form.classList.add('hidden');
            toggleBtn.innerHTML = '<i class="fas fa-eye"></i> Show';
            toggleBtn.style.background = '#28a745';
        } else if (savedState === 'visible' && container && form && toggleBtn) {
            container.classList.remove('hidden');
            form.classList.remove('hidden');
            toggleBtn.innerHTML = '<i class="fas fa-eye-slash"></i> Hide';
            toggleBtn.style.background = '#4361ee';
            
            // Initialize last check time for visible conversations
            lastMessageChecks[refundId] = Date.now();
        }
    });
}

// AJAX function to send messages without page refresh
function sendMessage(refundId) {
    const form = document.getElementById('messageForm-' + refundId);
    const submitBtn = document.getElementById('submitBtn-' + refundId);
    const statusDiv = document.getElementById('messageStatus-' + refundId);
    const conversationContainer = document.getElementById('conversation-' + refundId);
    
    if (!form || !submitBtn) {
        console.error('Form or submit button not found for refundId:', refundId);
        return false;
    }
    
    // Show loading state
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
    submitBtn.disabled = true;
    statusDiv.innerHTML = '';
    
    // Create FormData object
    const formData = new FormData(form);
    
    // Send AJAX request
    fetch('orders.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Success - add new message to conversation
            if (data.html && conversationContainer) {
                // If it's a no-conversation container, convert it to conversation container
                if (conversationContainer.classList.contains('no-conversation')) {
                    conversationContainer.classList.remove('no-conversation');
                    conversationContainer.innerHTML = data.html;
                } else {
                    conversationContainer.innerHTML += data.html;
                }
                
                // Scroll to bottom
                conversationContainer.scrollTop = conversationContainer.scrollHeight;
                
                // Add animation to new message
                const newMessage = conversationContainer.lastElementChild;
                if (newMessage) {
                    newMessage.style.opacity = '0';
                    newMessage.style.transform = 'translateY(20px)';
                    setTimeout(() => {
                        newMessage.style.transition = 'all 0.5s ease';
                        newMessage.style.opacity = '1';
                        newMessage.style.transform = 'translateY(0)';
                    }, 100);
                }
            }
            
            // Clear form
            form.reset();
            document.getElementById('filePreview-' + refundId).innerHTML = '';
            
            // Clear status (no success message needed since message appears instantly)
            statusDiv.innerHTML = '';
                
            // Update last check time
            lastMessageChecks[refundId] = Date.now();
        } else {
            // Error - still show error messages
            statusDiv.innerHTML = '<div class="alert alert-danger">' + (data.message || 'Unknown error occurred') + '</div>';
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        statusDiv.innerHTML = '<div class="alert alert-danger">An error occurred while sending the message. Please try again.</div>';
    })
    .finally(() => {
        // Restore button state
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        
        // Auto-hide error messages after 5 seconds
        setTimeout(() => {
            statusDiv.innerHTML = '';
        }, 5000);
    });
    
    return false; // Prevent default form submission
}

// Check for new admin messages using AJAX
function checkForNewMessages() {
    // Get all visible refund conversations
    const visibleRefundIds = [];
    document.querySelectorAll('[id^="conversation-"]').forEach(container => {
        if (!container.classList.contains('hidden') && !container.classList.contains('no-conversation')) {
            const refundId = container.id.replace('conversation-', '');
            visibleRefundIds.push(refundId);
        }
    });
    
    if (visibleRefundIds.length === 0) return;
    
    // Check for new messages for each visible conversation
    visibleRefundIds.forEach(refundId => {
        const lastCheck = lastMessageChecks[refundId] || Date.now() - 35000; // Default to 35 seconds ago
        
        fetch(`get_new_messages.php?refund_id=${refundId}&last_check=${lastCheck}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.newMessages && data.newMessages.length > 0) {
                    // Add new messages to conversation
                    data.newMessages.forEach(message => {
                        addNewMessageToConversation(refundId, message);
                    });
                    
                    // Show notification
                    showNewMessageNotification(refundId, data.newMessages.length);
                    
                    // Update last check time
                    lastMessageChecks[refundId] = Date.now();
                }
            })
            .catch(error => console.error('Error checking new messages for refund ' + refundId + ':', error));
    });
}

function addNewMessageToConversation(refundId, message) {
    const container = document.getElementById('conversation-' + refundId);
    if (!container) return;
    
    const messageHTML = generateMessageHTML(message);
    container.innerHTML += messageHTML;
    
    // Scroll to bottom if user is near the bottom
    const isNearBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 100;
    if (isNearBottom) {
        container.scrollTop = container.scrollHeight;
    }
    
    // Add animation
    const newMessage = container.lastElementChild;
    if (newMessage) {
        newMessage.style.opacity = '0';
        newMessage.style.transform = 'translateY(20px)';
        setTimeout(() => {
            newMessage.style.transition = 'all 0.5s ease';
            newMessage.style.opacity = '1';
            newMessage.style.transform = 'translateY(0)';
        }, 100);
    }
    
    // Update message count badge
    updateMessageCountBadge(refundId);
}

function generateMessageHTML(message) {
    const messageClass = message.is_admin_reply ? 'admin-message' : 'user-message';
    const sender = message.is_admin_reply ? 
        ('Support Team' + (message.admin_name ? ' (' + message.admin_name + ')' : '')) : 
        'You';
    
    let html = `<div class="message ${messageClass}">`;
    html += `<div class="message-sender">${sender}</div>`;
    
    if (message.message && message.message.trim() !== '') {
        html += `<div class="message-content">${message.message.replace(/\n/g, '<br>')}</div>`;
    }
    
    // Add media files if any
    if (message.media_files) {
        const media_files = JSON.parse(message.media_files);
        if (Array.isArray(media_files) && media_files.length > 0) {
            html += '<div class="media-attachments">';
            media_files.forEach(file_data => {
                const file_name = file_data.name || 'file';
                const file_path = file_data.path || file_data;
                const file_ext = file_name.split('.').pop().toLowerCase();
                const is_image = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(file_ext);
                const is_video = ['mp4', 'avi', 'mov', 'wmv', 'flv'].includes(file_ext);
                
                html += '<div class="media-attachment">';
                if (is_image) {
                    html += `<img src="uploads/refund_conversations/${file_path}" alt="${file_name}" onclick="openMediaModal('uploads/refund_conversations/${file_path}', 'image')">`;
                    html += `<div style="font-size: 0.7rem; color: #666; margin-top: 5px;">${file_name}</div>`;
                } else if (is_video) {
                    html += `<video controls><source src="uploads/refund_conversations/${file_path}" type="video/${file_ext}">Your browser does not support the video tag.</video>`;
                    html += `<div style="font-size: 0.7rem; color: #666; margin-top: 5px;">${file_name}</div>`;
                } else {
                    html += `<a href="uploads/refund_conversations/${file_path}" download="${file_name}" class="file-download">`;
                    html += `<i class="fas fa-download"></i>${file_name}`;
                    html += `</a>`;
                }
                html += '</div>';
            });
            html += '</div>';
        }
    }
    
    const messageTime = new Date(message.created_at).toLocaleString('en-US', { 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric',
        hour: 'numeric', 
        minute: 'numeric',
        hour12: true 
    });
    
    html += `<div class="message-time">${messageTime}</div>`;
    html += `</div>`;
    
    return html;
}

function showNewMessageNotification(refundId, count) {
    // Create notification
    const notification = document.createElement('div');
    notification.className = 'new-message-notification';
    notification.innerHTML = `<i class="fas fa-comment me-2"></i>${count} new message(s) in refund conversation`;
    
    document.body.appendChild(notification);
    
    // Remove notification after 3 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 3000);
}

function updateMessageCountBadge(refundId) {
    const badge = document.querySelector(`#toggle-${refundId} + .conversation-badge`);
    if (badge) {
        const currentCount = parseInt(badge.textContent) || 0;
        badge.textContent = currentCount + 1 + ' messages';
    }
}

// Auto-scroll conversation containers to bottom and add smooth animations
document.addEventListener('DOMContentLoaded', function() {
    // Restore conversation states first
    restoreConversationStates();
    
    // Then auto-scroll visible conversations
    const containers = document.querySelectorAll('.conversation-container:not(.hidden)');
    containers.forEach(container => {
        container.scrollTop = container.scrollHeight;
        
        // Add animation to messages
        const messages = container.querySelectorAll('.message');
        messages.forEach((message, index) => {
            message.style.opacity = '0';
            message.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                message.style.transition = 'all 0.5s ease';
                message.style.opacity = '1';
                message.style.transform = 'translateY(0)';
            }, index * 100);
        });
        
        // Initialize last check time for visible conversations
        const refundId = container.id.replace('conversation-', '');
        lastMessageChecks[refundId] = Date.now();
    });
    
    // Start checking for new messages every 30 seconds
    setInterval(checkForNewMessages, 30000);
});
</script>
</body>
</html>