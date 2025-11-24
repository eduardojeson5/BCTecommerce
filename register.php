<?php
include 'INCLUDES/connect.php';
session_start();

$message = [];

if (isset($_POST['submit'])) {
  $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
  $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
  $number = filter_var($_POST['number'], FILTER_SANITIZE_STRING);
  $pass = $_POST['pass'];
  $cpass = $_POST['cpass'];

  // Password strength validation
  function isPasswordStrong($password) {
    if (strlen($password) < 8) return "Password must be at least 8 characters long";
    if (!preg_match('/[A-Z]/', $password)) return "Password must contain at least one uppercase letter";
    if (!preg_match('/[a-z]/', $password)) return "Password must contain at least one lowercase letter";
    if (!preg_match('/[0-9]/', $password)) return "Password must contain at least one number";
    if (!preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $password)) return "Password must contain at least one special character";
    $common = ['password', '12345678', 'qwerty', 'admin', 'welcome'];
    if (in_array(strtolower($password), $common)) return "Password is too common. Please choose a stronger one";
    return true;
  }

  // Profile picture upload
  $profile_picture = '';
  if (!empty($_FILES['profile_picture']['name'])) {
    $image_name = $_FILES['profile_picture']['name'];
    $tmp = $_FILES['profile_picture']['tmp_name'];
    $size = $_FILES['profile_picture']['size'];
    $ext = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));
    $upload_folder = 'uploaded_img/profile_pictures/';
    if (!is_dir($upload_folder)) mkdir($upload_folder, 0777, true);

    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($ext, $allowed)) {
      $message[] = 'Only JPG, JPEG, PNG & GIF allowed!';
    } elseif ($size > 2000000) {
      $message[] = 'Profile picture must be less than 2MB!';
    } else {
      $unique = uniqid() . '_' . time() . '.' . $ext;
      $path = $upload_folder . $unique;
      if (move_uploaded_file($tmp, $path)) $profile_picture = $unique;
    }
  }

  // Check existing user
  $check_email = $conn->prepare("SELECT * FROM users WHERE email = ?");
  $check_email->execute([$email]);
  $check_number = $conn->prepare("SELECT * FROM users WHERE number = ?");
  $check_number->execute([$number]);

  if ($check_email->rowCount()) $message[] = 'Email already registered!';
  elseif ($check_number->rowCount()) $message[] = 'Phone number already registered!';
  else {
    $strength = isPasswordStrong($pass);
    if ($strength !== true) $message[] = $strength;
    elseif ($pass !== $cpass) $message[] = 'Confirm password does not match!';
    else {
      $hashed = password_hash($pass, PASSWORD_DEFAULT);
      $insert = $conn->prepare("INSERT INTO users (name, email, number, password, profile_picture) VALUES (?, ?, ?, ?, ?)");
      if ($insert->execute([$name, $email, $number, $hashed, $profile_picture])) {
        $message[] = 'Registered successfully! You can now log in.';
        $_POST = [];
      } else {
        $message[] = 'Registration failed! Try again.';
      }
    }
  }

  $_SESSION['register_message'] = $message;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Registration</title>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php
$user_id = $_SESSION['user_id'] ?? '';
include 'INCLUDES/user_header.php';
?>

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-6">
      <?php
      if (!empty($_SESSION['register_message'])) {
        $unique = array_unique($_SESSION['register_message']);
        $msg = reset($unique);
        $is_success = str_contains($msg, 'successfully');
        unset($_SESSION['register_message']);
        echo '<div class="alert ' . ($is_success ? 'alert-success' : 'alert-danger') . ' alert-dismissible fade show text-center" role="alert">'
          . htmlspecialchars($msg) .
          '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
      }
      ?>

      <div class="card shadow-sm">
        <div class="card-body p-4">
          <h3 class="card-title text-center mb-4 fw-bold">Create Your Account</h3>
          <form action="" method="post" enctype="multipart/form-data" id="registerForm">

            <div class="mb-3">
              <label class="form-label">Full Name</label>
              <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>

            <div class="mb-3">
              <label class="form-label">Email Address</label>
              <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="mb-3">
              <label class="form-label">Phone Number</label>
              <input type="text" name="number" class="form-control" required value="<?= htmlspecialchars($_POST['number'] ?? '') ?>">
            </div>

            <div class="mb-3">
              <label class="form-label">Password</label>
              <input type="password" name="pass" id="password" class="form-control" required placeholder="Create a strong password">
              <div class="progress mt-2">
                <div class="progress-bar" id="passwordStrengthBar" role="progressbar" style="width: 0%;"></div>
              </div>
              <small id="passwordFeedback" class="text-muted"></small>
            </div>

            <div class="mb-3">
              <label class="form-label">Confirm Password</label>
              <input type="password" name="cpass" id="confirmPassword" class="form-control" required placeholder="Confirm your password">
              <small id="confirmFeedback" class="text-muted"></small>
            </div>

            <div class="mb-3">
              <label class="form-label">Profile Picture (Optional)</label>
              <input type="file" name="profile_picture" class="form-control" accept="image/*">
              <small class="text-muted">Max size: 2MB • JPG, PNG, GIF</small>
            </div>

            <button type="submit" name="submit" class="btn btn-primary w-100 fw-semibold" id="submitBtn">Create Account</button>

            <p class="text-center mt-3">Already have an account? <a href="login.php" class="text-decoration-none fw-bold">Sign in here</a></p>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Password strength indicator
const password = document.getElementById('password');
const strengthBar = document.getElementById('passwordStrengthBar');
const feedback = document.getElementById('passwordFeedback');
const confirm = document.getElementById('confirmPassword');
const confirmFeedback = document.getElementById('confirmFeedback');
const submitBtn = document.getElementById('submitBtn');

password.addEventListener('input', () => {
  let val = password.value;
  let strength = 0;

  if (val.length >= 8) strength += 20;
  if (/[A-Z]/.test(val)) strength += 20;
  if (/[a-z]/.test(val)) strength += 20;
  if (/[0-9]/.test(val)) strength += 20;
  if (/[!@#$%^&*()\-_=+{};:,<.>]/.test(val)) strength += 20;

  strengthBar.style.width = strength + '%';
  if (strength < 40) {
    strengthBar.className = 'progress-bar bg-danger';
    feedback.textContent = 'Weak password';
  } else if (strength < 80) {
    strengthBar.className = 'progress-bar bg-warning';
    feedback.textContent = 'Medium strength password';
  } else {
    strengthBar.className = 'progress-bar bg-success';
    feedback.textContent = 'Strong password';
  }
});

confirm.addEventListener('input', () => {
  if (confirm.value === password.value) {
    confirmFeedback.textContent = '✓ Passwords match';
    confirmFeedback.classList.add('text-success');
    confirmFeedback.classList.remove('text-danger');
    submitBtn.disabled = false;
  } else {
    confirmFeedback.textContent = '✗ Passwords do not match';
    confirmFeedback.classList.add('text-danger');
    confirmFeedback.classList.remove('text-success');
    submitBtn.disabled = true;
  }
});
</script>
</body>
</html>
