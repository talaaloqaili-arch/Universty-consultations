<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["user_id"], $_SESSION["role"])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION["role"] !== "admin") {
    header("Location: dashboard.php");
    exit;
}

require_once __DIR__ . "/db.php";
?>