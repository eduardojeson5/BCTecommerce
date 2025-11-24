<?php
include 'INCLUDES/connect.php';

session_start();

if(isset($_SESSION['user_id'])){
   $user_id = $_SESSION['user_id'];
}else{
   $user_id = '';
   header('location:index.php');
};

// Initialize messages array
$messages = [];

// Fetch user profile data
$fetch_profile = [];
if($user_id){
   $select_profile = $conn->prepare("SELECT * FROM `users` WHERE ID = ?");
   $select_profile->execute([$user_id]);
   $fetch_profile = $select_profile->fetch(PDO::FETCH_ASSOC) ?: [];
}

// Fetch cart data for display
$grand_total = 0;
$cart_items = [];
$total_products = '';
$select_cart = $conn->prepare("SELECT * FROM `cart` WHERE user_id = ?");
$select_cart->execute([$user_id]);
$cart_count = $select_cart->rowCount();

if($cart_count > 0){
   $product_names = [];
   while($fetch_cart = $select_cart->fetch(PDO::FETCH_ASSOC)){
      $product_names[] = $fetch_cart['name'].' ('.$fetch_cart['price'].' x '. $fetch_cart['quantity'].')';
      $grand_total += ($fetch_cart['price'] * $fetch_cart['quantity']);
   }
   $total_products = implode(', ', $product_names);
}

