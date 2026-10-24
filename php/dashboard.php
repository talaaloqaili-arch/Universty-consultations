<?php
require_once __DIR__ . "/bootstrap.php";
require_login();

if (($_SESSION["role"] ?? "") === "admin") {
    header("Location: admin_dashboard.php");
    exit;
}

$fullName = htmlspecialchars($_SESSION["full_name"] ?? "", ENT_QUOTES, "UTF-8");
$role = htmlspecialchars($_SESSION["role"] ?? "", ENT_QUOTES, "UTF-8");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#102a43">
    <title>Student dashboard | University Consultations</title>
    <link rel="stylesheet" href="assets.css">
</head>
<body>
<header class="site-header">
    <nav class="nav-wrap" aria-label="Main navigation">
        <a class="brand" href="dashboard.php">
            <span class="brand-mark" aria-hidden="true">✦</span>
            <span>University Consultations<small>Academic advising</small></span>
        </a>
        <div class="nav-links">
            <a class="nav-optional" href="my_reservations.php">My reservations</a>
            <a href="availability.php">Find an advisor</a>
            <?php if (($_SESSION["role"] ?? "") === "advisor"): ?>
                <a class="nav-optional" href="manage_slots.php">Manage times</a>
            <?php endif; ?>
            <a class="nav-cta" href="logout.php">Log out</a>
        </div>
    </nav>
</header>
<main class="page-wrap">
    <section class="dashboard-hero">
        <p class="eyebrow">Student advising portal</p>
        <h1>Welcome, <?= $fullName ?>.</h1>
        <p>Your academic journey is unique. Connect with an advisor and find the support you need to move forward.</p>
    </section>
    <div class="page-heading">
        <div>
            <p class="eyebrow">Your next step</p>
            <h2>How can we help today?</h2>
        </div>
    </div>
    <section class="dashboard-grid" aria-label="Student services">
        <a class="dashboard-card" href="availability.php">
            <span class="empty-icon" aria-hidden="true">◷</span>
            <h2>Book a consultation</h2>
            <p>Browse available appointment times and meet with an academic advisor.</p>
            <span class="text-link">Find a time <span aria-hidden="true">→</span></span>
        </a>
        <a class="dashboard-card" href="my_reservations.php">
            <span class="empty-icon" aria-hidden="true">▦</span>
            <h2>My reservations</h2>
            <p>Review appointment details, check your reservation status, or cancel a booking.</p>
            <span class="text-link">View reservations <span aria-hidden="true">→</span></span>
        </a>
        <?php if (in_array($_SESSION["role"] ?? "", ["admin", "advisor"], true)): ?>
            <a class="dashboard-card" href="manage_slots.php">
                <span class="empty-icon" aria-hidden="true">＋</span>
                <h2>Manage appointment times</h2>
                <p>Add upcoming consultation times so students can book an academic advising session.</p>
                <span class="text-link">Manage availability <span aria-hidden="true">→</span></span>
            </a>
        <?php endif; ?>
    </section>
</main>
<footer class="site-footer">University Consultations · Academic guidance for your next step</footer>
</body>
</html>
