<?php
include 'INCLUDES/connect.php';

// Starting a new session if none exists
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if the user id is set in the session
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Handle AJAX contact form submission
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $response = ['success' => false, 'message' => ''];
    
    $name = $_POST['name'] ?? '';
    $name = filter_var($name, FILTER_SANITIZE_STRING);

    $email = $_POST['email'] ?? '';
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    
    $number = $_POST['number'] ?? '';
    $number = filter_var($number, FILTER_SANITIZE_STRING);
    
    $msg = $_POST['msg'] ?? '';
    $msg = filter_var($msg, FILTER_SANITIZE_STRING);

    // Validate required fields
    if(empty($name) || empty($email) || empty($number) || empty($msg)) {
        $response['message'] = 'Please fill all required fields!';
    } else {
        $select_message = $conn->prepare("SELECT * FROM `messages` WHERE name = ? AND email = ? AND number = ? AND message = ?");
        $select_message->execute([$name, $email, $number, $msg]);

        if($select_message->rowCount() > 0){
            $response['message'] = 'Message sent already!';
        } else {
            try {
                $insert_message = $conn->prepare("INSERT INTO `messages`(user_id, name, email, number, message) VALUES(?, ?, ?, ?, ?)");
                $insert_message->execute([$user_id, $name, $email, $number, $msg]);
                $response['success'] = true;
                $response['message'] = 'Message sent successfully!';
            } catch (PDOException $e) {
                $response['message'] = 'Error sending message. Please try again.';
            }
        }
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Battlefront Computer Trading</title>

    <!-- Icon Links -->
    <link href="https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

    <!-- CSS Links -->
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/aboutStyles.css">
    <link rel="stylesheet" href="css/form-styles.css">

    <style>
        /* Remove spinner for number input */
        input[type="tel"] {
            -moz-appearance: textfield;
        }
        input[type="tel"]::-webkit-outer-spin-button,
        input[type="tel"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        /* Contact image styling */
        .contact-image {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 20px;
        }

        .contact-image img {
            max-width: 100%;
            width: 300px;
            height: auto;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        /* Optional: Responsive row layout */
        .contact .row {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        @media (min-width: 768px) {
            .contact .row {
                flex-direction: row;
                justify-content: center;
                align-items: flex-start;
            }
            .contact form {
                max-width: 400px;
            }
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

<!-- Header section -->
<?php include 'INCLUDES/user_header.php'; ?>

<!-- Heading -->
<div class="heading">
    <h2>Contact us</h2>
    <p><a href="index.php">Home</a> <span>/ Contact</span></p>
</div>

<!-- Contact Section -->
<section class="contact">
    <div class="row">
        <div class="contact-image">
            <img src="images/IMG_20250722_140242_(400_x_400_pixel).png" alt="Contact Image">
        </div>

        <form id="contact-form">
            <h3>Tell us something!</h3>

            <input type="text" name="name" required placeholder="Enter your name" maxlength="50" class="box">
            
            <input type="tel" name="number" required placeholder="Enter your number" 
                   maxlength="11" class="box" pattern="[0-9]{11}" 
                   title="Please enter a 11-digit phone number">
            
            <input type="email" name="email" required placeholder="Enter your email" maxlength="50" class="box">
            
            <textarea name="msg" placeholder="Enter your message" required class="box" 
                      cols="30" rows="10" maxlength="500"></textarea>
            
            <button type="submit" class="hero-btn" id="submit-btn" name="send">
                <i class="fas fa-paper-plane"></i> Send Message
            </button>
        </form>
    </div>
</section>

<!-- Footer -->
<?php include 'INCLUDES/footer.php'; ?>

<!-- JavaScript -->
<script src="js/javascript.js" async defer></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const contactForm = document.getElementById('contact-form');
    const submitBtn = document.getElementById('submit-btn');

    if (contactForm && submitBtn) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // Validate form
            const name = contactForm.querySelector('input[name="name"]').value.trim();
            const email = contactForm.querySelector('input[name="email"]').value.trim();
            const number = contactForm.querySelector('input[name="number"]').value.trim();
            const msg = contactForm.querySelector('textarea[name="msg"]').value.trim();

            if (!name || !email || !number || !msg) {
                showNotification('Please fill all required fields!', 'error');
                return;
            }

            // Validate phone number
            const phoneRegex = /^[0-9]{11}$/;
            if (!phoneRegex.test(number)) {
                showNotification('Please enter a valid 11-digit phone number!', 'error');
                return;
            }

            // Validate email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showNotification('Please enter a valid email address!', 'error');
                return;
            }

            // Show loading state
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            submitBtn.disabled = true;
            contactForm.classList.add('form-loading');

            const formData = new FormData(this);
            formData.append('action', 'send_message');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Reset button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                contactForm.classList.remove('form-loading');

                showNotification(data.message, data.success ? 'success' : 'error');

                if (data.success) {
                    // Clear form on success
                    contactForm.reset();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                contactForm.classList.remove('form-loading');
                showNotification('Error sending message! Please try again.', 'error');
            });
        });
    }

    // Phone number validation
    const phoneInput = document.querySelector('input[name="number"]');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            // Remove any non-digit characters
            this.value = this.value.replace(/\D/g, '');
            
            // Limit to 11 digits
            if (this.value.length > 11) {
                this.value = this.value.slice(0, 11);
            }
        });
    }

    // Character counter for message
    const messageTextarea = document.querySelector('textarea[name="msg"]');
    if (messageTextarea) {
        const charCounter = document.createElement('div');
        charCounter.style.fontSize = '0.8rem';
        charCounter.style.color = '#666';
        charCounter.style.textAlign = 'right';
        charCounter.style.marginTop = '5px';
        messageTextarea.parentNode.insertBefore(charCounter, messageTextarea.nextSibling);

        function updateCharCounter() {
            const currentLength = messageTextarea.value.length;
            const maxLength = 500;
            charCounter.textContent = `${currentLength}/${maxLength} characters`;
            
            if (currentLength > maxLength * 0.9) {
                charCounter.style.color = '#dc3545';
            } else if (currentLength > maxLength * 0.75) {
                charCounter.style.color = '#ffc107';
            } else {
                charCounter.style.color = '#666';
            }
        }

        messageTextarea.addEventListener('input', updateCharCounter);
        updateCharCounter(); // Initialize counter
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