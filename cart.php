<?php

include 'INCLUDES/connect.php';

// Starting a new session if none exists
if (session_status() == PHP_SESSION_NONE) {
  session_start();
}

// Check if the user id is set in the session
if(isset($_SESSION['user_id'])){
  $user_id = $_SESSION['user_id'];
}else{
  $user_id = '';
  header('location:index.php');
  exit();
}

// Handle AJAX requests
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  $response = ['success' => false, 'message' => '', 'cart_count' => 0, 'grand_total' => 0];
  
  if($_POST['action'] === 'update_qty') {
    $cart_id = $_POST['cart_id'];
    $qty = $_POST['qty'];
    $qty = filter_var($qty, FILTER_SANITIZE_STRING);
    
    // Check stock availability before updating
    $check_stock = $conn->prepare("SELECT p.stock_quantity, c.quantity as cart_quantity 
                                  FROM `cart` c 
                                  JOIN `products` p ON c.pid = p.ID 
                                  WHERE c.id = ?");
    $check_stock->execute([$cart_id]);
    $stock_data = $check_stock->fetch(PDO::FETCH_ASSOC);
    
    $available_stock = $stock_data['stock_quantity'] ?? 0;
    $current_cart_qty = $stock_data['cart_quantity'] ?? 0;
    
    if($qty > $available_stock){
      $response['message'] = 'Sorry, only ' . $available_stock . ' items available in stock!';
    } else {
      $update_qty = $conn->prepare("UPDATE `cart` SET quantity = ? WHERE id = ?");
      $update_qty->execute([$qty, $cart_id]);
      $response['success'] = true;
      $response['message'] = 'Cart quantity updated';
    }
  }
  elseif($_POST['action'] === 'delete_item') {
    $cart_id = $_POST['cart_id'];
    $delete_cart_item = $conn->prepare("DELETE FROM `cart` WHERE id = ?");
    $delete_cart_item->execute([$cart_id]);
    $response['success'] = true;
    $response['message'] = 'Cart item deleted!';
  }
  elseif($_POST['action'] === 'delete_all') {
    $delete_cart_item = $conn->prepare("DELETE FROM `cart` WHERE user_id = ?");
    $delete_cart_item->execute([$user_id]);
    $response['success'] = true;
    $response['message'] = 'All items deleted from cart!';
  }
  
  // Get updated cart count and total
  $count_cart = $conn->prepare("SELECT * FROM `cart` WHERE user_id = ?");
  $count_cart->execute([$user_id]);
  $response['cart_count'] = $count_cart->rowCount();
  
  $select_total = $conn->prepare("SELECT SUM(price * quantity) as total FROM `cart` WHERE user_id = ?");
  $select_total->execute([$user_id]);
  $total_data = $select_total->fetch(PDO::FETCH_ASSOC);
  $response['grand_total'] = $total_data['total'] ?? 0;
  
  header('Content-Type: application/json');
  echo json_encode($response);
  exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cart - Battlefront Computer Trading</title>

  <!--icon links-->
  <link href="https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

  <!--css links-->
   <link rel="stylesheet" href="css/styles.css">
   <link rel="stylesheet" href="css/checkoutStyles.css">
   <link rel="stylesheet" href="css/aboutStyles.css">
   <link rel="stylesheet" href="css/form-styles.css">
   <link rel="stylesheet" href="css/shopStyles.css">

   <style>
    .stock-info {
        margin: 5px 0;
        font-size: 0.85rem;
        font-weight: bold;
        color: #000000; /* Black text color */
    }
    
    .in-stock {
        color: #000000; /* Black text color */
    }
    
    .low-stock {
        color: #000000; /* Black text color */
    }
    
    .out-of-stock {
        color: #000000; /* Black text color */
    }
    
    .stock-warning {
        background: #fff3cd;
        border: 1px solid #ffeaa7;
        border-radius: 5px;
        padding: 10px;
        margin: 10px 0;
        color: #856404;
    }
    
    .qty-input.disabled {
        opacity: 0.6;
        background: #f8f9fa;
    }
    
    .update-btn.disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    .cart-item-out-of-stock {
        background: #f8d7da;
        border-left: 4px solid #dc3545;
    }
    
    .cart-item-low-stock {
        background: #fff3cd;
        border-left: 4px solid #ffc107;
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

    .ajax-loading {
        opacity: 0.6;
        pointer-events: none;
    }
   </style>

</head>
<body>

<!--header section starts-->
<?php
  include 'INCLUDES/user_header.php';
?>
<!--header section ends-->

 <div class="heading">
   <h2>shopping cart</h2>
   <p><a href="index.php"> home</a> <span> / cart</span></p>
 </div>

<section class="products">
  <!-- Cart Items -->
  <div class="container-cart">
    <table id="cart-table">
      <tr>
        <th>Product</th>
        <th>Quantity</th>
        <th>Stock Status</th>
        <th>Subtotal</th>
      </tr>
      
      <?php
      $grand_total = 0; // Initialize grand_total to 0
      $has_out_of_stock = false;
      $has_low_stock = false;

      $select_cart = $conn->prepare("SELECT c.*, p.stock_quantity 
                                   FROM `cart` c 
                                   JOIN `products` p ON c.pid = p.ID 
                                   WHERE c.user_id = ?");
      $select_cart->execute([$user_id]);

      if($select_cart->rowCount() > 0){
        while($fetch_cart = $select_cart->fetch(PDO::FETCH_ASSOC)){
          $stock_quantity = $fetch_cart['stock_quantity'] ?? 0;
          $cart_quantity = $fetch_cart['quantity'];
          $is_out_of_stock = $stock_quantity <= 0;
          $is_low_stock = $stock_quantity > 0 && $stock_quantity <= 10;
          $can_update = !$is_out_of_stock && $cart_quantity <= $stock_quantity;
          
          // Determine stock class and message
          if ($is_out_of_stock) {
              $stock_class = 'out-of-stock';
              $stock_message = 'Out of Stock';
              $has_out_of_stock = true;
          } elseif ($is_low_stock) {
              $stock_class = 'low-stock';
              $stock_message = "Only $stock_quantity left";
              $has_low_stock = true;
          } else {
              $stock_class = 'in-stock';
              $stock_message = "In Stock: $stock_quantity";
          }
          
          $sub_total = $fetch_cart['price'] * $fetch_cart['quantity'];
          $grand_total += $sub_total;
        ?>

        <tr class="cart-item <?= $is_out_of_stock ? 'cart-item-out-of-stock' : ($is_low_stock ? 'cart-item-low-stock' : '') ?>" data-cart-id="<?= $fetch_cart['ID']; ?>">
          <td>
            <div class="cart-info">
              <img src="uploaded_img/<?= $fetch_cart['image']; ?>" alt="">
              <div>
                <div class="name"><?= $fetch_cart['name']; ?></div>
                <div class="price"><span>Php</span><?= number_format($fetch_cart['price']); ?></div>
                <button type="button" class="hero-btn delete-item-btn" onclick="deleteCartItem(<?= $fetch_cart['ID']; ?>)">remove</button>
              </div>
            </div>
          </td>
          <td>
            <input type="number" name="qty" class="qty <?= !$can_update ? 'disabled' : '' ?>" 
                   min="1" max="<?= min(99, $stock_quantity) ?>" 
                   value="<?= $cart_quantity; ?>" maxlength="2"
                   <?= !$can_update ? 'disabled' : '' ?>
                   onchange="updateCartQuantity(<?= $fetch_cart['ID']; ?>, this.value)">
            <button type="button" class="fas fa-edit update-btn <?= !$can_update ? 'disabled' : '' ?>" 
                    onclick="updateCartQuantity(<?= $fetch_cart['ID']; ?>, this.previousElementSibling.value)"
                    <?= !$can_update ? 'disabled' : '' ?>></button>
          </td>

          <td>
            <div class="stock-info <?= $stock_class ?>">
              <?= $stock_message ?>
              <?php if($is_out_of_stock): ?>
                <br><small style="color: #000000;">Remove item to proceed</small>
              <?php elseif($cart_quantity > $stock_quantity): ?>
                <br><small style="color: #000000;">Reduce quantity to <?= $stock_quantity ?></small>
              <?php endif; ?>
            </div>
          </td>

          <td>
            <div class="sub-total" id="subtotal-<?= $fetch_cart['ID']; ?>">
              <span>Php<?= number_format($sub_total); ?></span>
            </div>
          </td>
        </tr>

        <?php
           }
          } else {
           echo '<tr><td colspan="4" class="empty">your cart is empty </td></tr>';
           }
      ?>
    </table>

    <!-- Stock Warnings -->
    <?php if($has_out_of_stock): ?>
      <div class="stock-warning">
        <i class="fas fa-exclamation-triangle"></i>
        <strong>Attention:</strong> Some items in your cart are out of stock. Please remove them to proceed with checkout.
      </div>
    <?php elseif($has_low_stock): ?>
      <div class="stock-warning">
        <i class="fas fa-info-circle"></i>
        <strong>Note:</strong> Some items in your cart have limited stock. We recommend completing your purchase soon.
      </div>
    <?php endif; ?>

    <div class="total-price">
      <table>
        <tr>
          <td>cart total : <span id="grand-total">Php<?= number_format($grand_total); ?></span></td>
        </tr>
      </table>
      <a href="checkout.php" class="hero-btn <?= ($grand_total > 1 && !$has_out_of_stock) ? '' : 'disabled'; ?>" 
         id="checkout-btn"
         <?= ($grand_total > 1 && !$has_out_of_stock) ? '' : 'onclick="return false;"' ?>>
        proceed to checkout
      </a>
    </div>

    <div class="more-btn">
      <button type="button" class="hero-btn" id="delete-all-btn" onclick="deleteAllCartItems()"> 
        delete all
      </button>
    </div>

  </div>

</section>

<!--footer section-->
<?php
  include 'INCLUDES/footer.php';
?>
<!--footer section ends-->

<!--javascript-->
<script src="js/javascript.js" async defer></script>

<script>
// Validate quantity inputs in cart
document.addEventListener('DOMContentLoaded', function() {
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
    
    // Prevent checkout if there are out of stock items
    const checkoutBtn = document.querySelector('a.hero-btn.disabled');
    if (checkoutBtn) {
        checkoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            showNotification('Cannot proceed to checkout. Please remove out-of-stock items from your cart.', 'error');
        });
    }
});

// Update cart quantity via AJAX
function updateCartQuantity(cartId, quantity) {
    const input = document.querySelector(`input[name="qty"][onchange*="${cartId}"]`);
    const button = input.nextElementSibling;
    
    // Show loading state
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    button.disabled = true;
    input.disabled = true;
    
    const formData = new FormData();
    formData.append('action', 'update_qty');
    formData.append('cart_id', cartId);
    formData.append('qty', quantity);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // Reset button state
        button.innerHTML = originalText;
        button.disabled = false;
        input.disabled = false;
        
        showNotification(data.message, data.success ? 'success' : 'error');
        
        if(data.success) {
            // Update cart count in header
            updateCartCount(data.cart_count);
            
            // Update grand total
            document.getElementById('grand-total').textContent = 'Php' + data.grand_total.toLocaleString();
            
            // Update subtotal for this item
            const price = parseFloat(input.closest('tr').querySelector('.price').textContent.replace('Php', '').replace(',', ''));
            const newSubtotal = price * quantity;
            document.getElementById('subtotal-' + cartId).innerHTML = '<span>Php' + newSubtotal.toLocaleString() + '</span>';
            
            // Check if we need to disable checkout button
            checkCheckoutButton();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        button.innerHTML = originalText;
        button.disabled = false;
        input.disabled = false;
        showNotification('Error updating quantity!', 'error');
    });
}

