<?php
include 'INCLUDES/connect.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: portal.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? '';

// REMOVED: include 'INCLUDES/add_cart.php'; - No longer needed

// Fetch ALL categories from database
$select_categories = $conn->prepare("SELECT * FROM `categories` ORDER BY name ASC");
$select_categories->execute();
$all_categories = $select_categories->fetchAll(PDO::FETCH_ASSOC);

// Get total categories count
$total_categories = count($all_categories);
$categories_per_page = 4;
$total_pages = ceil($total_categories / $categories_per_page);
$current_page = isset($_GET['cat_page']) ? max(1, min($total_pages, (int)$_GET['cat_page'])) : 1;
$start_index = ($current_page - 1) * $categories_per_page;
$current_categories = array_slice($all_categories, $start_index, $categories_per_page);
$show_pagination = $total_categories > 4;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>BFCT</title>

    <!-- ✅ Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Icons -->
    <link href="https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css"/>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/styles.css"/>
    <link rel="stylesheet" href="css/form-styles.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Glide.js/3.4.1/css/glide.core.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Glide.js/3.4.1/css/glide.theme.css"/>

    <style>
        /* Category Box Adjustments - ENLARGED */
        .category-card {
            transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
            border: 2px solid #e9ecef !important;
            border-radius: 16px !important;
            overflow: hidden;
        }

        .category-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 12px 35px rgba(0,0,0,0.2) !important;
            border-color: #007bff !important;
        }

        .category-card .card-img-top {
            height: 150px !important;
            object-fit: contain;
            padding: 20px !important;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }

        .category-card:hover .card-img-top {
            transform: scale(1.1);
            background: #e3f2fd;
        }

        .category-card .card-body {
            padding: 16px 12px !important;
            background: white;
            border-top: 2px solid #e9ecef;
        }

        .category-card .card-title {
            font-size: 1rem;
            font-weight: 700;
            color: #2c3e50;
            margin: 0;
            line-height: 1.4;
        }

        .btn-outline-primary, .btn-outline-secondary {
            min-width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            font-size: 1.1rem;
        }

        .categories-row {
            min-height: 220px;
        }

        /* Category grid adjustments for larger boxes */
        .col-lg-2 {
            padding: 15px;
        }

        /* Cover photo adjustments */
        .cover-photo {
            margin-top: 0;
            padding-top: 0;
        }
        
        .cover-photo img {
            max-height: 500px !important;
            object-fit: cover;
            width: 100%;
        }

        .cover-placeholder {
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        /* PRICE COLOR CHANGED TO BLUE */
        .product-price {
            color: #007bff !important;
            font-weight: 600;
            font-size: 1.1rem;
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

        @media (max-width: 768px) {
            .category-card .card-img-top {
                height: 120px !important;
                padding: 20px !important;
            }
            
            .category-card .card-body {
                padding: 12px 8px !important;
            }
            
            .category-card .card-title {
                font-size: 0.9rem;
            }
            
            .btn-outline-primary, .btn-outline-secondary {
                min-width: 45px;
                height: 45px;
            }
            
            .cover-photo img {
                max-height: 200px !important;
            }
            
            .cover-placeholder {
                height: 150px;
            }
            
            .col-6 {
                padding: 8px;
            }
        }

        @media (max-width: 576px) {
            .category-card .card-img-top {
                height: 103px !important;
                padding: 15px !important;
            }
            
            .category-card .card-title {
                font-size: 0.85rem;
            }
        }
    </style>

</head>
<body>

<?php include 'INCLUDES/user_header.php'; ?>

<!-- Cover Photo Section - Moved closer to header -->
<section class="cover-photo text-center position-relative mb-0" style="margin-top: 0;">
    <div class="cover-placeholder d-none">
        <h4 class="mb-0">Loading cover images...</h4>
    </div>
    <img id="coverImage" src="" alt="BFCT Cover Photo" class="img-fluid w-100" style="display: none; max-height: 300px; object-fit: cover;">
</section>

<script>
const images = ["images/bfctcover2.jpg", "images/bfctcover3.png"];
let currentIndex = 0;
const coverImage = document.getElementById("coverImage");
const coverPlaceholder = document.querySelector('.cover-placeholder');

function showImage(index) {
    coverImage.src = images[index];
    coverImage.style.display = 'block';
    if (coverPlaceholder) {
        coverPlaceholder.style.display = 'none';
    }
}

// Preload first image and show immediately
const firstImage = new Image();
firstImage.src = images[0];
firstImage.onload = function() {
    showImage(0);
};

setInterval(() => {
    currentIndex = (currentIndex + 1) % images.length;
    showImage(currentIndex);
}, 4000);
</script>

<!-- Categories Section -->
<div class="container my-5" id="categories-section">
    <div class="text-center mb-4">
        <h1 class="fw-bold text-primary">Categories</h1>
        <p class="text-muted fst-italic">"Dive into Our Categories!"</p>
    </div>

    <!-- Categories Container -->
    <div class="d-flex justify-content-center align-items-center mb-4">
        <?php if($show_pagination): ?>
            <!-- Previous Button -->
            <?php if($current_page > 1): ?>
                <button onclick="changeCategoryPage(<?= $current_page - 1 ?>)" class="btn btn-outline-primary me-3">
                    <i class="fas fa-chevron-left"></i>
                </button>
            <?php else: ?>
                <button class="btn btn-outline-secondary me-3" disabled>
                    <i class="fas fa-chevron-left"></i>
                </button>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Categories Row -->
        <div class="<?= $show_pagination ? 'flex-grow-1' : '' ?>">
            <div class="row g-3 justify-content-center categories-row" id="categories-display">
                <?php if(!empty($current_categories)): ?>
                    <?php foreach($current_categories as $category): ?>
                        <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                            <a href="category.php?category=<?= urlencode($category['name']); ?>" class="text-decoration-none">
                                <div class="card h-100 shadow-sm border-0 text-center category-card">
                                    <?php if($category['image'] && file_exists('admin/images/category_images/' . $category['image'])): ?>
                                        <img src="admin/images/category_images/<?= $category['image']; ?>" class="card-img-top p-3" alt="<?= htmlspecialchars($category['name']); ?>">
                                    <?php else: ?>
                                        <img src="images/hero-1.png" class="card-img-top p-3" alt="<?= htmlspecialchars($category['name']); ?>">
                                    <?php endif; ?>
                                    <div class="card-body p-2">
                                        <h6 class="card-title text-dark fw-bold mb-0"><?= htmlspecialchars($category['name']); ?></h6>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center text-muted">No categories available yet. Check back soon!</div>
                <?php endif; ?>
            </div>
        </div>

        <?php if($show_pagination): ?>
            <!-- Next Button -->
            <?php if($current_page < $total_pages): ?>
                <button onclick="changeCategoryPage(<?= $current_page + 1 ?>)" class="btn btn-outline-primary ms-3">
                    <i class="fas fa-chevron-right"></i>
                </button>
            <?php else: ?>
                <button class="btn btn-outline-secondary ms-3" disabled>
                    <i class="fas fa-chevron-right"></i>
                </button>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Page Indicator -->
    <?php if($show_pagination && $total_pages > 1): ?>
        <div class="text-center mt-3">
            <small class="text-muted">Page <?= $current_page ?> of <?= $total_pages ?></small>
        </div>
    <?php endif; ?>
</div>

<!-- Hidden container with ALL categories data for JavaScript -->
<div id="all-categories-data" style="display: none;">
    <?= htmlspecialchars(json_encode($all_categories)) ?>
</div>

<!-- Brand New Products -->
<div class="container my-5">
    <div class="text-center mb-4">
        <h1 class="fw-bold text-success">Brand New Products</h1>
        <p class="text-muted fst-italic">"Dive into Our Latest and Brand New Products!"</p>
    </div>

    <div class="row g-4">
        <?php
        $select_products = $conn->prepare("SELECT p.*, c.name as category_name 
                                           FROM `products` p 
                                           LEFT JOIN `categories` c ON p.category_id = c.ID 
                                           ORDER BY p.ID DESC 
                                           LIMIT 6");
        $select_products->execute();
        if($select_products->rowCount() > 0):
            while($fetch_products = $select_products->fetch(PDO::FETCH_ASSOC)):
                $stock_quantity = $fetch_products['stock_quantity'] ?? 0;
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
            <form class="add-to-cart-form card h-100 shadow-sm border-0">
                <input type="hidden" name="pid" value="<?= $fetch_products['ID']; ?>">
                <input type="hidden" name="name" value="<?= $fetch_products['name']; ?>">
                <input type="hidden" name="price" value="<?= $fetch_products['price']; ?>">
                <input type="hidden" name="image" value="<?= $fetch_products['image']; ?>">
                <input type="hidden" name="stock_quantity" value="<?= $stock_quantity ?>">

                <a href="quick_view.php?pid=<?= $fetch_products['ID']; ?>">
                    <img src="uploaded_img/<?= $fetch_products['image']; ?>" class="card-img-top p-3" alt="<?= htmlspecialchars($fetch_products['name']); ?>" loading="lazy">
                </a>

                <div class="card-body text-center">
                    <h6 class="text-primary"><?= htmlspecialchars($fetch_products['category_name']); ?></h6>
                    <h5 class="fw-bold"><?= htmlspecialchars($fetch_products['name']); ?></h5>
                    <!-- PRICE COLOR CHANGED TO BLUE -->
                    <p class="product-price mb-1">₱<?= number_format($fetch_products['price']); ?></p>
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
        <?php endwhile; else: ?>
            <div class="text-center text-muted">No products added yet.</div>
        <?php endif; ?>
    </div>

    <div class="text-center mt-4">
        <a href="shop.php" class="btn btn-lg btn-primary">View All Products</a>
    </div>
</div>

<?php include 'INCLUDES/footer.php'; ?>

<!-- JS Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Glide.js/3.4.1/glide.min.js"></script>
<script src="js/javascript.js"></script>
<script src="js/slider.js" async defer></script>
<script src="js/admin_script.js" async defer></script>

<script>
// Get all categories data from PHP
const allCategories = JSON.parse(document.getElementById('all-categories-data').textContent);
const categoriesPerPage = 4;
let currentCategoryPage = <?= $current_page ?>;

// Function to change category page WITHOUT page refresh
function changeCategoryPage(newPage) {
    // Calculate the categories to show
    const startIndex = (newPage - 1) * categoriesPerPage;
    const currentCategories = allCategories.slice(startIndex, startIndex + categoriesPerPage);
    
    // Update the display
    updateCategoriesDisplay(currentCategories);
    
    // Update current page
    currentCategoryPage = newPage;
    
    // Update page indicator
    updatePageIndicator();
    
    // Update button states
    updateButtonStates();
    
    // Update URL without page reload
    updateURL(newPage);
}

// Update categories display
function updateCategoriesDisplay(categories) {
    const categoriesDisplay = document.getElementById('categories-display');
    
    if (categories.length === 0) {
        categoriesDisplay.innerHTML = '<div class="col-12 text-center text-muted">No categories available yet. Check back soon!</div>';
        return;
    }
    
    let html = '';
    categories.forEach(category => {
        html += `
            <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <a href="category.php?category=${encodeURIComponent(category.name)}" class="text-decoration-none">
                    <div class="card h-100 shadow-sm border-0 text-center category-card">
                        ${category.image ? 
                            `<img src="admin/images/category_images/${category.image}" class="card-img-top p-3" alt="${category.name}">` :
                            `<img src="images/hero-1.png" class="card-img-top p-3" alt="${category.name}">`
                        }
                        <div class="card-body p-2">
                            <h6 class="card-title text-dark fw-bold mb-0">${category.name}</h6>
                        </div>
                    </div>
                </a>
            </div>
        `;
    });
    
    categoriesDisplay.innerHTML = html;
}

// Update page indicator
function updatePageIndicator() {
    const pageIndicator = document.querySelector('.text-center small');
    if (pageIndicator) {
        pageIndicator.textContent = `Page ${currentCategoryPage} of ${Math.ceil(allCategories.length / categoriesPerPage)}`;
    }
}

// Update button states
function updateButtonStates() {
    const prevButton = document.querySelector('.btn-outline-primary.me-3, .btn-outline-secondary.me-3');
    const nextButton = document.querySelector('.btn-outline-primary.ms-3, .btn-outline-secondary.ms-3');
    const totalPages = Math.ceil(allCategories.length / categoriesPerPage);
    
    // Update previous button
    if (prevButton) {
        if (currentCategoryPage > 1) {
            prevButton.className = 'btn btn-outline-primary me-3';
            prevButton.onclick = () => changeCategoryPage(currentCategoryPage - 1);
            prevButton.disabled = false;
        } else {
            prevButton.className = 'btn btn-outline-secondary me-3';
            prevButton.disabled = true;
        }
    }
    
    // Update next button
    if (nextButton) {
        if (currentCategoryPage < totalPages) {
            nextButton.className = 'btn btn-outline-primary ms-3';
            nextButton.onclick = () => changeCategoryPage(currentCategoryPage + 1);
            nextButton.disabled = false;
        } else {
            nextButton.className = 'btn btn-outline-secondary ms-3';
            nextButton.disabled = true;
        }
    }
}

// Update URL without page reload
function updateURL(page) {
    const url = new URL(window.location.href);
    url.searchParams.set('cat_page', page);
    window.history.pushState({}, '', url.toString());
}

// Handle browser back/forward buttons
window.addEventListener('popstate', function(event) {
    const urlParams = new URLSearchParams(window.location.search);
    const page = parseInt(urlParams.get('cat_page')) || 1;
    if (page !== currentCategoryPage) {
        changeCategoryPage(page);
    }
});

// AJAX Add to Cart functionality for index.php
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