<?php
// search_results.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include 'INCLUDES/connect.php';

// Check if user typed something in the search box
if (isset($_POST['search_box']) && !empty(trim($_POST['search_box']))) {
    $search_query = trim($_POST['search_box']);

    // First, try to find products by exact or similar name match
    $select_products_by_name = $conn->prepare("SELECT * FROM `products` WHERE name LIKE ? ORDER BY 
        CASE 
            WHEN name = ? THEN 1  -- Exact match first
            WHEN name LIKE ? THEN 2  -- Starts with search term
            ELSE 3  -- Contains search term
        END, name ASC");
    
    $exact_match = $search_query;
    $starts_with = $search_query . '%';
    $contains = '%' . $search_query . '%';
    
    $select_products_by_name->execute([$contains, $exact_match, $starts_with]);

    if ($select_products_by_name->rowCount() > 0) {
        // If we found products by name, redirect to search results page
        header("Location: search_products.php?query=" . urlencode($search_query));
        exit;
    }

    // If no products found by name, try category search
    // FIXED: Join with categories table to search by category name
    $select_products = $conn->prepare("SELECT p.*, c.name as category_name 
                                      FROM `products` p 
                                      LEFT JOIN `categories` c ON p.category_id = c.ID 
                                      WHERE c.name LIKE ?");
    $select_products->execute(["%$search_query%"]);

    if ($select_products->rowCount() > 0) {
        // Fetch the first matching product
        $fetch_product = $select_products->fetch(PDO::FETCH_ASSOC);

        // Get the product's category to redirect properly
        // FIXED: Use category_name from the JOIN
        $product_category = $fetch_product['category_name'];

        // Redirect to the category page
        header("Location: category.php?category=" . urlencode($product_category));
        exit;
    } else {
        // If no product found, redirect to the shop page with a message
        header("Location: shop.php?notfound=" . urlencode($search_query));
        exit;
    }
} else {
    // If search box is empty, go back to homepage
    header("Location: index.php");
    exit;
}
?>