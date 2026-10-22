<?php
session_start();
require_once __DIR__ . "/user.php";

$error = "";

if (isset($_SESSION["user_id"])) {
    header("Location: " . (($_SESSION["role"] ?? "") === "admin" ? "admin_dashboard.php" : "dashboard.php"));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $error = "Please enter your email and password.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        try {
            require_once __DIR__ . "/db.php";
            $userModel = new User($pdo);
            $user = $userModel->login($email, $password);

            if ($user !== null) {
                session_regenerate_id(true);
                $_SESSION["user_id"] = $user["user_id"];
                $_SESSION["full_name"] = $user["full_name"];
                $_SESSION["role"] = $user["role"];

                header("Location: " . ($user["role"] === "admin" ? "admin_dashboard.php" : "dashboard.php"));
                exit;
            }

            $error = "The email or password is incorrect, or the account is inactive.";
        } catch (PDOException $exception) {
            error_log("Login database error: " . $exception->getMessage());
            $error = "Unable to connect to the database. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#102a43">
    <title>Log in | University Consultations</title>
    <link rel="stylesheet" href="assets.css">
</head>
<body>
<main class="auth-page">
    <section class="auth-intro">
        <a class="brand" href="login.php">
            <span class="brand-mark" aria-hidden="true">✦</span>
            <span>University Consultations<small>Academic advising</small></span>
        </a>
        <div>
            <p class="eyebrow">A clearer path forward</p>
            <h1>Guidance for where you want to go.</h1>
            <p class="auth-intro-copy">Connect with an academic advisor, get answers to your questions, and make a plan for your next step.</p>
        </div>
        <p class="auth-note">Your academic journey, supported.</p>
    </section>
    <section class="auth-content">
        <div class="auth-card">
            <p class="eyebrow">Welcome back</p>
            <h2>Log in to your account</h2>
            <p class="lead">Manage your advising appointments and stay on track.</p>
            <?php if ($error !== ""): ?>
                <p class="notice error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></p>
            <?php endif; ?>
            <form class="auth-form" action="login.php" method="POST">
                <div class="field">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" autocomplete="email" required>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" autocomplete="current-password" required>
                </div>
                <button type="submit">Log in <span aria-hidden="true">→</span></button>
            </form>
            <p class="auth-switch">New to the advising portal? <a href="register.php">Create an account</a></p>
        </div>
    </section>
</main>
</body>
</html>