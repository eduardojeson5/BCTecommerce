<?php
include 'INCLUDES/connect.php';

// Starting a new session if none exists
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Icon Links -->
    <link href="https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

    <!-- CSS Links -->
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/aboutStyles.css">

    <style>
        /* Heading Section */
        .heading {
            text-align: center;
            background-color: #222; /* Same color as user_header.php */
            color: #ffffff;
            padding: 3rem 1rem;
            margin-bottom: 2rem;
        }

        .heading a {
            color: #ffffff;
            text-decoration: underline;
        }

        .heading span {
            color: #e2e6ea;
        }

        /* About Section */
        .about {
            padding: 40px 0;
        }

        .about-item {
            margin-bottom: 30px;
        }

        .about-item h2 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .about-item p {
            font-size: 16px;
            line-height: 1.6;
        }

        .hero-btn {
            background-color: #333;
            color: #fff;
            padding: 10px 25px;
            font-size: 14px;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }

        .hero-btn:hover {
            background-color: #555;
        }
    </style>
</head>

<body>

    <!-- Header section -->
    <?php include 'INCLUDES/user_header.php'; ?>

    <!-- Page Heading -->
    <div class="heading">
        <h2>About Us</h2>
        <p><a href="index.php">Home</a> <span>/ About</span></p>
    </div>

    <!-- About Section -->
    <section class="about">
        <div class="container">
            <div class="about-item">
                <h2>Why Shop With Us</h2>
                <p>We offer high-quality and Brand New Products at competitive prices. Customer satisfaction is our top priority.</p>
                <a href="shop.php" class="hero-btn">Shop Now</a>
            </div>

            <div class="about-item">
                <h2>Our Journey</h2>
                <p>Founded in 2024, Battlefront Computer Trading started as a small online store and has since grown into one of the leading retailers in Sagay City.</p>
            </div>

            <div class="about-item">
                <h2>Our Values</h2>
                <p>At Battlefront Computer Trading, we believe in authenticity, quality, and customer service. We strive to provide the best shopping experience.</p>
            </div>

            <div class="about-item">
                <h2>Our Environment</h2>
                <p>We are committed to sustainability and reducing our environmental footprint. Our packaging materials are eco-friendly, and we aim to minimize waste in our operations.</p>
            </div>

            <div class="about-item">
                <h2>Company News</h2>
                <p>Stay updated with the latest product releases from Battlefront Computer Trading.</p>
            </div>

            <div class="about-item">
                <h2>Charity</h2>
                <p>Supporting our community is important to us. A portion of our proceeds goes towards charitable organizations dedicated to education and cultural preservation.</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include 'INCLUDES/footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
