<?php
include 'INCLUDES/connect.php';

// Starting a new session
session_start();

// Check if the user id is set in the session
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} else {
    $user_id = '';
}

// REMOVED: include 'INCLUDES/add_cart.php'; - No longer needed

// Create upload directory if it doesn't exist
$upload_dir = 'uploaded_reviews/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle AJAX review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_submit_review'])) {
    header('Content-Type: application/json');
    
    $product_id = $_POST['product_id'];
    $rating = $_POST['rating'];
    $comment = $_POST['comment'];
    $response = ['success' => false, 'message' => ''];
    
    if ($user_id === '') {
        $response['message'] = 'You need to log in to submit a review!';
        echo json_encode($response);
        exit;
    }

    // Get product name for purchase verification
    $get_product = $conn->prepare("SELECT name FROM products WHERE ID = ?");
    $get_product->execute([$product_id]);
    $product_name = $get_product->fetchColumn();

    // Check if user purchased this product
    $check_purchase = $conn->prepare("
        SELECT * FROM orders 
        WHERE user_id = ? 
          AND total_products LIKE CONCAT('%', ?, '%')
          AND payment_status = 'completed'
    ");
    $check_purchase->execute([$user_id, $product_name]);

    if ($check_purchase->rowCount() === 0) {
        $response['message'] = 'You can only review products you have purchased!';
        echo json_encode($response);
        exit;
    }

    $media_paths = [];

    // Handle image uploads (up to 5)
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $image_files = $_FILES['images'];
        $num_images = count($image_files['name']);
        $allowed_images = min(5, $num_images);

        for ($i = 0; $i < $allowed_images; $i++) {
            if ($image_files['error'][$i] === UPLOAD_ERR_OK) {
                $file_tmp = $image_files['tmp_name'][$i];
                $file_ext = pathinfo($image_files['name'][$i], PATHINFO_EXTENSION);
                $file_name = uniqid() . '.' . strtolower($file_ext);
                $file_path = $upload_dir . $file_name;

                // Validate image
                $image_info = getimagesize($file_tmp);
                if ($image_info !== false && in_array(strtolower($file_ext), ['jpg', 'jpeg', 'png', 'gif'])) {
                    if (move_uploaded_file($file_tmp, $file_path)) {
                        $media_paths[] = ['type' => 'image', 'path' => $file_path];
                    }
                }
            }
        }
    }

    // Handle video upload
    if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
        $video_file = $_FILES['video'];
        $max_video_size = 100 * 1024 * 1024;

        if ($video_file['size'] <= $max_video_size) {
            $file_tmp = $video_file['tmp_name'];
            $file_ext = pathinfo($video_file['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '.' . strtolower($file_ext);
            $file_path = $upload_dir . $file_name;

            if (in_array(strtolower($file_ext), ['mp4', 'avi', 'mov', 'wmv'])) {
                if (move_uploaded_file($file_tmp, $file_path)) {
                    $media_paths[] = ['type' => 'video', 'path' => $file_path];
                }
            }
        }
    }

    // Insert or update review
    $media_json = json_encode($media_paths);
    $existing_review = $conn->prepare("SELECT id FROM reviews WHERE product_id = ? AND user_id = ?");
    $existing_review->execute([$product_id, $user_id]);

    try {
        if ($existing_review->rowCount() > 0) {
            $review_id = $existing_review->fetchColumn();
            $update_review = $conn->prepare("UPDATE reviews SET rating = ?, comment = ?, media = ? WHERE id = ?");
            $update_review->execute([$rating, $comment, $media_json, $review_id]);
            $response['message'] = 'Review updated successfully!';
        } else {
            $insert_review = $conn->prepare("INSERT INTO reviews (product_id, user_id, rating, comment, media) VALUES (?, ?, ?, ?, ?)");
            $insert_review->execute([$product_id, $user_id, $rating, $comment, $media_json]);
            $response['message'] = 'Review submitted successfully!';
        }
        $response['success'] = true;
    } catch (Exception $e) {
        $response['message'] = 'Error submitting review: ' . $e->getMessage();
    }

    echo json_encode($response);
    exit;
}

// Delete review if requested
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    $check_review = $conn->prepare("SELECT user_id, media FROM reviews WHERE id = ?");
    $check_review->execute([$delete_id]);
    $review = $check_review->fetch(PDO::FETCH_ASSOC);

    if ($review && ($review['user_id'] == $user_id || (isset($_SESSION['role']) && $_SESSION['role'] == 'admin'))) {
        if ($review['media']) {
            $media_paths = json_decode($review['media'], true);
            if (is_array($media_paths)) {
                foreach ($media_paths as $media) {
                    if (file_exists($media['path'])) {
                        unlink($media['path']);
                    }
                }
            }
        }

        $delete_review = $conn->prepare("DELETE FROM reviews WHERE id = ?");
        $delete_review->execute([$delete_id]);
        header('Location: quick_view.php?pid=' . $_GET['pid'] . '#reviews-section');
        exit();
    } else {
        header('Location: quick_view.php?pid=' . $_GET['pid'] . '#reviews-section');
        exit();
    }
}

