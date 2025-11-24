<?php
// search_products.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include 'INCLUDES/connect.php';

$user_id = $_SESSION['user_id'] ?? '';
$search_query = $_GET['query'] ?? '';

// Fetch products that match the search query with category name
if (!empty($search_query)) {
    $select_products = $conn->prepare("SELECT p.*, c.name as category_name 
                                      FROM `products` p
                                      LEFT JOIN `categories` c ON p.category_id = c.ID
                                      WHERE p.name LIKE ? 
                                      ORDER BY 
                                        CASE 
                                            WHEN p.name = ? THEN 1
                                            WHEN p.name LIKE ? THEN 2
                                            ELSE 3
                                        END, 
                                        p.name ASC");
    
    $exact_match = $search_query;
    $starts_with = $search_query . '%';
    $contains = '%' . $search_query . '%';
    
    $select_products->execute([$contains, $exact_match, $starts_with]);
    $search_results = $select_products->fetchAll(PDO::FETCH_ASSOC);
} else {
    $search_results = [];
}

// REMOVED: include 'INCLUDES/add_cart.php'; - No longer needed
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results - BFCT</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/form-styles.css">

    <style>
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

        .product-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

<?php include 'INCLUDES/user_header.php'; ?>

<div class="container my-5">
    <div class="row">
        <div class="col-12">
            <!-- Search Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="fw-bold text-primary">Search Results</h1>
                    <?php if (!empty($search_query)): ?>
                        <p class="text-muted">Showing results for: "<strong><?= htmlspecialchars($search_query) ?></strong>"</p>
                    <?php endif; ?>
                </div>
                <a href="shop.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Shop
                </a>
            </div>

            <!-- Search Results Count -->
            <div class="mb-4">
                <p class="text-muted">
                    Found <strong><?= count($search_results) ?></strong> product(s) matching your search
                </p>
            </div>

            <!-- Search Results Grid -->
            <?php if (!empty($search_results)): ?>
                <div class="row g-4">
                    <?php foreach($search_results as $product): 
                        $stock_quantity = $product['stock_quantity'] ?? 0;
                        $is_out_of_stock = $stock_quantity <= 0;
                        $is_low_stock = $stock_quantity > 0 && $stock_quantity <= 10;

                        if ($is_out_of_stock) {
                            $stock_class = 'text-danger';
                            $stock_message = 'Out of Stock';
                        } elseif ($is_low_stock) {
                            $stock_class = 'text-warning';
                            $stock_message = "Only $stock_quantity left";
                        } else {
                            $stock_class = 'text-success';
                            $stock_message = "In Stock: $stock_quantity";
                        }
                    ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <form class="add-to-cart-form card h-100 shadow-sm border-0 product-card">
                            <input type="hidden" name="pid" value="<?= $product['ID']; ?>">
                            <input type="hidden" name="name" value="<?= $product['name']; ?>">
                            <input type="hidden" name="price" value="<?= $product['price']; ?>">
                            <input type="hidden" name="image" value="<?= $product['image']; ?>">
                            <input type="hidden" name="stock_quantity" value="<?= $stock_quantity ?>">

                            <a href="quick_view.php?pid=<?= $product['ID']; ?>">
                                <img src="uploaded_img/<?= $product['image']; ?>" class="card-img-top p-3" alt="<?= htmlspecialchars($product['name']); ?>" loading="lazy" style="height: 200px; object-fit: contain;">
                            </a>

                            <div class="card-body text-center">
                                <!-- FIXED: Use category_name from JOIN instead of non-existent category column -->
                                <h6 class="text-primary"><?= htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></h6>
                                <h5 class="fw-bold"><?= htmlspecialchars($product['name']); ?></h5>
                                <p class="mb-1">₱<?= number_format($product['price']); ?></p>
                                <p class="<?= $stock_class ?> small mb-2"><?= $stock_message ?></p>

                                <div class="d-flex justify-content-center align-items-center gap-2">
                                    <input type="number" name="qty" class="form-control w-25 text-center" min="1" max="<?= min(99, $stock_quantity) ?>" value="1" <?= $is_out_of_stock ? 'disabled' : '' ?>>
                                    <button type="submit" class="btn btn-sm btn-outline-primary add-to-cart-btn" <?= $is_out_of_stock ? 'disabled' : '' ?>>
                                        <?= $is_out_of_stock ? 'Out of Stock' : 'Add to Cart' ?>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- No Results Found -->
                <div class="text-center py-5">
                    <div class="mb-4">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h3 class="text-muted">No products found</h3>
                    </div>
                    <p class="text-muted mb-4">We couldn't find any products matching "<strong><?= htmlspecialchars($search_query) ?></strong>"</p>
                    <div class="d-flex justify-content-center gap-3 flex-wrap">
                        <a href="shop.php" class="btn btn-primary">Browse All Products</a>
                        <a href="index.php" class="btn btn-outline-secondary">Go to Homepage</a>
                    </div>
                    <div class="mt-4">
                        <small class="text-muted">Suggestions:</small>
                        <ul class="list-unstyled mt-2">
                            <li><small class="text-muted">• Check your spelling</small></li>
                            <li><small class="text-muted">• Try different keywords</small></li>
                            <li><small class="text-muted">• Browse by category instead</small></li>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'INCLUDES/footer.php'; ?>

<!-- JS Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/javascript.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // AJAX Add to Cart functionality for search results
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

    // Quantity input validation
    const quantityInputs = document.querySelectorAll('input[name="qty"]');
    quantityInputs.forEach(input => {
        if (!input.disabled) {
            input.addEventListener('change', function() {
                const value = parseInt(this.value);
                const max = parseInt(this.max);
                const min = parseInt(this.min);
                
                if (value > max) {
                    this.value = max;
                    showNotification(`Only ${max} items available in stock.`, 'error');
                }
                
                if (value < min) {
                    this.value = min;
                }
            });
        }
    });

    // Highlight search terms in product names
    const searchQuery = "<?= addslashes($search_query) ?>";
    if (searchQuery) {
        highlightSearchTerms(searchQuery);
    }
});

function highlightSearchTerms(searchTerm) {
    const productNames = document.querySelectorAll('.card-body h5.fw-bold');
    const regex = new RegExp(`(${searchTerm})`, 'gi');
    
    productNames.forEach(element => {
        const originalText = element.textContent;
        const highlightedText = originalText.replace(regex, '<mark class="bg-warning">$1</mark>');
        element.innerHTML = highlightedText;
    });
}

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