// Delete cart item via AJAX
function deleteCartItem(cartId) {
    if(!confirm('Delete this item?')) return;
    
    const row = document.querySelector(`tr[data-cart-id="${cartId}"]`);
    const deleteBtn = row.querySelector('.delete-item-btn');
    
    // Show loading state
    const originalText = deleteBtn.innerHTML;
    deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    deleteBtn.disabled = true;
    row.classList.add('ajax-loading');
    
    const formData = new FormData();
    formData.append('action', 'delete_item');
    formData.append('cart_id', cartId);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        showNotification(data.message, data.success ? 'success' : 'error');
        
        if(data.success) {
            // Remove row from table
            row.remove();
            
            // Update cart count in header
            updateCartCount(data.cart_count);
            
            // Update grand total
            document.getElementById('grand-total').textContent = 'Php' + data.grand_total.toLocaleString();
            
            // Check if cart is empty
            if(data.cart_count === 0) {
                document.getElementById('cart-table').innerHTML = '<tr><td colspan="4" class="empty">your cart is empty</td></tr>';
                document.querySelector('.more-btn').style.display = 'none';
            }
            
            // Check if we need to disable checkout button
            checkCheckoutButton();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        deleteBtn.innerHTML = originalText;
        deleteBtn.disabled = false;
        row.classList.remove('ajax-loading');
        showNotification('Error deleting item!', 'error');
    });
}