// Check for existing user review
$has_reviewed = false;
$user_review = null;
if ($user_id !== '') {
    $check_user_review = $conn->prepare("SELECT * FROM reviews WHERE product_id = ? AND user_id = ?");
    $check_user_review->execute([$_GET['pid'], $user_id]);
    if ($check_user_review->rowCount() > 0) {
        $has_reviewed = true;
        $user_review = $check_user_review->fetch(PDO::FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick View - Product Details</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Your existing CSS -->
    <link rel="stylesheet" href="css/styles.css">

    <style>
        .product-image {
            max-height: 500px;
            object-fit: cover;
        }
        
        .star-rating {
            color: #ffc107;
        }
        
        .star-rating .text-muted {
            color: #6c757d !important;
        }
        
        .review-media {
            max-width: 200px;
            max-height: 200px;
            object-fit: cover;
            margin: 5px;
            border-radius: 8px;
        }
        
        .stock-badge {
            font-size: 0.9rem;
        }
        
        .price-display {
            font-size: 2rem;
            font-weight: bold;
            color: #0d6efd;
        }
        
        .rating-stars label {
            cursor: pointer;
            color: #dee2e6;
            font-size: 1.5rem;
        }
        
        .rating-stars label:hover,
        .rating-stars label:hover ~ label {
            color: #ffc107;
        }
        
        .rating-stars input:checked ~ label {
            color: #ffc107;
        }
        
        .breadcrumb-item a {
            text-decoration: none;
        }
        
        /* Loading spinner */
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
        }
        
        /* Smooth scroll behavior */
        html {
            scroll-behavior: smooth;
        }
        
        /* Review form styles */
        .review-form-container {
            transition: all 0.3s ease;
        }
        
        .form-submitting {
            opacity: 0.7;
            pointer-events: none;
        }

        /* ====== CUSTOM NOTIFICATION - MATCHING STYLE ====== */
        .custom-cart-notification {
            background-color: rgba(13, 58, 153, 0.9); /* Dark blue background */
            box-shadow: 0 0 12px rgba(13, 58, 153, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            color: white; /* White text for contrast */
            border: none;
            border-radius: 8px;
            animation: slideInRight 0.3s ease-out;
        }

        .custom-cart-notification .toast-body {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            font-weight: 500;
        }

        .custom-cart-notification .toast-body i {
            margin-right: 8px;
            font-size: 1.1rem;
            color: white; /* White icon */
        }

        .custom-cart-notification .btn-close {
            filter: invert(1); /* White close button */
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .fa-spinner {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .form-loading {
            opacity: 0.6;
            pointer-events: none;
        }
    </style>
</head>
<body class="bg-light">

    <!-- Header -->
    <?php include 'INCLUDES/user_header.php'; ?>

    <!-- Breadcrumb -->
    <div class="container mt-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Product Details</li>
            </ol>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="container my-4">
        <?php
        $pid = $_GET['pid'];
        
        $select_products = $conn->prepare("
            SELECT p.*, c.name as category_name 
            FROM `products` p 
            LEFT JOIN `categories` c ON p.category_id = c.ID 
            WHERE p.ID = ?
        ");
        $select_products->execute([$pid]);
        
        if ($select_products->rowCount() > 0) {
            while ($fetch_products = $select_products->fetch(PDO::FETCH_ASSOC)) {
                $product_name = $fetch_products['name'];
                $stock_quantity = $fetch_products['stock_quantity'] ?? 0;
                $is_out_of_stock = $stock_quantity <= 0;
                $is_low_stock = $stock_quantity > 0 && $stock_quantity <= 10;

                if ($is_out_of_stock) {
                    $stock_class = 'danger';
                    $stock_message = 'Out of Stock';
                } elseif ($is_low_stock) {
                    $stock_class = 'warning';
                    $stock_message = "Only $stock_quantity left";
                } else {
                    $stock_class = 'success';
                    $stock_message = "In Stock: $stock_quantity";
                }
        ?>

        <!-- Product Details Section -->
        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card shadow-sm">
                    <img src="uploaded_img/<?= $fetch_products['image']; ?>" alt="<?= $fetch_products['name']; ?>" class="card-img-top product-image">
                    <?php if ($is_out_of_stock): ?>
                        <div class="position-absolute top-50 start-50 translate-middle">
                            <span class="badge bg-danger fs-4 p-3">OUT OF STOCK</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <span class="badge bg-primary mb-2">
                            <a href="category.php?category=<?= urlencode($fetch_products['category_name']); ?>" class="text-white text-decoration-none">
                                <?= $fetch_products['category_name']; ?>
                            </a>
                        </span>
                        
                        <h2 class="card-title mb-3"><?= $fetch_products['name']; ?></h2>
                        
                        <div class="price-display mb-3">
                            <i class="fas fa-peso-sign"></i><?= number_format($fetch_products['price']); ?>
                        </div>
                        
                        <span class="badge bg-<?= $stock_class ?> stock-badge mb-3">
                            <i class="fas fa-box"></i> <?= $stock_message ?>
                        </span>
                        
                        <p class="card-text text-muted mb-4"><?= $fetch_products['description']; ?></p>
                        
                        <?php if ($is_low_stock && !$is_out_of_stock): ?>
                            <div class="alert alert-warning" role="alert">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Low Stock:</strong> This item is selling fast! Only <?= $stock_quantity ?> left in stock.
                            </div>
                        <?php elseif ($is_out_of_stock): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-times-circle"></i>
                                <strong>Out of Stock:</strong> This item is currently unavailable.
                            </div>
                        <?php endif; ?>
                        
                        <form class="add-to-cart-form d-flex gap-2">
                            <input type="hidden" name="pid" value="<?= $fetch_products['ID']; ?>">
                            <input type="hidden" name="name" value="<?= $fetch_products['name']; ?>">
                            <input type="hidden" name="price" value="<?= $fetch_products['price']; ?>">
                            <input type="hidden" name="image" value="<?= $fetch_products['image'] ?>">
                            <input type="hidden" name="stock_quantity" value="<?= $stock_quantity ?>">
                            
                            <div class="input-group" style="max-width: 150px;">
                                <button class="btn btn-outline-secondary" type="button" onclick="decreaseQty()">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <input type="number" name="qty" id="qtyInput" class="form-control text-center" 
                                       min="1" max="<?= min(99, $stock_quantity) ?>" value="1" 
                                       <?= $is_out_of_stock ? 'disabled' : '' ?>>
                                <button class="btn btn-outline-secondary" type="button" onclick="increaseQty()">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            
                            <button type="submit" class="btn btn-primary flex-grow-1 add-to-cart-btn" 
                                    <?= $is_out_of_stock ? 'disabled' : '' ?>>
                                <i class="fas fa-cart-plus me-2"></i>
                                <?= $is_out_of_stock ? 'Out of Stock' : 'Add to Cart' ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php
            }
        } else {
            echo '<div class="alert alert-info text-center">No products found</div>';
        }
        ?>

        <!-- Reviews Section with Anchor -->
        <div class="row mt-5" id="reviews-section">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h3 class="mb-0"><i class="fas fa-star me-2"></i>Customer Reviews</h3>
                    </div>
                    <div class="card-body">
                        
                        <?php
                        // Calculate average rating
                        $avg_query = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM reviews WHERE product_id = ?");
                        $avg_query->execute([$pid]);
                        $avg = $avg_query->fetch(PDO::FETCH_ASSOC);
                        $avg_rating = $avg ? round($avg['avg_rating'], 1) : 0;
                        $total_reviews = $avg ? $avg['total_reviews'] : 0;
                        ?>

                        <?php if ($total_reviews > 0): ?>
                            <div class="text-center mb-4 p-3 bg-light rounded">
                                <h4 class="mb-2">Average Rating: <?= $avg_rating; ?>/5</h4>
                                <div class="star-rating mb-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?= $i <= $avg_rating ? '' : 'text-muted' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <p class="text-muted mb-0">(<?= $total_reviews; ?> reviews)</p>
                            </div>
                        <?php endif; ?>

                        <?php
                        // Check if user purchased this product
                        $purchased = false;
                        if ($user_id !== '') {
                            $check_purchase = $conn->prepare("
                                SELECT * FROM orders 
                                WHERE user_id = ? 
                                  AND total_products LIKE CONCAT('%', ?, '%')
                                  AND payment_status = 'completed'
                            ");
                            $check_purchase->execute([$user_id, $product_name]);
                            $purchased = $check_purchase->rowCount() > 0;
                        }

                        // Show different messages based on user status
                        if ($user_id === ''): ?>
                            <div class="alert alert-info text-center">
                                <i class="fas fa-lock me-2"></i>
                                Please <a href="user_login.php" class="alert-link">log in</a> to submit a review.
                            </div>
                        <?php elseif ($purchased): ?>
                            
                            <?php if ($has_reviewed): ?>
                                <!-- Edit Review Section -->
                                <div class="alert alert-success d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-check-circle me-2"></i>You have already reviewed this product.</span>
                                    <button class="btn btn-outline-success btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#editReviewForm">
                                        <i class="fas fa-edit me-1"></i>Edit Review
                                    </button>
                                </div>

                                <!-- Edit Review Form -->
                                <div class="collapse mb-4" id="editReviewForm">
                                    <div class="card review-form-container" id="editReviewFormContainer">
                                        <div class="card-header">
                                            <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Your Review</h5>
                                        </div>
                                        <div class="card-body">
                                            <form id="editReviewFormAjax" enctype="multipart/form-data">
                                                <input type="hidden" name="product_id" value="<?= $pid; ?>">
                                                <input type="hidden" name="ajax_submit_review" value="1">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Your Rating <span class="text-danger">*</span></label>
                                                    <div class="rating-stars d-flex flex-row-reverse justify-content-end">
                                                        <?php for ($i = 5; $i >= 1; $i--): ?>
                                                            <input type="radio" id="edit_star<?= $i ?>" name="rating" value="<?= $i ?>" 
                                                                   <?= $user_review['rating'] == $i ? 'checked' : '' ?> required class="d-none">
                                                            <label for="edit_star<?= $i ?>" class="me-1" title="<?= $i ?> stars">★</label>
                                                        <?php endfor; ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="edit_comment" class="form-label">Your Comment <span class="text-danger">*</span></label>
                                                    <textarea name="comment" id="edit_comment" class="form-control" rows="4" 
                                                              placeholder="Share your experience with this product..." required><?= htmlspecialchars($user_review['comment']) ?></textarea>
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="edit_images" class="form-label">Upload New Images (up to 5)</label>
                                                            <input type="file" name="images[]" id="edit_images" class="form-control" multiple accept="image/*">
                                                            <div class="form-text">Max 5 images (JPG, PNG, GIF). New images will replace existing ones.</div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="edit_video" class="form-label">Upload New Video (up to 2 minutes)</label>
                                                            <input type="file" name="video" id="edit_video" class="form-control" accept="video/*">
                                                            <div class="form-text">Max 100MB (MP4, AVI, MOV, WMV). New video will replace existing one.</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <button type="submit" class="btn btn-primary" id="editSubmitBtn">
                                                    <i class="fas fa-save me-2"></i>Update Review
                                                </button>
                                                <button type="button" class="btn btn-secondary" onclick="closeEditForm()">
                                                    <i class="fas fa-times me-2"></i>Cancel
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                            <?php else: ?>
                                <!-- New Review Form -->
                                <div class="card mb-4 review-form-container" id="newReviewFormContainer">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Write Your Review</h5>
                                    </div>
                                    <div class="card-body">
                                        <form id="newReviewFormAjax" enctype="multipart/form-data">
                                            <input type="hidden" name="product_id" value="<?= $pid; ?>">
                                            <input type="hidden" name="ajax_submit_review" value="1">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Your Rating <span class="text-danger">*</span></label>
                                                <div class="rating-stars d-flex flex-row-reverse justify-content-end">
                                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                                        <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" required class="d-none">
                                                        <label for="star<?= $i ?>" class="me-1" title="<?= $i ?> stars">★</label>
                                                    <?php endfor; ?>
                                                </div>
                                                <div class="form-text">Click on stars to rate this product</div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="comment" class="form-label">Your Comment <span class="text-danger">*</span></label>
                                                <textarea name="comment" id="comment" class="form-control" rows="4" 
                                                          placeholder="Share your experience with this product..." required></textarea>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="images" class="form-label">Upload Images (up to 5)</label>
                                                        <input type="file" name="images[]" id="images" class="form-control" multiple accept="image/*">
                                                        <div class="form-text">Max 5 images (JPG, PNG, GIF)</div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="video" class="form-label">Upload Video (up to 2 minutes)</label>
                                                        <input type="file" name="video" id="video" class="form-control" accept="video/*">
                                                        <div class="form-text">Max 100MB (MP4, AVI, MOV, WMV)</div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                                <i class="fas fa-paper-plane me-2"></i>Submit Review
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <div class="alert alert-warning text-center">
                                <i class="fas fa-info-circle me-2"></i>
                                You can review this product after purchase.
                            </div>
                        <?php endif; ?>

                        <!-- Display Reviews -->
                        <hr>
                        <h5 class="mb-3">All Reviews</h5>
                        <div id="reviewsList">
                            <?php
                            $select_reviews = $conn->prepare("SELECT reviews.*, users.name FROM reviews JOIN users ON reviews.user_id = users.ID WHERE product_id = ? ORDER BY created_at DESC");
                            $select_reviews->execute([$pid]);
                            if ($select_reviews->rowCount() > 0) {
                                while ($fetch_reviews = $select_reviews->fetch(PDO::FETCH_ASSOC)) {
                                    $media_paths = [];
                                    if (!empty($fetch_reviews['media'])) {
                                        $media_paths = json_decode($fetch_reviews['media'], true) ?: [];
                                    }
                            ?>
                                    <div class="card mb-3 review-item" id="review-<?= $fetch_reviews['id']; ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <h6 class="mb-1"><?= htmlspecialchars($fetch_reviews['name']); ?></h6>
                                                    <div class="star-rating mb-1">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star <?= $i <= $fetch_reviews['rating'] ? '' : 'text-muted' ?>"></i>
                                                        <?php endfor; ?>
                                                        <span class="ms-2 text-muted"><?= $fetch_reviews['rating']; ?>/5</span>
                                                    </div>
                                                    <small class="text-muted">
                                                        <i class="fas fa-calendar-alt me-1"></i>
                                                        <?= date('M j, Y g:i A', strtotime($fetch_reviews['created_at'])); ?>
                                                    </small>
                                                </div>
                                                
                                                <?php if ($fetch_reviews['user_id'] == $user_id || (isset($_SESSION['role']) && $_SESSION['role'] == 'admin')): ?>
                                                    <button class="btn btn-outline-danger btn-sm" onclick="confirmDelete(<?= $fetch_reviews['id']; ?>)">
                                                        <i class="fas fa-trash me-1"></i>Delete
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <p class="mb-3"><?= htmlspecialchars($fetch_reviews['comment']); ?></p>
                                            
                                            <?php if (!empty($media_paths)): ?>
                                                <div class="mb-2">
                                                    <h6>Media:</h6>
                                                    <div class="d-flex flex-wrap">
                                                        <?php foreach ($media_paths as $media): ?>
                                                            <?php if ($media['type'] === 'image'): ?>
                                                                <img src="<?= htmlspecialchars($media['path']); ?>" alt="Review Image" 
                                                                     class="review-media" data-bs-toggle="modal" data-bs-target="#mediaModal" 
                                                                     onclick="showMedia('<?= htmlspecialchars($media['path']); ?>', 'image')">
                                                            <?php elseif ($media['type'] === 'video'): ?>
                                                                <video class="review-media" controls>
                                                                    <source src="<?= htmlspecialchars($media['path']); ?>" type="video/mp4">
                                                                    Your browser does not support the video tag.
                                                                </video>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                            <?php
                                }
                            } else {
                                echo '<div class="text-center text-muted py-5">
                                        <i class="fas fa-comments fa-3x mb-3"></i>
                                        <p class="lead">No customer reviews yet.</p>
                                        <p>Be the first to review this product!</p>
                                      </div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Media Modal -->
    <div class="modal fade" id="mediaModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Review Media</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" alt="Review Image" class="img-fluid" style="display: none;">
                    <video id="modalVideo" controls class="w-100" style="display: none;">
                        <source src="" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include 'INCLUDES/footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Quantity controls
        function increaseQty() {
            const input = document.getElementById('qtyInput');
            const max = parseInt(input.getAttribute('max'));
            const current = parseInt(input.value);
            if (current < max) {
                input.value = current + 1;
            }
        }

        function decreaseQty() {
            const input = document.getElementById('qtyInput');
            const current = parseInt(input.value);
            if (current > 1) {
                input.value = current - 1;
            }
        }

        // File upload validation
        document.getElementById('images')?.addEventListener('change', function(e) {
            if (e.target.files.length > 5) {
                showNotification('You can only upload up to 5 images.', 'error');
                e.target.value = '';
            }
        });

        document.getElementById('edit_images')?.addEventListener('change', function(e) {
            if (e.target.files.length > 5) {
                showNotification('You can only upload up to 5 images.', 'error');
                e.target.value = '';
            }
        });

        // Star rating functionality
        document.querySelectorAll('.rating-stars input').forEach(input => {
            input.addEventListener('change', function() {
                const rating = this.value;
                const labels = this.parentElement.querySelectorAll('label');
                labels.forEach((label, index) => {
                    if ((5 - index) <= rating) {
                        label.style.color = '#ffc107';
                    } else {
                        label.style.color = '#dee2e6';
                    }
                });
            });
        });

        // Delete confirmation
        function confirmDelete(reviewId) {
            if (confirm('Are you sure you want to delete this review?')) {
                window.location.href = `quick_view.php?pid=<?= $pid; ?>&delete=${reviewId}`;
            }
        }

        // Media modal
        function showMedia(src, type) {
            const modal = document.getElementById('mediaModal');
            const image = document.getElementById('modalImage');
            const video = document.getElementById('modalVideo');
            
            if (type === 'image') {
                image.src = src;
                image.style.display = 'block';
                video.style.display = 'none';
            } else {
                video.querySelector('source').src = src;
                video.load();
                video.style.display = 'block';
                image.style.display = 'none';
            }
        }

        // Quantity input validation
        document.getElementById('qtyInput')?.addEventListener('change', function() {
            const value = parseInt(this.value);
            const max = parseInt(this.getAttribute('max'));
            const min = parseInt(this.getAttribute('min'));
            
            if (value > max) {
                this.value = max;
                showNotification(`Only ${max} items available in stock.`, 'error');
            } else if (value < min) {
                this.value = min;
            }
        });

        // Close edit form
        function closeEditForm() {
            const collapse = document.getElementById('editReviewForm');
            const bsCollapse = new bootstrap.Collapse(collapse, {
                toggle: false
            });
            bsCollapse.hide();
        }

        // AJAX review form submission
        document.getElementById('newReviewFormAjax')?.addEventListener('submit', function(e) {
            e.preventDefault();
            submitReviewForm(this, 'submitBtn');
        });

        document.getElementById('editReviewFormAjax')?.addEventListener('submit', function(e) {
            e.preventDefault();
            submitReviewForm(this, 'editSubmitBtn');
        });

        function submitReviewForm(form, submitBtnId) {
            const submitBtn = document.getElementById(submitBtnId);
            const originalText = submitBtn.innerHTML;
            const formContainer = form.closest('.review-form-container');
            
            // Show loading state
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
            submitBtn.disabled = true;
            formContainer.classList.add('form-submitting');
            
            const formData = new FormData(form);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    // Reload the page after a short delay to show updated reviews
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showNotification(data.message, 'error');
                    // Reset form state
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                    formContainer.classList.remove('form-submitting');
                }
            })
            .catch(error => {
                showNotification('An error occurred while submitting the review.', 'error');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                formContainer.classList.remove('form-submitting');
            });
        }

        // AJAX Add to Cart functionality
        document.addEventListener('DOMContentLoaded', function() {
            const addToCartForms = document.querySelectorAll('.add-to-cart-form');
            
            addToCartForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    formData.append('add_to_cart', 'true');
                    
                    // Show loading state
                    const submitBtn = this.querySelector('.add-to-cart-btn');
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
                    submitBtn.disabled = true;
                    
                    fetch('INCLUDES/ajax_add_to_cart.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        // Reset button state
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                        
                        // Show notification
                        showNotification(data.message, data.success ? 'success' : 'error');
                        
                        // Update cart count in header
                        if(data.cart_count !== undefined) {
                            updateCartCount(data.cart_count);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                        showNotification('Error adding to cart!', 'error');
                    });
                });
            });

            // Check for URL hash and scroll to reviews section
            if (window.location.hash === '#reviews-section') {
                const element = document.getElementById('reviews-section');
                if (element) {
                    setTimeout(() => {
                        element.scrollIntoView({ behavior: 'smooth' });
                    }, 100);
                }
            }
        });

        // Update cart count in header
        function updateCartCount(count) {
            const cartBadge = document.querySelector('.badge');
            if(cartBadge) {
                cartBadge.textContent = count;
            }
        }

        // Show notification
        function showNotification(message, type) {
            // Remove existing notifications
            const existingNotifications = document.querySelectorAll('.custom-cart-notification');
            existingNotifications.forEach(notification => notification.remove());
            
            // Create new notification
            const notification = document.createElement('div');
            notification.className = `custom-cart-notification toast show`;
            notification.style.cssText = `
                top: 120px;
                right: 20px;
                z-index: 9999;
                min-width: 300px;
                position: fixed;
            `;
            notification.innerHTML = `
                <div class="toast-body">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 4 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 4000);
            
            // Add Bootstrap dismiss functionality
            const closeButton = notification.querySelector('.btn-close');
            closeButton.addEventListener('click', function() {
                notification.remove();
            });
        }
    </script>
</body>
</html>