// Handle AJAX checkout request
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout_submit'])) {
   $response = ['success' => false, 'message' => '', 'redirect' => ''];
   
   $name = $_POST['name'] ?? '';
   $number = $_POST['number'] ?? '';
   $email = $_POST['email'] ?? '';
   $method = $_POST['method'] ?? '';
   $address = $_POST['address'] ?? '';
   $total_products = $_POST['total_products'] ?? '';
   $total_price = $_POST['total_price'] ?? '';

   // Check if there are items in the cart
   $check_cart = $conn->prepare("SELECT * FROM `cart` WHERE user_id = ?");
   $check_cart->execute([$user_id]);

   if($check_cart->rowCount() > 0){
      // Check if the address is provided
      if($address == ''){
         $response['message'] = 'Please add your address!';
      }else{
         // Insert order into the database
         $insert_order = $conn->prepare("INSERT INTO `orders`(user_id, name, number, email, method, address, total_products, total_price) VALUES(?,?,?,?,?,?,?,?)");
         $insert_order->execute([$user_id, $name, $number, $email, $method, $address, $total_products, $total_price]);

         if($insert_order->rowCount() > 0){
            // Delete items from the cart after placing the order
            $delete_cart = $conn->prepare("DELETE FROM `cart` WHERE user_id = ?");
            $delete_cart->execute([$user_id]);
            
            $response['success'] = true;
            $response['message'] = 'Order placed successfully!';
            $response['redirect'] = 'orders.php';
         } else {
            $response['message'] = 'Failed to place order. Please try again.';
         }
      }
   }else{
      $response['message'] = 'Your cart is empty';
   }

   header('Content-Type: application/json');
   echo json_encode($response);
   exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Checkout - Battlefront Computer Trading</title>

   <!-- Bootstrap 5 CSS -->
   <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
   
   <!-- Icon links -->
   <link href="https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet"/>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

   <!-- custom css file link  -->
   <link rel="stylesheet" href="css/styles.css">
   <link rel="stylesheet" href="css/aboutStyles.css">

   <style>
      :root {
         --header-blue: #0d6efd; /* Bootstrap primary blue */
         --header-dark: #212529; /* Bootstrap dark */
         --header-gradient: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
      }

      body {
         background: #f8f9fa;
         min-height: 100vh;
         display: flex;
         flex-direction: column;
      }

      .checkout-container {
         flex: 1;
         display: flex;
         align-items: center;
         justify-content: center;
         padding: 1rem;
         min-height: calc(100vh - 200px);
      }

      .checkout-card {
         background: white;
         border-radius: 16px;
         box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
         overflow: hidden;
         width: 100%;
         max-width: 1200px;
         margin: 0 auto;
         border: none;
      }

      /* Header matching user_header blue */
      .checkout-header {
         background: var(--header-gradient);
         color: white;
         padding: 1.5rem 2rem;
         text-align: center;
         border-bottom: 1px solid rgba(255, 255, 255, 0.3);
      }

      .checkout-header h1 {
         font-size: 1.8rem;
         font-weight: 700;
         margin-bottom: 0.5rem;
         color: white;
         text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      }

      .breadcrumb {
         background: transparent;
         color: rgba(255, 255, 255, 0.9);
         justify-content: center;
         margin-bottom: 0;
         font-size: 0.9rem;
      }

      .breadcrumb a {
         color: rgba(255, 255, 255, 0.9);
         text-decoration: none;
         transition: color 0.3s ease;
      }

      .breadcrumb a:hover {
         color: white;
      }

      .breadcrumb .active {
         color: white;
         font-weight: 600;
      }

      .checkout-body {
         padding: 2rem;
         display: flex;
         flex-direction: column;
         gap: 1.5rem;
      }

      .row-compact {
         display: grid;
         grid-template-columns: 1fr 1fr;
         gap: 1.5rem;
         height: 100%;
      }

      .section-compact {
         background: #f8f9fa;
         border-radius: 12px;
         padding: 1.25rem;
         border: 1px solid #dee2e6;
         height: 100%;
         display: flex;
         flex-direction: column;
      }

      .section-title {
         font-size: 1.1rem;
         font-weight: 600;
         color: #2d3748;
         margin-bottom: 1rem;
         display: flex;
         align-items: center;
         gap: 8px;
         padding-bottom: 0.5rem;
         border-bottom: 2px solid #e9ecef;
      }

      .section-title i {
         color: var(--header-blue);
         font-size: 1rem;
      }

      .cart-items-compact {
         flex: 1;
         overflow-y: auto;
         max-height: 200px;
      }

      .cart-item-compact {
         display: flex;
         justify-content: space-between;
         align-items: center;
         padding: 0.75rem 0;
         border-bottom: 1px solid #dee2e6;
         font-size: 0.9rem;
      }

      .cart-item-compact:last-child {
         border-bottom: none;
      }

      .item-name-compact {
         font-weight: 500;
         color: #2d3748;
         flex: 1;
      }

      .item-price-compact {
         color: var(--header-blue);
         font-weight: 600;
         text-align: right;
         font-size: 0.85rem;
      }

      .grand-total-compact {
         background: var(--header-gradient);
         color: white;
         padding: 1rem;
         border-radius: 8px;
         margin-top: auto;
         display: flex;
         justify-content: space-between;
         align-items: center;
         font-weight: 700;
         border: none;
         box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
      }

      .user-info-compact {
         display: flex;
         flex-direction: column;
         gap: 0.75rem;
         margin-bottom: 1rem;
      }

      .info-item-compact {
         display: flex;
         align-items: center;
         gap: 10px;
         padding: 0.5rem 0;
         font-size: 0.9rem;
         border-bottom: 1px solid #e9ecef;
      }

      .info-item-compact:last-child {
         border-bottom: none;
      }

      .info-item-compact i {
         width: 16px;
         color: var(--header-blue);
         text-align: center;
         font-size: 0.8rem;
      }

      .info-item-compact span {
         color: #4a5568;
         flex: 1;
      }

      .badge-warning {
         background: #ffc107;
         color: #000;
         font-size: 0.7rem;
         padding: 0.2rem 0.5rem;
         border: none;
         border-radius: 4px;
      }

      /* Buttons matching user_header style */
      .btn-compact {
         padding: 8px 16px;
         border-radius: 6px;
         font-size: 0.85rem;
         font-weight: 600;
         text-decoration: none;
         display: inline-flex;
         align-items: center;
         gap: 6px;
         transition: all 0.3s ease;
         border: none;
         cursor: pointer;
         position: relative;
      }

      .btn-update-compact {
         background: var(--header-gradient);
         color: white;
         align-self: flex-start;
         border: none;
      }

      .btn-update-compact:hover {
         background: linear-gradient(135deg, #0b5ed7 0%, #0a58ca 100%);
         transform: translateY(-2px);
         box-shadow: 0 4px 12px rgba(13, 110, 253, 0.4);
         color: white;
      }

      .payment-section-compact {
         margin-top: auto;
      }

      .payment-select-compact {
         border: 2px solid #dee2e6;
         border-radius: 8px;
         padding: 10px 12px;
         font-size: 0.9rem;
         width: 100%;
         margin-bottom: 1rem;
         background: white;
         transition: all 0.3s ease;
      }

      .payment-select-compact:focus {
         border-color: var(--header-blue);
         box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
         outline: none;
      }

      .btn-place-order-compact {
         background: linear-gradient(135deg, #198754 0%, #157347 100%);
         color: white;
         border: none;
         padding: 12px 20px;
         border-radius: 8px;
         font-weight: 700;
         transition: all 0.3s ease;
         width: 100%;
         font-size: 0.95rem;
      }

      .btn-place-order-compact:hover:not(.disabled) {
         background: linear-gradient(135deg, #157347 0%, #146c43 100%);
         transform: translateY(-2px);
         box-shadow: 0 6px 20px rgba(25, 135, 84, 0.3);
      }

      .btn-place-order-compact.disabled {
         background: #6c757d;
         cursor: not-allowed;
         transform: none;
         border: none;
      }

      .security-note {
         font-size: 0.75rem;
         color: #6c757d;
         text-align: center;
         margin-top: 0.5rem;
      }

      .security-note i {
         color: #198754;
      }

      .empty-cart-compact {
         text-align: center;
         padding: 2rem 1rem;
         color: #6c757d;
      }

      .empty-cart-compact i {
         font-size: 2.5rem;
         margin-bottom: 0.5rem;
         color: #adb5bd;
      }

      .view-cart-btn-compact {
         background: var(--header-gradient);
         color: white;
         text-decoration: none;
         padding: 8px 16px;
         border-radius: 6px;
         font-weight: 600;
         font-size: 0.85rem;
         display: inline-flex;
         align-items: center;
         gap: 6px;
         margin-top: 0.5rem;
         border: none;
         transition: all 0.3s ease;
      }

      .view-cart-btn-compact:hover {
         background: linear-gradient(135deg, #0b5ed7 0%, #0a58ca 100%);
         transform: translateY(-2px);
         box-shadow: 0 4px 12px rgba(13, 110, 253, 0.4);
         color: white;
      }

      /* Hover effects matching user_header exactly */
      .btn-compact, .view-cart-btn-compact, .btn-update-compact {
         position: relative;
         transition: all 0.3s ease;
      }

      .btn-compact:hover, .view-cart-btn-compact:hover, .btn-update-compact:hover {
         transform: translateY(-2px);
         box-shadow: 0 4px 10px rgba(13, 110, 253, 0.4);
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

      /* Responsive Design */
      @media (max-width: 992px) {
         .row-compact {
            grid-template-columns: 1fr;
            gap: 1rem;
         }

         .checkout-body {
            padding: 1.5rem;
         }

         .checkout-header {
            padding: 1.25rem 1.5rem;
         }

         .checkout-header h1 {
            font-size: 1.5rem;
         }
      }

      @media (max-width: 576px) {
         .checkout-container {
            padding: 0.5rem;
         }

         .checkout-body {
            padding: 1rem;
         }

         .checkout-header {
            padding: 1rem;
         }

         .checkout-header h1 {
            font-size: 1.3rem;
         }

         .section-compact {
            padding: 1rem;
         }

         .cart-items-compact {
            max-height: 150px;
         }

         .d-flex.gap-2 {
            flex-direction: column;
         }

         .btn-compact {
            width: 100%;
            justify-content: center;
         }
      }

      /* Custom scrollbar with blue accent */
      .cart-items-compact::-webkit-scrollbar {
         width: 6px;
      }

      .cart-items-compact::-webkit-scrollbar-track {
         background: #f1f1f1;
         border-radius: 3px;
      }

      .cart-items-compact::-webkit-scrollbar-thumb {
         background: var(--header-blue);
         border-radius: 3px;
      }

      .cart-items-compact::-webkit-scrollbar-thumb:hover {
         background: #0b5ed7;
      }
   </style>
</head>
<body>
   
<!-- header section starts  -->
<?php include 'INCLUDES/user_header.php'; ?>
<!-- header section ends -->

<section class="checkout-container">
   <div class="checkout-card">
      <!-- Checkout Header - Matching user_header blue -->
      <div class="checkout-header">
         <h1><i class="fas fa-shopping-bag"></i> Checkout</h1>
         <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
               <li class="breadcrumb-item"><a href="index.php">Home</a></li>
               <li class="breadcrumb-item"><a href="cart.php">Cart</a></li>
               <li class="breadcrumb-item active">Checkout</li>
            </ol>
         </nav>
      </div>

      <!-- Checkout Body -->
      <div class="checkout-body">
         <form id="checkout-form">
            <div class="row-compact">
               <!-- Order Summary Section -->
               <div class="section-compact">
                  <h3 class="section-title">
                     <i class="fas fa-shopping-cart"></i>
                     Order Summary
                  </h3>
                  
                  <div class="cart-items-compact">
                     <?php
                        if($cart_count > 0){
                           // Re-fetch cart items for display
                           $select_cart_display = $conn->prepare("SELECT * FROM `cart` WHERE user_id = ?");
                           $select_cart_display->execute([$user_id]);
                           
                           while($fetch_cart = $select_cart_display->fetch(PDO::FETCH_ASSOC)){
                     ?>
                     <div class="cart-item-compact">
                        <span class="item-name-compact"><?= $fetch_cart['name']; ?></span>
                        <span class="item-price-compact">₱<?= number_format($fetch_cart['price']); ?> × <?= $fetch_cart['quantity']; ?></span>
                     </div>
                     <?php
                           }
                        }else{
                           echo '
                           <div class="empty-cart-compact">
                              <i class="fas fa-shopping-cart"></i>
                              <h5>Your cart is empty</h5>
                              <a href="shop.php" class="view-cart-btn-compact">
                                 <i class="fas fa-shopping-bag"></i>
                                 Shop Now
                              </a>
                           </div>
                           ';
                        }
                     ?>
                  </div>

                  <?php if($cart_count > 0): ?>
                  <div class="grand-total-compact">
                     <span>Total Amount:</span>
                     <span>₱<?= number_format($grand_total, 2); ?></span>
                  </div>
                  
                  <div class="text-center mt-2">
                     <a href="cart.php" class="btn-compact btn-update-compact">
                        <i class="fas fa-edit"></i>
                        Edit Cart
                     </a>
                  </div>
                  <?php endif; ?>
               </div>

               <!-- Customer Information Section -->
               <div class="section-compact">
                  <h3 class="section-title">
                     <i class="fas fa-user-circle"></i>
                     Customer Information
                  </h3>

                  <div class="user-info-compact">
                     <div class="info-item-compact">
                        <i class="fas fa-user"></i>
                        <span><?= htmlspecialchars($fetch_profile['name'] ?? 'Not set') ?></span>
                     </div>
                     
                     <div class="info-item-compact">
                        <i class="fas fa-phone"></i>
                        <span><?= htmlspecialchars($fetch_profile['number'] ?? 'Not set') ?></span>
                     </div>
                     
                     <div class="info-item-compact">
                        <i class="fas fa-envelope"></i>
                        <span><?= htmlspecialchars($fetch_profile['email'] ?? 'Not set') ?></span>
                     </div>

                     <div class="info-item-compact">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>
                           <?php 
                              if(empty($fetch_profile['address'])){
                                 echo '<span class="badge badge-warning">Address required</span>';
                              }else{
                                 echo htmlspecialchars($fetch_profile['address']);
                              }
                           ?>
                        </span>
                     </div>
                  </div>

                  <div class="d-flex gap-2 mb-3">
                     <a href="update_profile.php" class="btn-compact btn-update-compact flex-fill text-center">
                        <i class="fas fa-edit"></i>
                        Update Profile
                     </a>
                     <a href="update_address.php" class="btn-compact btn-update-compact flex-fill text-center">
                        <i class="fas fa-map-marker-alt"></i>
                        Update Address
                     </a>
                  </div>

                  <div class="payment-section-compact">
                     <h3 class="section-title">
                        <i class="fas fa-credit-card"></i>
                        Payment Method
                     </h3>
                     
                     <select name="method" class="payment-select-compact" required>
                        <option value="" disabled selected>Select payment method</option>
                        <option value="Cash On Delivery">Cash On Delivery</option>
                        <option value="Credit Card">Credit Card</option>
                        <option value="GCash">GCash</option>
                        <option value="PayPal">PayPal</option>
                     </select>

                     <!-- Hidden Inputs -->
                     <input type="hidden" name="total_products" value="<?= htmlspecialchars($total_products ?? ''); ?>">
                     <input type="hidden" name="total_price" value="<?= $grand_total; ?>">
                     <input type="hidden" name="name" value="<?= htmlspecialchars($fetch_profile['name'] ?? '') ?>">
                     <input type="hidden" name="number" value="<?= htmlspecialchars($fetch_profile['number'] ?? '') ?>">
                     <input type="hidden" name="email" value="<?= htmlspecialchars($fetch_profile['email'] ?? '') ?>">
                     <input type="hidden" name="address" value="<?= htmlspecialchars($fetch_profile['address'] ?? '') ?>">

                     <!-- Place Order Button -->
                     <button type="submit" 
                            class="btn-place-order-compact <?php if(empty($fetch_profile['address']) || $cart_count == 0){echo 'disabled';} ?>" 
                            id="place-order-btn"
                            <?php if(empty($fetch_profile['address']) || $cart_count == 0){echo 'disabled';} ?>>
                        <i class="fas fa-paper-plane"></i>
                        Place Order - ₱<?= number_format($grand_total, 2); ?>
                     </button>

                     <div class="security-note">
                        <i class="fas fa-lock"></i>
                        Your payment information is secure and encrypted
                     </div>
                  </div>
               </div>
            </div>
         </form>
      </div>
   </div>
</section>

<!-- footer section starts  -->
<?php include 'INCLUDES/footer.php'; ?>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/javascript.js"></script>

<script>
   // Highlight active page in navigation
   document.addEventListener('DOMContentLoaded', function() {
      const currentPage = window.location.pathname.split("/").pop();
      document.querySelectorAll('.nav .nav-link').forEach(link => {
         if (link.getAttribute('href') === currentPage) {
            link.classList.add('active');
         }
      });

      // AJAX form submission for checkout
      const checkoutForm = document.getElementById('checkout-form');
      const placeOrderBtn = document.getElementById('place-order-btn');

      if (checkoutForm && placeOrderBtn) {
         checkoutForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // Show loading state
            const originalText = placeOrderBtn.innerHTML;
            placeOrderBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            placeOrderBtn.disabled = true;

            const formData = new FormData(this);
            formData.append('checkout_submit', 'true');

            fetch('', {
               method: 'POST',
               body: formData
            })
            .then(response => response.json())
            .then(data => {
               if (data.success) {
                  showNotification(data.message, 'success');
                  // Redirect to orders page after success
                  setTimeout(() => {
                     window.location.href = data.redirect;
                  }, 1500);
               } else {
                  showNotification(data.message, 'error');
                  // Reset button state on error
                  placeOrderBtn.innerHTML = originalText;
                  placeOrderBtn.disabled = false;
               }
            })
            .catch(error => {
               console.error('Error:', error);
               showNotification('Error processing order! Please try again.', 'error');
               placeOrderBtn.innerHTML = originalText;
               placeOrderBtn.disabled = false;
            });
         });
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
   });
</script>

</body>
</html>