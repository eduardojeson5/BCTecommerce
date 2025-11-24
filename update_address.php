<?php

include  'INCLUDES/connect.php';

//starting a new session
session_start();

//check if the user id is set in the session
if(isset($_SESSION['user_id'])){
  $user_id = $_SESSION['user_id'];
}else{
  $user_id = '';
  header('location:index.php');
}

// Handle AJAX address update
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update_address'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    $address1 = $_POST['address1'] ?? '';
    $city = $_POST['city'] ?? '';
    $state = $_POST['state'] ?? '';
    $country = $_POST['country'] ?? '';
    $zipcode = $_POST['zipcode'] ?? '';

    // Validate required fields
    if(empty($address1) || empty($city) || empty($state) || empty($country) || empty($zipcode)) {
        $response['message'] = 'Please fill all required fields!';
        echo json_encode($response);
        exit;
    }

    // Sanitize inputs
    $address1 = filter_var($address1, FILTER_SANITIZE_STRING);
    $city = filter_var($city, FILTER_SANITIZE_STRING);
    $state = filter_var($state, FILTER_SANITIZE_STRING);
    $country = filter_var($country, FILTER_SANITIZE_STRING);
    $zipcode = filter_var($zipcode, FILTER_SANITIZE_STRING);

    // Combine address components
    $address = $address1 .', '.$city.', '.$state.', '.$country .', '. $zipcode;

    try {
        $update_address = $conn->prepare("UPDATE `users` SET address = ? WHERE ID = ?");
        $update_address->execute([$address, $user_id]);
        
        $response['success'] = true;
        $response['message'] = 'Address updated successfully!';
    } catch (Exception $e) {
        $response['message'] = 'Error updating address. Please try again.';
    }

    echo json_encode($response);
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Update Address - Battlefront Computer Trading</title>

  <!-- Icon links -->
  <link href="https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

  <!-- CSS links -->
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

    .address-preview {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 15px;
        margin: 15px 0;
        font-size: 0.9rem;
        color: #495057;
    }

    .address-preview h4 {
        margin-bottom: 10px;
        color: #2c3e50;
        font-size: 1rem;
    }

    .form-container {
        max-width: 600px;
        margin: 0 auto;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
        color: #2c3e50;
    }

    .box {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .box:focus {
        border-color: #007bff;
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        outline: none;
    }
  </style>
</head>
<body>

<!-- Header section starts -->
<?php include 'INCLUDES/user_header.php'; ?>
<!-- Header section ends -->

<!-- Update Address Section -->
<section class="form-container">
    <form id="address-update-form">
        <h3><i class="fas fa-map-marker-alt me-2"></i>Update Your Address</h3>
        
        <div class="form-group">
            <label for="address1">Address Line 1 <span class="text-danger">*</span></label>
            <input type="text" id="address1" name="address1" maxlength="100" required placeholder="House number, street name" class="box">
        </div>

        <div class="form-group">
            <label for="city">City <span class="text-danger">*</span></label>
            <input type="text" id="city" name="city" maxlength="50" required placeholder="City name" class="box">
        </div>

        <div class="form-group">
            <label for="state">State/Province <span class="text-danger">*</span></label>
            <input type="text" id="state" name="state" maxlength="50" required placeholder="State or province name" class="box">
        </div>

        <div class="form-group">
            <label for="country">Country <span class="text-danger">*</span></label>
            <input type="text" id="country" name="country" maxlength="50" required placeholder="Country name" class="box">
        </div>

        <div class="form-group">
            <label for="zipcode">Zip/Postal Code <span class="text-danger">*</span></label>
            <input type="text" id="zipcode" name="zipcode" maxlength="50" required placeholder="Zip or postal code" class="box">
        </div>

        <!-- Address Preview -->
        <div class="address-preview" id="addressPreview" style="display: none;">
            <h4><i class="fas fa-eye me-2"></i>Address Preview</h4>
            <div id="previewContent"></div>
        </div>

        <button type="submit" class="hero-btn" id="update-btn" style="border: none; width: 100%;">
            <i class="fas fa-save me-2"></i> Save Address
        </button>

        <div class="text-center mt-3">
            <a href="checkout.php" class="text-decoration-none">
                <i class="fas fa-arrow-left me-2"></i>Back to Checkout
            </a>
        </div>
    </form>
</section>

<!-- Footer section -->
<?php include 'INCLUDES/footer2.php'; ?>
<!-- Footer section ends -->

<!-- JavaScript -->
<script src="js/javascript.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const addressForm = document.getElementById('address-update-form');
    const updateBtn = document.getElementById('update-btn');
    const addressPreview = document.getElementById('addressPreview');
    const previewContent = document.getElementById('previewContent');

    // Real-time address preview
    const addressInputs = document.querySelectorAll('#address1, #city, #state, #country, #zipcode');
    
    addressInputs.forEach(input => {
        input.addEventListener('input', updateAddressPreview);
    });

    function updateAddressPreview() {
        const address1 = document.getElementById('address1').value.trim();
        const city = document.getElementById('city').value.trim();
        const state = document.getElementById('state').value.trim();
        const country = document.getElementById('country').value.trim();
        const zipcode = document.getElementById('zipcode').value.trim();

        if (address1 || city || state || country || zipcode) {
            const fullAddress = `${address1}, ${city}, ${state}, ${country}, ${zipcode}`;
            previewContent.textContent = fullAddress;
            addressPreview.style.display = 'block';
        } else {
            addressPreview.style.display = 'none';
        }
    }

    // Form submission
    if (addressForm && updateBtn) {
        addressForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // Get form values
            const address1 = document.getElementById('address1').value.trim();
            const city = document.getElementById('city').value.trim();
            const state = document.getElementById('state').value.trim();
            const country = document.getElementById('country').value.trim();
            const zipcode = document.getElementById('zipcode').value.trim();

            // Validate required fields
            if (!address1 || !city || !state || !country || !zipcode) {
                showNotification('Please fill all required fields!', 'error');
                return;
            }

            // Show loading state
            const originalText = updateBtn.innerHTML;
            updateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            updateBtn.disabled = true;
            addressForm.classList.add('form-loading');

            const formData = new FormData(this);
            formData.append('ajax_update_address', 'true');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Reset button state
                updateBtn.innerHTML = originalText;
                updateBtn.disabled = false;
                addressForm.classList.remove('form-loading');

                showNotification(data.message, data.success ? 'success' : 'error');

                if (data.success) {
                    // Redirect to checkout after a short delay
                    setTimeout(() => {
                        window.location.href = 'checkout.php';
                    }, 1500);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                updateBtn.innerHTML = originalText;
                updateBtn.disabled = false;
                addressForm.classList.remove('form-loading');
                showNotification('Error updating address! Please try again.', 'error');
            });
        });
    }

    // Input validation and formatting
    const zipcodeInput = document.getElementById('zipcode');
    if (zipcodeInput) {
        zipcodeInput.addEventListener('input', function(e) {
            // Allow only alphanumeric characters and hyphens
            this.value = this.value.replace(/[^a-zA-Z0-9\-]/g, '');
        });
    }

    const cityInput = document.getElementById('city');
    if (cityInput) {
        cityInput.addEventListener('input', function(e) {
            // Allow only letters, spaces, and hyphens
            this.value = this.value.replace(/[^a-zA-Z\s\-]/g, '');
        });
    }

    const stateInput = document.getElementById('state');
    if (stateInput) {
        stateInput.addEventListener('input', function(e) {
            // Allow only letters and spaces
            this.value = this.value.replace(/[^a-zA-Z\s]/g, '');
        });
    }

    const countryInput = document.getElementById('country');
    if (countryInput) {
        countryInput.addEventListener('input', function(e) {
            // Allow only letters and spaces
            this.value = this.value.replace(/[^a-zA-Z\s]/g, '');
        });
    }
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
</script>

</body>
</html>