<?php

include 'INCLUDES/connect.php';

session_start();

if(isset($_SESSION['user_id'])){
   $user_id = $_SESSION['user_id'];
}else{
   $user_id = '';
   header('location:index.php');
};

// Initialize message array
$message = [];

// Fetch current user data including profile picture
$select_profile = $conn->prepare("SELECT * FROM `users` WHERE ID = ?");
$select_profile->execute([$user_id]);
$fetch_profile = $select_profile->fetch(PDO::FETCH_ASSOC);

// Handle AJAX profile update
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update_profile'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $number = $_POST['number'] ?? '';
    $old_pass = $_POST['old_pass'] ?? '';
    $new_pass = $_POST['new_pass'] ?? '';
    $confirm_pass = $_POST['confirm_pass'] ?? '';

    $profile_picture = $fetch_profile['profile_picture']; // Keep current picture by default

    // Handle profile picture upload
    if(isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
        $image = $_FILES['profile_picture']['name'];
        $image = filter_var($image, FILTER_SANITIZE_STRING);
        $image_size = $_FILES['profile_picture']['size'];
        $image_tmp_name = $_FILES['profile_picture']['tmp_name'];
        $image_folder = 'uploaded_img/profile_pictures/'.$image;
        
        // Check image size (max 2MB)
        if($image_size > 2000000) {
            $response['message'] = 'Profile picture size is too large (max 2MB)!';
            echo json_encode($response);
            exit;
        } else {
            // Check if file is an actual image
            $check_image = getimagesize($image_tmp_name);
            if($check_image !== false) {
                // Generate unique filename to prevent conflicts
                $image_extension = pathinfo($image, PATHINFO_EXTENSION);
                $unique_filename = uniqid() . '_' . time() . '.' . $image_extension;
                $image_folder = 'uploaded_img/profile_pictures/'.$unique_filename;
                
                if(move_uploaded_file($image_tmp_name, $image_folder)) {
                    // Delete old profile picture if it exists
                    if(!empty($fetch_profile['profile_picture']) && file_exists('uploaded_img/profile_pictures/'.$fetch_profile['profile_picture'])){
                        unlink('uploaded_img/profile_pictures/'.$fetch_profile['profile_picture']);
                    }
                    $profile_picture = $unique_filename;
                } else {
                    $response['message'] = 'Failed to upload profile picture!';
                    echo json_encode($response);
                    exit;
                }
            } else {
                $response['message'] = 'File is not a valid image!';
                echo json_encode($response);
                exit;
            }
        }
    }

    $updates_made = false;

    if(!empty($name)){
        $update_name = $conn->prepare("UPDATE `users` SET name = ? WHERE ID = ?");
        $update_name->execute([$name, $user_id]);
        $updates_made = true;
    }

    if(!empty($email)){
        $select_email = $conn->prepare("SELECT * FROM `users` WHERE email = ? AND ID != ?");
        $select_email->execute([$email, $user_id]);
        if($select_email->rowCount() > 0){
            $response['message'] = 'Email already taken!';
            echo json_encode($response);
            exit;
        }else{
            $update_email = $conn->prepare("UPDATE `users` SET email = ? WHERE ID = ?");
            $update_email->execute([$email, $user_id]);
            $updates_made = true;
        }
    }

    if(!empty($number)){
        // Validate phone number length
        if(strlen($number) < 10 || strlen($number) > 11){
            $response['message'] = 'Phone number must be 10-11 digits!';
            echo json_encode($response);
            exit;
        } else {
            $select_number = $conn->prepare("SELECT * FROM `users` WHERE number = ? AND ID != ?");
            $select_number->execute([$number, $user_id]);
            if($select_number->rowCount() > 0){
                $response['message'] = 'Phone number already taken!';
                echo json_encode($response);
                exit;
            }else{
                $update_number = $conn->prepare("UPDATE `users` SET number = ? WHERE ID = ?");
                $update_number->execute([$number, $user_id]);
                $updates_made = true;
            }
        }
    }
    
    // Update profile picture if changed
    if($profile_picture != $fetch_profile['profile_picture']){
        $update_picture = $conn->prepare("UPDATE `users` SET profile_picture = ? WHERE ID = ?");
        $update_picture->execute([$profile_picture, $user_id]);
        $updates_made = true;
    }
    
    // Handle password update
    $empty_pass = 'da39a3ee5e6b4b0d3255bfef95601890afd80709';
    $select_prev_pass = $conn->prepare("SELECT password FROM `users` WHERE ID = ?");
    $select_prev_pass->execute([$user_id]);
    $fetch_prev_pass = $select_prev_pass->fetch(PDO::FETCH_ASSOC);
    $prev_pass = $fetch_prev_pass['password'];
    $old_pass_hashed = sha1($old_pass);
    $new_pass_hashed = sha1($new_pass);
    $confirm_pass_hashed = sha1($confirm_pass);

    if(!empty($old_pass)){
        if($old_pass_hashed != $prev_pass){
            $response['message'] = 'Old password not matched!';
            echo json_encode($response);
            exit;
        }elseif($new_pass != $confirm_pass){
            $response['message'] = 'Confirm password not matched!';
            echo json_encode($response);
            exit;
        }else{
            if(!empty($new_pass)){
                $update_pass = $conn->prepare("UPDATE `users` SET password = ? WHERE ID = ?");
                $update_pass->execute([$confirm_pass_hashed, $user_id]);
                $updates_made = true;
            }else{
                $response['message'] = 'Please enter a new password!';
                echo json_encode($response);
                exit;
            }
        }
    }

    if($updates_made) {
        $response['success'] = true;
        $response['message'] = 'Profile updated successfully!';
        // Refresh profile data
        $select_profile->execute([$user_id]);
        $fetch_profile = $select_profile->fetch(PDO::FETCH_ASSOC);
    } else {
        $response['message'] = 'No changes were made.';
    }

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
   <title>Update Profile - Battlefront Computer Trading</title>

   <!-- Boxicons CDN link -->
   <link href="https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet"/>

   <!-- Font Awesome CDN link -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
   
   <!-- Custom CSS -->
   <link rel="stylesheet" href="css/style.css">
   <link rel="stylesheet" href="css/form-styles.css">

   <style>
      .profile-picture-section {
         text-align: center;
         margin-bottom: 20px;
      }
      
      .profile-picture {
         width: 120px;
         height: 120px;
         border-radius: 50%;
         object-fit: cover;
         border: 3px solid #4CAF50;
         margin: 0 auto 15px;
      }
      
      .profile-picture-placeholder {
         width: 120px;
         height: 120px;
         border-radius: 50%;
         background: #f5f5f5;
         border: 3px dashed #ddd;
         display: flex;
         align-items: center;
         justify-content: center;
         margin: 0 auto 15px;
         color: #999;
         font-size: 40px;
      }
      
      .file-input-wrapper {
         position: relative;
         display: inline-block;
         width: 100%;
         margin-bottom: 10px;
      }
      
      .file-input-wrapper input[type="file"] {
         position: absolute;
         left: 0;
         top: 0;
         opacity: 0;
         width: 100%;
         height: 100%;
         cursor: pointer;
      }
      
      .file-input-label {
         display: block;
         padding: 10px 15px;
         background: #f8f9fa;
         color: #495057;
         border: 1px solid #dee2e6;
         border-radius: 5px;
         text-align: center;
         cursor: pointer;
         transition: all 0.2s ease;
         font-weight: 500;
         font-size: 14px;
      }
      
      .file-input-label:hover {
         background: #e9ecef;
         border-color: #4CAF50;
      }
      
      .file-input-label i {
         margin-right: 5px;
      }
      
      .file-info {
         margin-top: 5px;
         font-size: 12px;
         color: #666;
         text-align: center;
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

<section class="form-container update-form">
   <form id="profile-update-form" enctype="multipart/form-data">
      <h3>Update Profile</h3>
      
      <!-- Profile Picture Section -->
      <div class="profile-picture-section">
         <?php if (!empty($fetch_profile['profile_picture'])): ?>
            <img src="uploaded_img/profile_pictures/<?= htmlspecialchars($fetch_profile['profile_picture']) ?>" 
                 alt="Profile Picture" 
                 class="profile-picture"
                 id="currentProfilePic">
         <?php else: ?>
            <div class="profile-picture-placeholder" id="currentProfilePic">
               <i class="fas fa-user"></i>
            </div>
         <?php endif; ?>
         
         <div class="file-input-wrapper">
            <input type="file" id="profile_picture" name="profile_picture" accept="image/jpg, image/jpeg, image/png, image/webp" onchange="previewProfilePicture(this)">
            <label for="profile_picture" class="file-input-label">
               <i class="fas fa-camera"></i> Change Profile Picture
            </label>
         </div>
         
         <div class="file-info" id="fileInfo">
            Optional - Max 2MB (JPG, JPEG, PNG, WEBP)
         </div>
         
         <!-- Preview will be shown here -->
         <img id="profilePreview" class="profile-picture" style="display: none; margin-top: 10px;" alt="Preview">
      </div>

      <input type="text" name="name" placeholder="<?= htmlspecialchars($fetch_profile['name']); ?>" class="box" maxlength="50" value="<?= htmlspecialchars($fetch_profile['name']); ?>">
      <input type="email" name="email" placeholder="<?= htmlspecialchars($fetch_profile['email']); ?>" class="box" maxlength="50" value="<?= htmlspecialchars($fetch_profile['email']); ?>" oninput="this.value = this.value.replace(/\s/g, '')">
      <input type="number" name="number" placeholder="<?= htmlspecialchars($fetch_profile['number']); ?>" class="box" min="0" max="99999999999" maxlength="11" value="<?= htmlspecialchars($fetch_profile['number']); ?>" oninput="this.value = this.value.replace(/\s/g, '')">
      <input type="password" name="old_pass" placeholder="Enter your old password" class="box" maxlength="50" oninput="this.value = this.value.replace(/\s/g, '')">
      <input type="password" name="new_pass" placeholder="Enter your new password" class="box" maxlength="50" oninput="this.value = this.value.replace(/\s/g, '')">
      <input type="password" name="confirm_pass" placeholder="Confirm your new password" class="box" maxlength="50" oninput="this.value = this.value.replace(/\s/g, '')">
      <button type="submit" class="hero-btn" id="update-btn" style="border: none;">
         <i class="fas fa-save"></i> Update Profile
      </button>
   </form>
</section>

<?php include 'INCLUDES/footer2.php'; ?>

<!-- Custom JS -->
<script src="js/javascript.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const profileForm = document.getElementById('profile-update-form');
    const updateBtn = document.getElementById('update-btn');

    if (profileForm && updateBtn) {
        profileForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // Basic validation
            const name = profileForm.querySelector('input[name="name"]').value.trim();
            const email = profileForm.querySelector('input[name="email"]').value.trim();
            const number = profileForm.querySelector('input[name="number"]').value.trim();
            const oldPass = profileForm.querySelector('input[name="old_pass"]').value.trim();
            const newPass = profileForm.querySelector('input[name="new_pass"]').value.trim();
            const confirmPass = profileForm.querySelector('input[name="confirm_pass"]').value.trim();

            // Validate email format
            if (email && !isValidEmail(email)) {
                showNotification('Please enter a valid email address!', 'error');
                return;
            }

            // Validate phone number length
            if (number && (number.length < 10 || number.length > 11)) {
                showNotification('Phone number must be 10-11 digits!', 'error');
                return;
            }

            // Validate password fields
            if ((oldPass || newPass || confirmPass) && (!oldPass || !newPass || !confirmPass)) {
                showNotification('Please fill all password fields to change password!', 'error');
                return;
            }

            // Show loading state
            const originalText = updateBtn.innerHTML;
            updateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            updateBtn.disabled = true;
            profileForm.classList.add('form-loading');

            const formData = new FormData(this);
            formData.append('ajax_update_profile', 'true');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Reset button state
                updateBtn.innerHTML = originalText;
                updateBtn.disabled = false;
                profileForm.classList.remove('form-loading');

                showNotification(data.message, data.success ? 'success' : 'error');

                if (data.success) {
                    // Clear password fields on success
                    profileForm.querySelector('input[name="old_pass"]').value = '';
                    profileForm.querySelector('input[name="new_pass"]').value = '';
                    profileForm.querySelector('input[name="confirm_pass"]').value = '';
                    
                    // Reset file input
                    profileForm.querySelector('input[type="file"]').value = '';
                    document.getElementById('profilePreview').style.display = 'none';
                    document.getElementById('currentProfilePic').style.display = 'block';
                    document.getElementById('fileInfo').textContent = 'Optional - Max 2MB (JPG, JPEG, PNG, WEBP)';
                    
                    // Refresh page after a delay to show updated profile
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                updateBtn.innerHTML = originalText;
                updateBtn.disabled = false;
                profileForm.classList.remove('form-loading');
                showNotification('Error updating profile! Please try again.', 'error');
            });
        });
    }

    // Phone number validation
    const numberInput = document.querySelector('input[name="number"]');
    if (numberInput) {
        numberInput.addEventListener('input', function(e) {
            const number = this.value;
            if (number.length > 11) {
                this.value = number.slice(0, 11);
            }
        });
    }
});

function previewProfilePicture(input) {
    const preview = document.getElementById('profilePreview');
    const currentPic = document.getElementById('currentProfilePic');
    const fileInfo = document.getElementById('fileInfo');
    const file = input.files[0];
    
    if (file) {
        // Check file size
        if (file.size > 2000000) {
            showNotification('Profile picture size must be less than 2MB!', 'error');
            input.value = '';
            return;
        }

        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
            // Hide current picture when preview is shown
            if (currentPic.style.display !== 'none') {
                currentPic.style.display = 'none';
            }
        }
        
        reader.readAsDataURL(file);
        fileInfo.textContent = file.name + ' - ' + (file.size / 1024 / 1024).toFixed(2) + 'MB';
    } else {
        preview.style.display = 'none';
        // Show current picture again if no file selected
        if (currentPic.style.display === 'none') {
            currentPic.style.display = 'block';
        }
        fileInfo.textContent = 'Optional - Max 2MB (JPG, JPEG, PNG, WEBP)';
    }
}

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
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