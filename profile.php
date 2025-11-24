<?php
include 'INCLUDES/connect.php';

// Start a new session if none exists
if (session_status() == PHP_SESSION_NONE) {
  session_start();
}

// Check if the user ID is set in the session
if (isset($_SESSION['user_id'])) {
  $user_id = $_SESSION['user_id'];
} else {
  $user_id = '';
  header('location:index.php');
  exit();
}

// Fetch user profile data
$select_profile = $conn->prepare("SELECT * FROM `users` WHERE ID = ?");
$select_profile->execute([$user_id]);
$fetch_profile = $select_profile->fetch(PDO::FETCH_ASSOC);

// Check if profile data was fetched successfully
if (!$fetch_profile) {
  $message[] = 'User profile not found!';
  $fetch_profile = ['name' => '', 'number' => '', 'email' => '', 'address' => '', 'profile_picture' => ''];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profile</title>

  <!-- Icon links -->
  <!-- Boxicons -->
  <link href="https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet"/>

  <!-- Font Awesome CDN link -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

  <!-- CSS links -->
  <link rel="stylesheet" href="css/styles.css">
  <link rel="stylesheet" href="css/form-styles.css">
</head>
<body>

<!-- Header section starts -->
<?php include 'INCLUDES/user_header.php'; ?>
<!-- Header section ends -->

<!-- Display messages -->
<?php
if(isset($message)){
   foreach($message as $message){
      echo '
      <div class="message">
         <span>'.$message.'</span>
         <i class="fas fa-times" onclick="this.parentElement.remove();"></i>
      </div>
      ';
   }
}
?>

<!-- Profile section starts -->
<section class="user-details">
  <div class="user">
    <!-- Profile Picture -->
    <?php if (!empty($fetch_profile['profile_picture'])): ?>
      <img src="uploaded_img/profile_pictures/<?= htmlspecialchars($fetch_profile['profile_picture']) ?>" 
           alt="Profile Picture">
    <?php else: ?>
      <img src="images/user-icon.png" alt="User Icon">
    <?php endif; ?>

    <p><i class="fas fa-user"></i><span><?= htmlspecialchars($fetch_profile['name'] ?? '') ?></span></p>
    <p><i class="fas fa-phone"></i><span><?= htmlspecialchars($fetch_profile['number'] ?? '') ?></span></p>
    <p><i class="fas fa-envelope"></i><span><?= htmlspecialchars($fetch_profile['email'] ?? '') ?></span></p>
    <a href="update_profile.php" class="hero-btn">Update Info</a>
    <p class="address"><i class="fas fa-map-marker-alt"></i><span><?php echo htmlspecialchars($fetch_profile['address'] == '' ? 'please enter your address' : $fetch_profile['address']); ?></span></p>
    <a href="update_address.php" class="hero-btn">Update Address</a>
  </div>
</section>
<!-- Profile section ends -->

<!-- Footer section -->
<?php include 'INCLUDES/footer2.php'; ?>
<!-- Footer section ends -->

<!-- JavaScript -->
<script src="js/javascript.js" async defer></script>

</body>
</html>