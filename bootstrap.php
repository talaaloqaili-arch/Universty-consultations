<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/db.php";

function require_login(): int
{
    if (!isset($_SESSION["user_id"])) {
        header("Location: login.php");
        exit;
    }

    return (int) $_SESSION["user_id"];
}

function csrf_token(): string
{
    if (!isset($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }

    return $_SESSION["csrf_token"];
}

function csrf_is_valid(): bool
{
    $submittedToken = $_POST["csrf_token"] ?? "";
    $sessionToken = $_SESSION["csrf_token"] ?? "";

    return is_string($submittedToken)
        && is_string($sessionToken)
        && $sessionToken !== ""
        && hash_equals($sessionToken, $submittedToken);
}