// Delete all cart items via AJAX
function deleteAllCartItems() {
    if(!confirm('Delete all items from cart?')) return;
    
    const deleteBtn = document.getElementById('delete-all-btn');
    
    // Show loading state
    const originalText = deleteBtn.innerHTML;
    deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
    deleteBtn.disabled = true;
    
    const formData = new FormData();
    formData.append('action', 'delete_all');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        showNotification(data.message, data.success ? 'success' : 'error');
        
        if(data.success) {
            // Clear cart table
            document.getElementById('cart-table').innerHTML = '<tr><td colspan="4" class="empty">your cart is empty</td></tr>';
            
            // Update cart count in header
            updateCartCount(data.cart_count);
            
            // Update grand total
            document.getElementById('grand-total').textContent = 'Php0';
            
            // Hide delete all button
            document.querySelector('.more-btn').style.display = 'none';
            
            // Disable checkout button
            document.getElementById('checkout-btn').classList.add('disabled');
            document.getElementById('checkout-btn').setAttribute('onclick', 'return false;');
        }
        
        // Reset button state
        deleteBtn.innerHTML = originalText;
        deleteBtn.disabled = false;
    })
    .catch(error => {
        console.error('Error:', error);
        deleteBtn.innerHTML = originalText;
        deleteBtn.disabled = false;
        showNotification('Error deleting items!', 'error');
    });
}

// Update cart count in header
function updateCartCount(count) {
    const cartBadge = document.querySelector('.badge');
    if(cartBadge) {
        cartBadge.textContent = count;
    }
}

// Check if checkout button should be enabled/disabled
function checkCheckoutButton() {
    const checkoutBtn = document.getElementById('checkout-btn');
    const hasOutOfStock = document.querySelector('.cart-item-out-of-stock') !== null;
    const grandTotal = parseFloat(document.getElementById('grand-total').textContent.replace('Php', '').replace(',', ''));
    
    if(grandTotal > 1 && !hasOutOfStock) {
        checkoutBtn.classList.remove('disabled');
        checkoutBtn.removeAttribute('onclick');
    } else {
        checkoutBtn.classList.add('disabled');
        checkoutBtn.setAttribute('onclick', 'return false;');
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