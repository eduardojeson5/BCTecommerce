<?php
include 'INCLUDES/connect.php';

// Starting a new session if none exists
if (session_status() == PHP_SESSION_NONE) {
  session_start();
}

//check if the user id is set in the session
if(isset($_SESSION['user_id'])){
  $user_id = $_SESSION['user_id'];
}else{
  $user_id = '';
}

// Initialize message as array
$message = [];

if (isset($_POST['submit'])) {
  $email = $_POST['email']; 
  $email = filter_var($email, FILTER_SANITIZE_EMAIL); 
  $pass = $_POST['pass'];

  // Debug: Show what we're receiving
  error_log("Login attempt - Email: $email, Password: $pass");

  // First, find the user by email only
  $select_user = $conn->prepare("SELECT * FROM `users` WHERE email = ?");
  $select_user->execute([$email]);
  $row = $select_user->fetch(PDO::FETCH_ASSOC);

  if ($select_user->rowCount() > 0) {
    // Debug: Show what we found in database
    error_log("User found - ID: " . $row['ID'] . ", Email: " . $row['email']);
    error_log("Stored password prefix: " . substr($row['password'], 0, 20));
    error_log("Password length: " . strlen($row['password']));
    
    // Check if password is hashed with password_hash() (starts with $2y$)
    if (strpos($row['password'], '$2y$') === 0) {
      error_log("Detected: New password hash (bcrypt)");
      // New password hashing (password_hash) - use password_verify
      if (password_verify($pass, $row['password'])) {
        error_log("Password verification: SUCCESS");
        $_SESSION['user_id'] = $row['ID'];
        header('Location: index.php');
        exit;
      } else {
        error_log("Password verification: FAILED");
        $message[] = 'Incorrect email or password';
      }
    } else {
      error_log("Detected: Old password hash (sha1)");
      // Old password hashing (sha1) - for backward compatibility
      if (sha1($pass) === $row['password']) {
        error_log("SHA1 verification: SUCCESS");
        $_SESSION['user_id'] = $row['ID'];
        
        // Optional: Update to new password hash for future logins
        $new_hashed_password = password_hash($pass, PASSWORD_DEFAULT);
        $update_password = $conn->prepare("UPDATE `users` SET password = ? WHERE ID = ?");
        $update_password->execute([$new_hashed_password, $row['ID']]);
        error_log("Password upgraded to bcrypt");
        
        header('Location: index.php');
        exit;
      } else {
        error_log("SHA1 verification: FAILED");
        $message[] = 'Incorrect email or password';
      }
    }
  } else {
    error_log("No user found with email: $email");
    $message[] = 'Incorrect email or password';
  }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login</title>

  <!--icon links-->
    <!-- Boxicons -->
    <link href="https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet"/>

   <!-- font awesome cdn link  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

   <!--css links-->
   <link rel="stylesheet" href="css/styles.css">
   <link rel="stylesheet" href="css/form-styles.css" />

   <style>
    .message {
      background: #fef2f2;
      color: #dc2626;
      border: 1px solid #fecaca;
      padding: 16px 20px;
      border-radius: 10px;
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 1000;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 14px;
      max-width: 400px;
      border-left: 4px solid #dc2626;
    }
    .message i {
      cursor: pointer;
      font-size: 18px;
      opacity: 0.7;
      transition: opacity 0.2s ease;
    }
    .message i:hover {
      opacity: 1;
    }
    .debug-info {
      background: #e8f4fd;
      border: 1px solid #b3d9ff;
      padding: 15px;
      margin: 20px;
      border-radius: 8px;
      font-family: monospace;
      font-size: 12px;
    }
   </style>

</head>
<body>

<!--header section starts-->
<?php
  include 'INCLUDES/user_header.php';
?>
<!--header section ends-->

<!-- Display error messages -->
<?php
if (!empty($message)) {
  // Make sure $message is an array before using array_unique
  if (is_array($message)) {
    $unique_messages = array_unique($message);
    foreach ($unique_messages as $msg) {
      echo '
      <div class="message">
        <span>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</span>
        <i class="fas fa-times" onclick="this.parentElement.remove();"></i>
      </div>';
    }
  } else {
    // If $message is a string, display it directly
    echo '
    <div class="message">
      <span>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</span>
      <i class="fas fa-times" onclick="this.parentElement.remove();"></i>
    </div>';
  }
}
?>

<!--login section starts-->

<section class="form-container">
  <form action="" method="post">
    <h3>Login Now</h3>
    <input type="email" required maxlength="50" name="email" placeholder="Enter your email" class="box" oninput="this.value = this.value.replace(/\s/g, '')">
    <input type="password" required maxlength="20" name="pass" placeholder="Enter your password" class="box" oninput="this.value = this.value.replace(/\s/g, '')">
    <input type="submit" value="login now" class="hero-btn" name="submit">
    <p>Don't have an account? <a href="register.php">Register Now</a></p>
  </form>
</section>

<!--login section ends-->

  <!--javascript-->
  <script src="js/javascript.js" async defer></script>
  
  <script>
    // Auto-remove messages after 5 seconds
    setTimeout(() => {
      const messages = document.querySelectorAll('.message');
      messages.forEach(msg => msg.remove());
    }, 5000);
  </script>
  
</body>
</html>