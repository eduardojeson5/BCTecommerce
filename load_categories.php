<?php
include 'INCLUDES/connect.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get the requested page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// Fetch categories from database
$select_categories = $conn->prepare("SELECT * FROM `categories` ORDER BY name ASC");
$select_categories->execute();
$categories = $select_categories->fetchAll(PDO::FETCH_ASSOC);

// Pagination for categories
$categories_per_page = 4;
$total_categories = count($categories);
$total_pages = ceil($total_categories / $categories_per_page);
$current_page = max(1, min($total_pages, $page));
$start_index = ($current_page - 1) * $categories_per_page;
$current_categories = array_slice($categories, $start_index, $categories_per_page);
$show_pagination = $total_categories > 4;
?>

<!-- Categories Pagination -->
<div class="d-flex justify-content-center align-items-center mb-4">
    <?php if($show_pagination): ?>
        <!-- Previous Button -->
        <?php if($current_page > 1): ?>
            <a href="javascript:void(0);" onclick="loadCategories(<?= $current_page - 1 ?>)" class="btn btn-outline-primary me-3">
                <i class="fas fa-chevron-left"></i>
            </a>
        <?php else: ?>
            <button class="btn btn-outline-secondary me-3" disabled>
                <i class="fas fa-chevron-left"></i>
            </button>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Categories Row -->
    <div class="<?= $show_pagination ? 'flex-grow-1' : '' ?>">
        <div class="row g-3 justify-content-center">
            <?php if(!empty($current_categories)): ?>
                <?php foreach($current_categories as $category): ?>
                    <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                        <a href="category.php?category=<?= urlencode($category['name']); ?>" class="text-decoration-none">
                            <div class="card h-100 shadow-sm border-0 text-center category-card">
                                <?php if($category['image'] && file_exists('admin/images/category_images/' . $category['image'])): ?>
                                    <img src="admin/images/category_images/<?= $category['image']; ?>" class="card-img-top p-3" alt="<?= htmlspecialchars($category['name']); ?>" loading="lazy" style="height: 120px; object-fit: contain;">
                                <?php else: ?>
                                    <img src="images/hero-1.png" class="card-img-top p-3" alt="<?= htmlspecialchars($category['name']); ?>" loading="lazy" style="height: 120px; object-fit: contain;">
                                <?php endif; ?>
                                <div class="card-body p-2">
                                    <h6 class="card-title text-dark fw-bold mb-0 small"><?= htmlspecialchars($category['name']); ?></h6>
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
            <a href="javascript:void(0);" onclick="loadCategories(<?= $current_page + 1 ?>)" class="btn btn-outline-primary ms-3">
                <i class="fas fa-chevron-right"></i>
            </a>
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