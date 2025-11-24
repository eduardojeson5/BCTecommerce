<?php 
include 'INCLUDES/connect.php';

// Start session safely
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get user ID if logged in
$user_id = $_SESSION['user_id'] ?? '';

// REMOVED: include 'INCLUDES/add_cart.php'; - No longer needed
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop | Battlefront Computer Trading</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome & Boxicons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <!-- Custom Styles -->
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/aboutStyles.css">

    <style>
        /* ====== GENERAL ====== */
        body {
            background-color: #f8f9fa;
        }

        /* ====== HEADING ====== */
        .heading {
            text-align: center;
            background-color: #222;
            color: #ffffff;
            padding: 3rem 1rem;
            margin-bottom: 2rem;
        }

        .heading a {
            color: #ffffff;
            text-decoration: underline;
        }

        .heading span {
            color: #ced4da;
        }

        /* ====== PRODUCT CARD ====== */
        .product-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            border-radius: 15px;
            overflow: hidden;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .product-card img {
            height: 220px;
            object-fit: contain;
            background: #fff;
            border-bottom: 1px solid #eee;
        }

        /* ====== CATEGORY NAME COLOR (UPDATED) ====== */
        .product-category {
            font-size: 0.85rem;
            color: #0d6efd; /* ✅ Changed to blue */
            font-weight: 500;
        }

        .product-category:hover {
            color: #0a58ca; /* darker blue on hover */
            text-decoration: underline;
        }

        .product-price {
            font-weight: bold;
            color: #0d6efd;
        }

        /* ====== STOCK STATUS ====== */
        .stock-info {
            font-size: 0.85rem;
            font-weight: bold;
        }
        .in-stock { color: #198754; }
        .low-stock { color: #ffc107; }
        .out-of-stock { color: #dc3545; }

        /* ====== OUT OF STOCK OVERLAY ====== */
        .out-of-stock-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(220, 53, 69, 0.95);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: bold;
            z-index: 10;
        }

        .card.out-of-stock {
            opacity: 0.6;
        }

        /* ====== CUSTOM NOTIFICATION - MATCHING CHECKOUT STYLE ====== */
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

        /* ====== RESPONSIVE FIX ====== */
        @media (max-width: 576px) {
            .product-card img {
                height: 180px;
            }
        }
    </style>
</head>
<body>

<!-- Header -->
<?php include 'INCLUDES/user_header.php'; ?>

<!-- Page Heading -->
<div class="heading">
    <h2 class="fw-bold">Shop</h2>
    <p>
        <a href="index.php" class="text-light text-decoration-underline">Home</a> 
        <span class="text-secondary"> / Shop</span>
    </p>
</div>

<!-- Products Section -->
<section class="container my-5">
    <div class="row g-4">
        <?php
        // ✅ Fetch products with category join (dynamic update)
        $select_products = $conn->prepare("
            SELECT p.*, c.name AS category_name 
            FROM `products` p 
            LEFT JOIN `categories` c ON p.category_id = c.ID 
            ORDER BY p.ID DESC
        ");
        $select_products->execute();

        if ($select_products->rowCount() > 0) {
            while ($fetch_products = $select_products->fetch(PDO::FETCH_ASSOC)) {
                $stock_quantity = $fetch_products['stock_quantity'] ?? 0;
                $is_out_of_stock = $stock_quantity <= 0;
                $is_low_stock = $stock_quantity > 0 && $stock_quantity <= 10;

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
        <div class="col-12 col-sm-6 col-md-4 col-lg-3">
            <form class="add-to-cart-form position-relative <?= $is_out_of_stock ? 'out-of-stock' : '' ?>">
                <input type="hidden" name="pid" value="<?= $fetch_products['ID']; ?>">
                <input type="hidden" name="name" value="<?= htmlspecialchars($fetch_products['name']); ?>">
                <input type="hidden" name="price" value="<?= $fetch_products['price']; ?>">
                <input type="hidden" name="image" value="<?= htmlspecialchars($fetch_products['image']); ?>">
                <input type="hidden" name="stock_quantity" value="<?= $stock_quantity; ?>">

                <?php if ($is_out_of_stock): ?>
                    <div class="out-of-stock-overlay">Out of Stock</div>
                <?php endif; ?>

                <div class="card product-card h-100 shadow-sm <?= $is_out_of_stock ? 'out-of-stock' : '' ?>">
                    <a href="quick_view.php?pid=<?= $fetch_products['ID']; ?>">
                        <img src="uploaded_img/<?= htmlspecialchars($fetch_products['image']); ?>" 
                             class="card-img-top" alt="<?= htmlspecialchars($fetch_products['name']); ?>">
                    </a>

                    <div class="card-body text-center d-flex flex-column justify-content-between">
                        <div>
                            <a href="category.php?category=<?= urlencode($fetch_products['category_name']); ?>" 
                               class="product-category d-block mb-2">
                               <?= htmlspecialchars($fetch_products['category_name'] ?? 'Uncategorized'); ?>
                            </a>
                            <h6 class="card-title fw-semibold text-dark"><?= htmlspecialchars($fetch_products['name']); ?></h6>
                            <p class="product-price mb-1">₱<?= number_format($fetch_products['price']); ?></p>
                            <p class="stock-info <?= $stock_class ?>"><?= $stock_message ?></p>
                        </div>

                        <div class="mt-3">
                            <button type="submit" 
                                class="btn <?= $is_out_of_stock ? 'btn-secondary' : 'btn-primary' ?> w-100 mb-2 add-to-cart-btn"
                                <?= $is_out_of_stock ? 'disabled' : '' ?> >
                                <i class="fas fa-cart-plus"></i> 
                                <?= $is_out_of_stock ? 'Out of Stock' : 'Add to Cart' ?>
                            </button>

                            <?php if (!$is_out_of_stock): ?>
                                <input type="number" name="qty" class="form-control text-center" 
                                       min="1" max="<?= min(99, $stock_quantity) ?>" value="1"
                                       onkeypress="if(this.value.length == 2) return false;">
                            <?php else: ?>
                                <input type="number" class="form-control text-center" value="0" disabled>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php
            }
        } else {
            echo '<div class="text-center py-5 text-muted fs-5">No products added yet.</div>';
        }
        ?>
    </div>
</section>

<!-- Footer -->
<?php include 'INCLUDES/footer.php'; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/javascript.js" async defer></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Quantity input validation
    const quantityInputs = document.querySelectorAll('input[name="qty"]');
    quantityInputs.forEach(input => {
        const form = input.closest('form');
        const stockInput = form.querySelector('input[name="stock_quantity"]');
        const stockQuantity = parseInt(stockInput?.value) || 0;

        if (stockQuantity > 0) input.max = Math.min(99, stockQuantity);

        input.addEventListener('change', function() {
            const value = parseInt(this.value);
            const max = parseInt(this.max);
            if (value > max) {
                this.value = max;
                alert(`Only ${max} items available in stock.`);
            }
            if (value < 1) this.value = 1;
        });
    });

    // AJAX Add to Cart functionality
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
    
    function updateCartCount(count) {
        const cartBadge = document.querySelector('.badge');
        if(cartBadge) {
            cartBadge.textContent = count;
        }
    }
});
</script>

</body>
</html>