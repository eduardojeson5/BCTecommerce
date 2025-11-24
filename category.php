<?php
include 'INCLUDES/connect.php';

// Starting a new session if none exists
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if the user id is set in the session
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} else {
    $user_id = '';
}

// REMOVED: include 'INCLUDES/add_cart.php'; - No longer needed
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category - Battlefront Computer Trading</title>

    <!-- Icon links -->
    <link href="https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/form-styles.css">
    <link rel="stylesheet" href="css/shopStyles.css">
    <link rel="stylesheet" href="css/aboutStyles.css">
    <link rel="stylesheet" href="css/checkoutStyles.css">

    <style>
        /* Product info styling */
        .product-info {
            text-align: center;
        }

        .product-info .name {
            font-size: 1.1rem;
            font-weight: bold;
            margin: 10px 0;
        }

        .product-info .price {
            font-size: 1.2rem;
            color: #007bff; /* Blue color for price */
            margin: 10px 0;
            font-weight: 600;
        }

        .stock-info {
            margin: 5px 0;
            font-size: 0.85rem;
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }

        /* Stock status colors - CHANGED TO GREEN */
        .stock-info.in-stock {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .stock-info.low-stock {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .stock-info.out-of-stock {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Custom Blue Button */
        .product-btns button {
            background-color: #007bff !important;  /* Blue color for the button */
            color: white !important;
            border: none;
            padding: 10px;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
        }

        .product-btns button:disabled {
            background-color: #ccc !important; /* Grey background when disabled */
        }

        .product-btns .qty {
            width: 60px;
            margin-left: 10px;
        }

        /* Out of stock overlay */
        .out-of-stock-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(220, 53, 69, 0.9);
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: bold;
            z-index: 10;
        }

        /* Category box styling */
        .cat-box {
            position: relative;
            overflow: hidden;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
            padding: 15px;
            background: white;
        }

        .cat-box:hover {
            transform: translateY(-5px);
        }

        .cat-box.out-of-stock {
            opacity: 0.7;
        }

        .empty {
            text-align: center;
            padding: 30px;
            font-size: 1.2rem;
            color: #6c757d;
        }

        /* Make image clickable */
        .product-image-container {
            position: relative;
            cursor: pointer;
            overflow: hidden;
        }

        .product-image-container img {
            width: 100%;
            height: auto;
            transition: opacity 0.3s ease;
        }

        .product-image-container:hover img {
            opacity: 0.8;
        }

        /* Quick view icon - hidden by default */
        .quick-view-icon {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0);
            background: rgba(0, 123, 255, 0.9);
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 5;
            transition: all 0.3s ease;
            opacity: 0;
        }

        .product-image-container:hover .quick-view-icon {
            opacity: 1;
            transform: translate(-50%, -50%) scale(1);
        }

        .quick-view-icon i {
            font-size: 1.2rem;
        }

        /* Philippine Peso Symbol Styling */
        .price-symbol {
            font-weight: bold;
            margin-right: 2px;
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
<body>

<!-- Header section starts -->
<?php include 'INCLUDES/user_header.php'; ?>
<!-- Header section ends -->

<!-- Category header -->
<section class="category-heading py-5 bg-light">
    <?php
    if (isset($_GET['category'])) {
        $category_name = htmlspecialchars($_GET['category']);
    } else {
        $category_name = 'Unknown Category';
    }
    ?>
    <div class="container text-center">
        <h2 class="fw-bold"><?= $category_name ?></h2>
        <p><a href="index.php" class="text-decoration-none">Home</a> <span>/ <?= $category_name ?></span></p>
    </div>
</section>

<!-- Category section starts -->
<section class="products py-5">
    <div class="container">
        <div class="row row-cols-1 row-cols-md-3 g-4">
            <?php
            if (isset($_GET['category'])) {
                $category_name = $_GET['category'];
                
                // FIXED: Use JOIN query to get products by category name from categories table
                $select_products = $conn->prepare("
                    SELECT p.*, c.name as category_name 
                    FROM `products` p 
                    LEFT JOIN `categories` c ON p.category_id = c.ID 
                    WHERE c.name = ?
                    ORDER BY p.ID DESC
                ");
                $select_products->execute([$category_name]);
                
                if ($select_products->rowCount() > 0) {
                    while ($fetch_products = $select_products->fetch(PDO::FETCH_ASSOC)) {
                        // Get stock quantity and determine status
                        $stock_quantity = $fetch_products['stock_quantity'] ?? 0;
                        $is_out_of_stock = $stock_quantity <= 0;
                        $is_low_stock = $stock_quantity > 0 && $stock_quantity <= 10;

                        // Determine stock class and message
                        if ($is_out_of_stock) {
                            $stock_class = 'out-of-stock';
                            $stock_message = 'Out of Stock';
                        } elseif ($is_low_stock) {
                            $stock_class = 'low-stock';
                            $stock_message = "Only $stock_quantity left";
                        } else {
                            $stock_class = 'in-stock';
                            $stock_message = "In Stock: $stock_quantity";
                        }
                        ?>
                        <div class="col">
                            <form class="add-to-cart-form cat-box <?= $is_out_of_stock ? 'out-of-stock' : '' ?>">
                                <input type="hidden" name="pid" value="<?= $fetch_products['ID']; ?>">
                                <input type="hidden" name="name" value="<?= $fetch_products['name']; ?>">
                                <input type="hidden" name="price" value="<?= $fetch_products['price']; ?>">
                                <input type="hidden" name="image" value="<?= $fetch_products['image'] ?>">
                                <input type="hidden" name="stock_quantity" value="<?= $stock_quantity ?>">

                                <?php if($is_out_of_stock): ?>
                                    <div class="out-of-stock-overlay">Out of Stock</div>
                                <?php endif; ?>

                                <!-- Clickable image container -->
                                <div class="product-image-container" onclick="window.location.href='quick_view.php?pid=<?= $fetch_products['ID']; ?>'">
                                    <img src="uploaded_img/<?= $fetch_products['image']; ?>" alt="product" class="img-fluid">
                                    <div class="quick-view-icon" title="Quick View">
                                        <i class="fas fa-eye"></i>
                                    </div>
                                </div>

                                <!-- Product information -->
                                <div class="product-info mt-3">
                                    <div class="name"><?= $fetch_products['name']; ?></div>
                                    <!-- CHANGED TO PHILIPPINE PESO SYMBOL -->
                                    <div class="price">
                                        <span class="price-symbol">₱</span><?= number_format($fetch_products['price'], 2); ?>
                                    </div>

                                    <!-- Stock Information - NOW WITH GREEN BACKGROUND -->
                                    <div class="stock-info <?= $stock_class ?>">
                                        <?= $stock_message ?>
                                    </div>
                                </div>

                                <!-- Buttons -->
                                <div class="product-btns d-flex justify-content-center align-items-center">
                                    <button type="submit" class="btn add-to-cart-btn <?= $is_out_of_stock ? 'btn-secondary' : 'btn-primary' ?>" <?= $is_out_of_stock ? 'disabled' : '' ?>>
                                        <?= $is_out_of_stock ? 'Out of Stock' : 'Add to Cart' ?>
                                    </button>
                                    <?php if(!$is_out_of_stock): ?>
                                        <input type="number" name="qty" class="qty ms-2 form-control" min="1" max="<?= min(99, $stock_quantity) ?>" value="1" onkeypress="if(this.value.length == 2) return false;">
                                    <?php else: ?>
                                        <input type="number" class="qty ms-2 form-control" value="0" disabled style="opacity: 0.5;">
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                        <?php
                    }
                } else {
                    echo '<div class="empty">No products found in this category</div>';
                }
            } else {
                echo '<div class="empty">Category not specified</div>';
            }
            ?>
        </div>
    </div>
</section>
<!-- Category section ends -->

<!-- Footer section -->
<?php include 'INCLUDES/footer.php'; ?>
<!-- Footer section ends -->

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/javascript.js" async defer></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // AJAX Add to Cart functionality for category page
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

    // Update quantity input max value based on stock
    const quantityInputs = document.querySelectorAll('input[name="qty"]');

    quantityInputs.forEach(input => {
        if (!input.disabled) {
            const form = input.closest('form');
            const stockInput = form.querySelector('input[name="stock_quantity"]');
            const stockQuantity = parseInt(stockInput?.value) || 0;

            if (stockQuantity > 0) {
                input.max = Math.min(99, stockQuantity);
            }

            // Validate quantity on change
            input.addEventListener('change', function() {
                const value = parseInt(this.value);
                const max = parseInt(this.max);

                if (value > max) {
                    this.value = max;
                    showNotification(`Only ${max} items available in stock.`, 'error');
                }

                if (value < 1) {
                    this.value = 1;
                }
            });
        }
    });

    // Prevent form submission when clicking on the image
    document.querySelectorAll('.product-image-container').forEach(container => {
        container.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const onclickAttr = this.getAttribute('onclick');
            if (onclickAttr) {
                const url = onclickAttr.match(/'(.*?)'/)[1];
                window.location.href = url;
            }
        });
    });
});

function updateCartCount(count) {
    const cartBadge = document.querySelector('.badge');
    if(cartBadge) {
        cartBadge.textContent = count;
    }
}

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